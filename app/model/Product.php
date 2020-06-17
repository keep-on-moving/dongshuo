<?php
namespace app\model;
use think\Model;

class Product extends Model
{
	protected $pk = 'id';

	public function changeUnit($productId, $num){
	    $product = $this->find($productId);
	    $unitInfo = db('unit')->where('name', $product['unit'])->find();
	    if(!$product['unit1'] && !$product['unit2'] &&  !$product['unit3']){//如果没有设置返回默认单位
	        return $num.$product['unit'];
        }
	    if ($product['unit1'] == $unitInfo['id']){//如果顶级单位为默认单位
            return $num.$product['unit'];
        }
        if ($product['unit2'] == $unitInfo['id']){//如果顶级单位为默认单位的母单位
            $firstNum = bcdiv($num, $product['unit2_num'], 0) * $product['unit1_num'];
            $firstUnit = db('unit')->where('id', $product['unit1'])->find();
            $sendNum = $num - bcdiv($firstNum, $product['unit1_num'], 0)*$product['unit2_num'];

            return $firstNum.$firstUnit['name'].$sendNum.$product['unit'];
        }

        if ($product['unit3'] == $unitInfo['id']){//如果顶级单位为默认单位的母单位
            $firstNum = bcdiv($num, $product['unit3_num'], 0) * $product['unit1_num'];
            $firstUnit = db('unit')->where('id', $product['unit1'])->find();

            $num = $num - bcdiv($firstNum, $product['unit1_num'], 0)*$product['unit3_num'];
            $second1 = bcdiv($product['unit3_num'] , $product['unit2_num'], 0);
            $secondNum = bcdiv($num, $second1, 0);
            $secondUnit = db('unit')->where('id', $product['unit2'])->find();
            $num = $num - $secondNum * $second1;

            return $firstNum.$firstUnit['name'].$secondNum.$secondUnit['name'].$num.$product['unit'];
        }

        return '包装设置的单位没有设置到默认单位！';
    }

    public function tarPacket($productId, $unit, $num)
    {
        $product = $this->find($productId);
        $unitInfo = db('unit')->where('name', $product['unit'])->find();
        $instorageUnit = db('unit')->where('name', $unit)->find();//当前设置单位
        if(!$product['unit1'] && !$product['unit2'] &&  !$product['unit3']){//如果没有设置返回默认单位
            return ['code' => 400, 'msg' => '商品未设置包装'];
        }
        $unitArr = array(
            $product['unit1'],
            $product['unit2'],
            $product['unit3']
        );
        if (!in_array($instorageUnit['id'], $unitArr)){
            return ['code' => 400, 'msg' => '未找到对应包装单位'];
        }

        if ($product['unit1'] == $unitInfo['id']){//如果顶级单位为默认单位
            if($product['unit1'] == $instorageUnit['id']){
                return ['code' => 200, 'msg' => $num];;
            }

            return ['code' => 400, 'msg' => '未找到对应包装单位'];
        }
        if ($product['unit2'] == $unitInfo['id']){//如果顶级单位为默认单位的母单位
            if($instorageUnit['id'] == $product['unit1']){
                $num = bcdiv($product['unit2_num'], $product['unit1_num'], 0) * $num;
            }

            return ['code' => 200, 'msg' => $num];
        }

        if ($product['unit3'] == $unitInfo['id']){//如果顶级单位为默认单位的母单位
            if($instorageUnit['id'] == $product['unit1']){
                $num = bcdiv($product['unit3_num'], $product['unit1_num'], 0) * $num;
            }elseif ($instorageUnit['id'] == $product['unit2']){
                $num = bcdiv($product['unit3_num'], $product['unit2_num'], 0) * $num;
            }

            return ['code' => 200, 'msg' => $num];
        }
    }
}

