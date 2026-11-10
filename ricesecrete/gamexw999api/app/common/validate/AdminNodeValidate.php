<?php
namespace app\common\validate;
use think\Validate;
class AdminNodeValidate extends Validate
{
    protected $rule = [
        'node_name'  =>  'require',
//        'node_url' =>  'require',
    ];

    protected $message  =   [
        'node_name.require' => '节点名称不得为空',
//        'node_url.require'     => '节点路径不得为空',
    ];

}