<?php
namespace app\common\validate;
use app\common\model\TaskModel;
use think\Validate;

class SignValidate extends Validate
{
    protected $rule = [
        'sign_id' =>  'require|number|egt:0',
        'day'    => 'require|number|egt:1',
    ];

    protected $message  =   [
        'sign_id'     => '缺少重要参数',
        'day'     => '缺少重要参数',
    ];

    protected $scene = [
        'sign'  =>  ['sign_id','day'],
    ];
}