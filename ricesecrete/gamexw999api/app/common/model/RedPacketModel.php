<?php

namespace app\common\model;

use think\Model;

/**
 * 红包活动
 * Class RedPacketModel
 * @package app\common\model
 */
class RedPacketModel extends Model
{
    protected $name = 'red_packet';
    protected $schema = [
        'id' => 'int',
        'lang' => 'varchar',
        'name' => 'varchar',
        'model' => 'tinyint',
        'model_day' => 'varchar',
        'validity_time' => 'varchar',
        'condition' => 'tinyint',
        'condition_value' => 'float',
        'award_type' => 'tinyint',
        'award' => 'text',
        'award_limit' => 'int',
        'people_limit' => 'int',
        'user_limit' => 'int',
        'total_user_limit' => 'int',
        'max_award' => 'float',
        'receive_savings' => 'int',
        'savings' => 'int',
        'agent_id' => 'varchar',
        'tag_id' => 'int',
        'vip_id' => 'int',
        'introduce' => 'text',
        'status' => 'tinyint',
        'ip_limit' => 'tinyint',
        'sort' => 'int',
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
            'model' => lang('活动模式'),
            'model_day' => lang('有效天数'),
            'validity_time' => lang('有效参与活动时间'),
            'condition' => lang('参与条件'),
            'award_type' => lang('奖励模式'),
            'award' => lang('奖励规则'),
            'award_limit' => lang('总金额限制'),
            'people_limit' => lang('人数限制'),
            'user_limit' => lang('用户每日次/每周数限制'),
            'total_user_limit' => lang('总用户次数限制'),
            'max_award' => lang('最高奖励金额'),
            'receive_savings' => lang('流水限制'),
            'savings' => lang('流水倍数'),
            'agent_id' => lang('限制代理'),
            'tag_id' => lang('标签限制（可参与）'),
            'vip_id' => lang('VIP限制（可参与）'),
            'introduce' => lang('活动说明'),
            'status' => lang('状态'),
            'sort' => lang('排序'),
            'ip_limit' => lang('IP限制'),
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