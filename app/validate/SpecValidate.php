<?php
namespace app\validate;
use think\Validate;

class SpecValidate extends Validate
{
    protected $rule = [
        'product_id'      =>  'require|number',
        'spec_name'      =>  'require'
    ];

    protected $message  =   [
        'product_id.require'      =>  '产品不能为空',
        'product_id.int'          =>  '产品id必须为数字',
        'spec_name.require'          =>  '规格名称不能为空'
    ];
}
