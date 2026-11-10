<?php
namespace app\common\validate;
use think\Validate;
class AdminRoleValidate extends Validate
{
    protected $rule = [
        'role_name'  =>  'require',
//        'node_url' =>  'require',
    ];

    protected $message  =   [
        'role_name.require' => '角色名称不得为空',
//        'node_url.require'     => '节点路径不得为空',
    ];

}

