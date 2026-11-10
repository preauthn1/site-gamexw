<?php
namespace app\common\validate;
use think\Validate;
class UserBankValidate extends Validate
{    
    protected $rule = [
        'bank_name'  =>  'require',
        'bank_number' =>  'require',
        /*'bank_open' =>  'require',
        'bank_hm' =>  'require',*/
    ];

    protected $message  =   [
        'bank_name.require' => '银行名称必须填写',
        'bank_number.require'     => '卡号必须填写',
        /*'bank_open.require'   => '开卡行（支行）必须填写',
        'bank_hm.require'  => '户名必须填写',*/
    ];

}