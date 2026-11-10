<?php
namespace app\common\validate;
use think\Validate;
class RegisterValidate extends Validate
{
    protected $rule = [
        'username'  =>  'require',
        'password' =>  'require|length:6,32',
    ];

    protected $message  =   [
        'username.require' => '用户名必须',
        'username.length' => '用户名必须6-40位',
        'password.require'     => '密码必须',
        'password.length'   => '密码长度6-32位',
    ];

    protected $scene = [
        'create'  =>  ['username','password'],
        'login'  =>  ['username','password'],
        'login1'  =>  ['username'],
        'create1'  =>  ['username'],
    ];

    // create 验证场景定义
    public function sceneCreate()
    {
        return $this->append('username', 'length:6,40');
    }

}