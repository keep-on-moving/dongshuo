<?php
namespace app\service;
use app\model\Spec;
use app\model\Statistics;
use think\Db;
use think\Request,
	app\model\Order,
	app\model\Product,
	app\validate\OrderValidate;

class StatisticsService
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

		$data = Statistics::where($where)->order('id', 'desc')->paginate(10000);
		
		return $data;
    }

    // 保存数据
    public function save(){
        Request::instance()->isPost() || die('request not  post!');

        $param = Request::instance()->param();	//获取参数
        $error = $this->_validate($param); 		// 是否通过验证

        if( is_null( $error ) ){
            $statistics 	    = new Statistics();
            $statistics->sn 			= $param['sn'];
            $statistics->desc 		= $param['desc'];
            $statistics->product_id = $param['product_id'];
            $product = Product::get(['id' => $param['product_id']]);
            $statistics->product_name = $product['name'];
            $statistics->author 		= $param['author'];
            $statistics->add_time	= time();
            $temp = [];
            Db::startTrans();
            if(isset($param['product'])){
                foreach ( $param['product'] as $k=>$v) {
                    $data = explode('_', $v);
                    $temp[] =  [
                        $data[0],//id
                        $data[1],
                        $data[2],
                        $param['num'][$k]
                    ];
                }

                $statistics->res = json_encode( $temp );
            }
            // 检测错误
            if( $statistics->save() ){
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
    	return Statistics::get($id);
    }


    public function update(){
    	Request::instance()->isPost() || die('request not  post!');
    	
		$param = Request::instance()->param();	//获取参数
		$error = $this->_validate($param); 		// 是否通过验证

		if( is_null( $error ) ){

            $statistics = Statistics::get($param['id']);

            $statistics->sn 			= $param['sn'];
            $statistics->desc 		= $param['desc'];
            $statistics->product_id = $param['product_id'];
            $product = Product::get(['id' => $param['product_id']]);
            $statistics->product_name = $product['name'];
            $statistics->author 		= $param['author'];
            $statistics->add_time	= time();
            $temp = [];
            Db::startTrans();
            if(isset($param['product'])) {
                foreach ($param['product'] as $k => $v) {
                    $data = explode('_', $v);
                    $temp[] = [
                        $data[0],//id
                        $data[1],
                        $data[2],
                        $param['num'][$k]
                    ];
                }

                $statistics->res = json_encode($temp);
            }

			// 检测错误
			if( $statistics->save() ){
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
    	if( Statistics::destroy($id) ){
    		return ['error'	=>	0,'msg'	=>	'删除成功'];
    	}else{
    		return ['error'	=>	100,'msg'	=>	'删除失败'];	
    	}
    }


    // 验证器
    private function _validate($data){
		// 验证
		$validate = validate('StatisticsValidate');
		if(!$validate->check($data)){
			return $validate->getError();
		}
    }




}
