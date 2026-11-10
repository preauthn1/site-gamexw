<?php
namespace app\common\validate;
use think\Validate;
class UserMsgValidate extends Validate
{
    protected $rule = [
        'lang'      => 'require',
        'title'     => 'require',
        'content'   => 'require',
        'id'        => 'requireIf:send_type,0|requireIf:send_type,1',
        'send_type' => 'require',
    ];

    protected $message  =   [
        'lang.require' => '语种必须',
        'title.require' => '标题必须',
        'content.require' => '内容必须',
        'id' => '用户id或代理id必须',
        'send_type.require' => '发送对象必须',
    ];

}