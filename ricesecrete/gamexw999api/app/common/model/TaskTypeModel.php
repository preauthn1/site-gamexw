<?php
namespace app\common\model;
use think\Model;
class TaskTypeModel extends Model
{
    protected $name = 'task_type';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'          => 'int',
        'name' => 'varchar',
        'icon' => 'varchar',
        'sort'  =>  'tinyint',
        'create_time'  =>  'int',
        'update_time'  =>  'int',
        'stage_id'  => 'int'
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'name'=>lang('名称'),
            'icon'=>lang('图标'),
            'sort'=>lang('排序'),
            'stage_id' => lang("阶段任务id"),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}