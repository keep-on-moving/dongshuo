<?php
namespace app\service;
use app\model\Spec;
use think\Db;
use think\Request,
	app\model\Order,
	app\model\Product,
	app\validate\OrderValidate;

class OutstorageService
{

    public function page(){

    	$data 	= Request::instance()->get();
    	$where 	= [];
    	
    	//封装where查询条件
    	empty($data['type']) 	|| $where['type'] 	= 	$data['type'];
    	empty($data['author'])	|| $where['author']		= 	['like','%'.$data['author'] ];
    	empty($data['sn'])		|| $where['sn'] 		= 	$data['sn'];
		if(isset($data['start_time']) && $data['start_time'] && isset($data['end_time']) && $data['end_time']){
            $where['add_time'] = ['between time', [strtotime($data['start_time']), strtotime($data['end_time'])]];
        }elseif(isset($data['start_time']) && $data['start_time']){
            $where['add_time'] = ['>', strtotime($data['start_time'])];
        }elseif(isset($data['end_time']) && $data['end_time']){
            $where['add_time'] = ['<', strtotime($data['end_time'])];
        }
    	$where['state'] 		= 	2;

		$data = Order::where($where)->order('id', 'desc')->paginate(10000);
		foreach ($data as $key => $val){
		    if(time() > ($val['add_time']+24*3600*2)){
		        $data[$key]['canDel'] = 0;
		        $data[$key]['canEdit'] = 0;
            }else{
		        $data[$key]['canDel'] = 1;
                $data[$key]['canEdit'] = 1;
                if(time() > ($val['add_time']+24*3600)){
                    $data[$key]['canEdit'] = 0;
                }
            }
		}
		
		return $data;
    }

    // 保存数据
    public function save(){
        Request::instance()->isPost() || die('request not  post!');

        $param = Request::instance()->param();	//获取参数
        $error = $this->_validate($param); 		// 是否通过验证

        if( is_null( $error ) ){
            $order 				= new Order();
            $order->sn 			= $param['sn'];
            $order->type 		= $param['type'];
            $order->desc 		= $param['desc'];
            $order->author 		= $param['author'];
            $order->supplier 	= $param['supplier'];
            $order->outstorage_checker = $param['outstorage_checker'];
            $order->outstorage_curator = $param['outstorage_curator'];
            $order->outstorage_consignee = $param['outstorage_consignee'];
            $order->state 		= $param['state'];//1-成品入库单  2-成品出库单  3-材料入库单  4-材料出库单
            $order->add_time	= time();
            $order->product_id  = $param['product_id'];
            $product = Product::getById($param['product_id']);
            $temp = [];
            Db::startTrans();
            if(isset($param['spec_id'])){
                $total = 0;
                $productModel = new Product();
                foreach ( $param['spec_id'] as $k=>$v) {
                    $param['num'][$k] = sprintf("%.2f", $param['num'][$k]);
                    if ($order->state == 2){
                        if($param['num'][$k] > 0){
                            $numData = $productModel->tarPacket($param['product_id'], $param['unit'][$k], $param['num'][$k]);
                            if($numData['code'] == 400){
                                Db::commit();
                                return ['error'	=>	100,'msg'	=> $numData['msg']];
                            }
                        }else{
                            $numData['msg'] =  0;
                        }
                    }else{
                        $numData['msg'] =  $param['num'][$k];
                    }

                    $temp[] =  [
                        $v,
                        $param['spec_name'][$k],
                        $param['num'][$k],
                        $param['time'][$k],
                        $param['unit'][$k],
                        $numData['msg']
                    ];

                    $pec = Spec::get([ 'id' => $v ]);
                    if(!$param['num'][$k]){
                        $param['num'][$k] = 0;
                    }
                    if($pec->store < $numData['msg']){
                        $msg = '规格为：'.$param['spec_name'][$k].'现有库存为'.$pec->store.'，您要出库'.$param['num'][$k].$param['unit'][$k].'需要出库量为'.$numData['msg'].$product['unit'];
                        return ['error'	=>	100,'msg'	=> $msg];
                    }
                    $total = bcadd($total, $numData['msg']);
                    $pec->store =  bcsub($pec->store, $numData['msg'],2);

                    $pec->save();
                }

                if($order->state == 2){
                    $order->expected_num = $total.$product['unit'];
                }

                $order->res = json_encode( $temp );

            }


            // 检测错误
            if( $order->save() ){
                Db::commit();
                return ['error'	=>	0,'msg'	=>	'保存成功'];
            }else{
                Db::rollback();
                return ['error'	=>	100,'msg'	=>	'保存失败'];
            }

        }else{
            return ['error'	=>	100,'msg'	=>	$error];
        }

    }


