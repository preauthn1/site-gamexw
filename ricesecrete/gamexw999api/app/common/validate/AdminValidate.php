<?php
namespace app\common\validate;
use think\Validate;
class AdminValidate extends Validate
{
    protected $rule = [
        'username'  =>  'require',
        'password' =>  'require|length:6,16',
        'name' =>  'require',
        'role_id' =>  'require',
    ];

    protected $message  =   [
        'username.require' => '账号必须',
        'password.require'     => '密码必须',
        'password.length'   => '密码长度6-16位',
        'name.require'  => '姓名必须',
        'role_id.require'        => '角色必须', 
    ];

    //修改
    public function sceneEdit()
    {
        return $this->remove('password',true);
    }  

}