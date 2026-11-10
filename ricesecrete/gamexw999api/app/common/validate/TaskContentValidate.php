<?php
namespace app\common\validate;
use app\common\model\TaskModel;
use think\Validate;

class TaskContentValidate extends Validate
{
    protected $rule = [
        'lang' => 'require',
        'task_id' => 'require|egt:0',
        'title'  =>  'require',
        'introduce' =>  'require',
    ];

    protected $message  =   [
        'lang' => '请选择语言',
        'task_id' => '缺少重要参数',
        'title'  =>  '请输入标题',
        'introduce' =>  '请输入简介',
    ];

    protected $scene = [
        'add'  =>  ['lang', 'task_id', 'title', 'introduce'],
    ];
}