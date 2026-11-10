<?php

namespace app\common\model;

use think\Model;

class TaskContentModel extends Model {

    protected $name = 'task_content';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $scene = [
        'id'                        => 'int',
        'lang'                      => 'varchar',
        'task_id'                   => 'int',
        'title'                     => 'varchar',
        'introduce'                 => 'varchar',
        'content'                 => 'longtext',
        'create_time'               => 'int',
        'update_time'               => 'int',

    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lang'=>lang('语言'),
            'task_id'=>lang('任务id'),
            'title'=>lang('标题'),
            'introduce'=>lang('简介'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}