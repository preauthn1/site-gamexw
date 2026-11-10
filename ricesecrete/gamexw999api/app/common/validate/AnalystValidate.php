<?php
namespace app\common\validate;
use think\Validate;
class AnalystValidate extends Validate
{
    protected $rule = [
        'name'  =>  'require',
        'birthday' =>  'require',
        'position' =>  'require',
        'accuracy' =>  'require',
    ];

    protected $message  =   [
        'name.require'  => '姓名必须',
        'birthday.require' => '生日必须',
        'position.require'     => '职位必须',
        'accuracy.require'   => '准确率必须',
    ];


}