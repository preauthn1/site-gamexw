<?php

namespace app\common\model;

use think\Model;

/**
 * ⽕箭活动
 * Class RocketActivityModel
 * @package app\common\model
 */
class RocketActivityModel extends Model
{
    protected $name = 'rocket_activity';
    protected $schema = [
        'id' => 'int',
        'lang' => 'varchar',
        'name' => 'varchar',
        'condition' => 'tinyint',
        'all_mileage_min' => 'float',
        'all_mileage_max' => 'float',
        'savings' => 'int',
        'agent_id' => 'varchar',
        'tag_id' => 'int',
        'vip_id' => 'int',
        'introduce' => 'text',
        'status' => 'tinyint',
        'start_time' => 'int',
        'end_time' => 'int',
        'create_time' => 'int',
        'update_time' => 'int'
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'lang' => lang('语言种类'),
            'name' => lang('活动名称'),
            'condition' => lang('参与条件'),
            'all_mileage_min' => lang('起点'),
            'all_mileage_max' => lang('最大里程'),
            'savings' => lang('流水倍数'),
            'agent_id' => lang('限制代理'),
            'tag_id' => lang('标签限制（可参与）'),
            'vip_id' => lang('VIP限制（可参与）'),
            'introduce' => lang('活动说明'),
            'status' => lang('状态'),
            'start_time' => lang('开始时间'),
            'end_time' => lang('结束时间'),
            'create_time' => lang('创建时间'),
            'update_time' => lang('更新时间')
        ];
        $set_key = [
            ['status' => [0 => lang('禁用'), 1 => lang('启用')]],
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}