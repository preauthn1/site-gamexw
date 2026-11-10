<?php
namespace app\common\validate;
use think\Validate;
class UserOfflineValidate extends Validate
{
    protected $rule = [
        //'bank_id'  =>  'require',
        'bank_sk_id' =>  'require',
        'apply_price' =>  'require',
    ];

    protected $message  =   [
        //'bank_id.require' => '转账银行卡必须',
        'bank_sk_id.require'     => '收款账户必须',
        'apply_price.require'   => '转账金额必须',
    ];

}