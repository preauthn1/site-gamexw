<?php
namespace app\common\validate;
use think\Validate;

class PaySetValidate  extends Validate
{
    protected $rule = [
        'currency_id'  =>  'require',
        'usdt_type'  =>  ['requireIf:pay_type,1'],

    ];

    protected $message  =   [
        'currency_id.require' => '货币必须',
        'usdt_type.requireIf' => '货币类型为USDT时链接名称必须',
    ];

}