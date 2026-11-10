<?php
namespace app\common\validate;
use think\Validate;

class NationValidate extends Validate
{
    protected $rule = [
        'name'  =>  'require',
        'lang'  =>  'require',
    ];

    protected $message  =   [
        'name.require' => '国家名称必须',
        'lang.require' => '语种标识必须',
    ];
   

}