    public function edit($id){
    	return Order::get($id);
    }


    public function update(){
    	Request::instance()->isPost() || die('request not  post!');
    	
		$param = Request::instance()->param();	//获取参数
		$error = $this->_validate($param); 		// 是否通过验证

		if( is_null( $error ) ){

			$order = Order::get($param['id']);
			if (time() > ($order['add_time'] + 24*3600)){
                return ['error'	=>	100,'msg' => '订单已经生成24小时，不支持修改！'];
            }
			$order->sn 			= $param['sn'];
			$order->desc 		= $param['desc'];
			$order->author 		= $param['author'];
			$order->supplier 	= $param['supplier'];
			$order->outstorage_checker = $param['outstorage_checker'];
			$order->outstorage_curator = $param['outstorage_curator'];
			$order->outstorage_consignee = $param['outstorage_consignee'];
            $order->product_id  = $param['product_id'];
            $product = Product::getById($param['product_id']);
			$temp = [];
			$productModel = new Product();
            Db::startTrans();
			//todo 先还回库存
            $res = $order->res;
            if($res){
                $res = json_decode($res, true);
                foreach ($res as $val){
                    \db('product_spec')->where('id', $val[0])->setInc('store', $val[5]);
                }
            }

            $total = 0;
            foreach ( $param['spec_id'] as $k=>$v) {
                $param['num'][$k] = sprintf("%.2f", $param['num'][$k]);
                if ($order->state == 2){
                    if($param['num'][$k] > 0){
                        $numData = $productModel->tarPacket($param['product_id'], $param['unit'][$k], $param['num'][$k]);
                        if($numData['code'] == 400){
                            Db::commit();
                            return ['error'	=>	100,'msg'	=> $numData['msg']];
                        }
                    }else{
                        $numData['msg'] =  0;
                    }
                }else{
                    $numData['msg'] =  $param['num'][$k];
                }

                $temp[] =  [
                    $v,
                    $param['spec_name'][$k],
                    $param['num'][$k],
                    $param['time'][$k],
                    $param['unit'][$k],
                    $numData['msg']
                ];

                $pec = Spec::get([ 'id' => $v ]);
                if(!$param['num'][$k]){
                    $param['num'][$k] = 0;
                }
                if($pec->store < $numData['msg']){
                    $msg = '规格为：'.$param['spec_name'][$k].'现有库存为'.$pec->store.'，您要出库'.$param['num'][$k].$param['unit'][$k].'需要出库量为'.$numData['msg'].$product['unit'];
                    Db::rollback();
                    return ['error'	=>	100,'msg'	=> $msg];
                }

                $total = bcadd($total, $numData['msg']);
                $pec->store =  bcsub($pec->store, $numData['msg'],2);

                $pec->save();
            }

            if($order->state == 2){
                $order->expected_num = $total.$product['unit'];
            }
			
			$order->res = json_encode( $temp );

			// 检测错误
			if( $order->save() ){
			    Db::commit();
				return ['error'	=>	0,'msg'	=>	'修改成功'];
			}else{
			    Db::rollback();
				return ['error'	=>	100,'msg'	=>	'修改失败,请确认您是否有数据修改！'];
			}
		}else{
			return ['error'	=>	100,'msg'	=>	$error];
		}


    }

    public function delete($id){
		$order = Order::get($id);
		if (time() > ($order['add_time'] + 24*3600*2)){
            return ['error'	=>	100,'msg' => '订单已经生成48小时，不支持删除！'];
        }
    	if( Order::destroy($id) ){
    		return ['error'	=>	0,'msg'	=>	'删除成功'];
    	}else{
    		return ['error'	=>	100,'msg'	=>	'删除失败'];	
    	}
    }


    // 验证器
    private function _validate($data){
		// 验证
		$validate = validate('OrderValidate');
		if(!$validate->check($data)){
			return $validate->getError();
		}
    }




}
