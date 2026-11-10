<?php
namespace app\common\validate;
use think\Validate;

class CurrencyValidate  extends Validate
{
    protected $rule = [
        'name'  =>  'require',
        'country_code'  =>  'require',
        'recharge_rate'  =>  'require',
        'withdrawal_rate'  =>  'require',
        'link_name'  =>  ['requireIf:type,1'],
    ];

    protected $message  =   [
        'name.require' => '货币名称必须',
        'country_code.require' => '国家代码必须',
        'recharge_rate.require' => '充值汇率必须',
        'withdrawal_rate.require' => '提现汇率必须',
        'link_name.requireIf' => '货币类型为USDT时链接名称必须',
    ];

}