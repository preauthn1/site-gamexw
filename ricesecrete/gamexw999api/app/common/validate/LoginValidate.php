<?php
namespace app\common\validate;
use think\Validate;
class LoginValidate extends Validate
{
    protected $rule = [
        'username'  =>  'require',
        'password' =>  'require|length:6,12',
        'code'  =>  'require',
    ];

    protected $message  =   [
        'username.require' => '用户名必须',
        'password.require'     => '密码必须',
        'password.length'   => '密码长度6-12位',
        'code.require'  =>  '验证码必须',
    ];


}