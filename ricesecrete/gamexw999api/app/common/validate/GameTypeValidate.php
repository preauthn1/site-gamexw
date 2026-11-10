<?php
namespace app\common\validate;
use think\Validate;
class GameTypeValidate extends Validate
{
    protected $rule = [
        'name'  =>  'require',
    ];

    protected $message  =   [
        'name.require' => '分类名称必须',
    ];
}