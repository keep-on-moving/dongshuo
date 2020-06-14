<?php
namespace app\service;

use app\model\Spec;
use app\validate\SpecValidate;
use think\Db;
use think\Request,
	app\model\Order,
	app\model\Product,
	app\validate\OrderValidate;

class InstorageService
{

    public function page($state){

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

    	$where['state'] 		=  $state;
    	// $config['page'] = isset($data['page']) ? $data['page'] : 1;
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
			$order->instorage_checker_a = $param['instorage_checker_a'];
			$order->instorage_checker_b = $param['instorage_checker_b'];
			$order->supplier 	= $param['supplier'];
			$order->state 		= $param['state'];
			$order->add_time	= time();
			$order->product_id  = $param['product_id'];
			$order->return_num  = sprintf("%.2f", $param['return_num']);
			$order->effective_time = $param['effective_time'] ? $param['effective_time']: null;

			$temp = [];
            Db::startTrans();
            if(isset($param['spec_id'])){
                $total = 0;
                $productModel = new Product();
                foreach ( $param['spec_id'] as $k=>$v) {
                    $param['num'][$k] = sprintf("%.2f", $param['num'][$k]);
                    if ($order->state == 1){
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

                    $total = bcadd($total, $numData['msg']);
                    $pec->store =  bcadd($numData['msg'], $pec->store, 2);

                    $pec->save();
                }

                if($order->state == 1){
                    $order->expected_num = $total;
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
			$returnNum = $order->return_num;
			if (time() > ($order['add_time'] + 24*3600)){
                return ['error'	=>	100,'msg' => '订单已经生成24小时，不支持修改！'];
            }
            $order->desc 		= $param['desc'];
            $order->author 		= $param['author'];
            $order->instorage_checker_a = $param['instorage_checker_a'];
            $order->instorage_checker_b = $param['instorage_checker_b'];
            $order->supplier 	= $param['supplier'];
            $order->add_time	= time();
            $order->product_id  = $param['product_id'];
            $order->return_num  = sprintf("%.2f", $param['return_num']);
            $order->effective_time = $param['effective_time'] ? $param['effective_time'] : null;

            $temp = [];
            Db::startTrans();
            //todo 先还回库存
            $res = $order->res;
            if($res){
                $res = json_decode($res, true);
                foreach ($res as $val){
                    \db('product_spec')->where('id', $val[0])->setDec('store', $val[2]);
                }
            }

            if(isset($param['spec_id']) && $param['spec_id']){
                $total = 0;
                $productModel = new Product();
                foreach ( $param['spec_id'] as $k=>$v) {
                    $param['num'][$k] = sprintf("%.2f", $param['num'][$k]);
                    //todo 转换单位
                    if ($order->state == 1){
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

                    $total = bcadd($total, $numData['msg']);
                    $pec->store =  bcadd($numData['msg'], $pec->store, 2);

                    $pec->save();
                }
                if($order->state == 1){//TODO 如果是成品则，显示入库多少个，单位换算
                    $order->expected_num = $total;
                }
            }

            $order->res = json_encode( $temp );

			// 检测错误
			if( $order->save() ){
			    Db::commit();
				return ['error'	=>	0,'msg'	=>	'修改成功'];
			}else{
			    Db::rollback();
				return ['error'	=>	100,'msg'	=>	'修改失败'];	
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
