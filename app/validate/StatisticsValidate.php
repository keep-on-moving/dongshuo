<?php
namespace app\validate;
use think\Validate;

class StatisticsValidate extends Validate
{
    protected $rule = [
        'product_id'      =>  'require',
        'product'      =>  'require'
    ];

    protected $message  =   [
        'product_id.require'      =>  '产品id不能为空',
        'product.require'          =>  '策略内容不能为空'
    ];
}
