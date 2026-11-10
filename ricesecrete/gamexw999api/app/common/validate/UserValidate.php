<?php
namespace app\common\validate;
use think\Validate;
class UserValidate extends Validate
{
    protected $rule = [
        'username'  =>  'require',
        'password'  =>  'require|length:6,32',
    ];

    protected $message  =   [
        'username.require' => '用户名必须',
        'username.length' => '用户名必须6-20位',
        'password.require' => '密码必须',
        'password.length' => '密码必须6-32位',
    ];

    protected $scene = [
        'create'  =>  ['username','password'],
    ];

    // create 验证场景定义
    public function sceneCreate()
    {
        return $this->append('username', 'length:6,20');
    }
}