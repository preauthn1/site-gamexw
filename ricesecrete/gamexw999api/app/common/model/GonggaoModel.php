<?php

namespace app\common\model;

use think\Model;

class GonggaoModel extends Model
{
    protected $name = 'gonggao';
    protected $schema = [
        'id' => 'int',
        'lang' => 'varchar',
        'add_time' => 'bigint',
        'title' => 'varchar',
        'content' => 'varchar',
        'photo' => 'varchar',
        'show_type' => 'int',
        'show_loca' => 'int',
        'start_time' => 'bigint',
        'end_time' => 'bigint',
        'status' => 'int',
        'update_time' => 'bigint',
        'admin_name' => 'varchar',
        'sort' => 'int',
        'agent_id' => 'varchar',
        'is_show_time' => 'int',
        'jump_url' => 'varchar',
        'type_id' => 'int',
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'title' => lang('标题'),
            'lang' => lang('公告语种'),
            'start_time' => lang('开始时间'),
            'end_time' => lang('结束时间'),
            'content' => lang('内容'),
            'status' => lang('状态'),
            'show_loca' => lang('显示位置'),
            'type_id' => lang('类型'),
        ];
        $set_key = [
            ['status' => [0 => lang('关闭'), 1 => lang('启用')]],
            ['show_loca' => [1 => lang('首页'), 2 => lang('代理')]],
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }


}