<?php


namespace app\common\model;


use think\Model;

class TaskStageTypeModel extends Model
{
    protected $name = 'task_stage_type';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'            => 'int',
        'name'          => 'varchar',
        'icon'          => 'varchar',
        'status'        =>  'tinyint',
        'create_time'   =>  'int',
        'update_time'   =>  'int',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'name'=>lang('名称'),
            'icon'=>lang('图标'),
            'sort'=>lang('状态'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}