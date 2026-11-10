<?php
namespace app\common\validate;
use think\Validate;

class PayAgentValidate  extends Validate
{
    protected $rule = [
        'username'  =>  'require|length:6,32|unique:agent',
        'realname'  =>  'require',
        'password'  =>  'require|length:6,32',

    ];

    protected $message  =   [
        'username.require' => '账号必须',
        'username.unique' => '账号已存在',
        'username.length' => '账号必须6-32位',
        'realname.require' => '真实姓名必须',
        'password.require' => '密码必须',
        'password.length' => '密码必须6-32位',
    ];

    // edit 验证场景定义
    public function sceneEdit()
    {
        return $this->only(['realname'])
            ->remove('password', 'require');
    }
}