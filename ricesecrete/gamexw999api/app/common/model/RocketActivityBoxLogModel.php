<?php

namespace app\common\model;

use think\Model;

/**
 * 参与宝箱开启记录
 * Class RocketActivityBoxLogModel
 * @package app\common\model
 */
class RocketActivityBoxLogModel extends Model
{
    protected $name = 'rocket_activity_box_log';
    protected $schema = [
        'id' => 'int',
        'rid' => 'int',
        'uid' => 'int',
        'type' => 'tinyint',
        'level' => 'int',
        'savings' => 'int',
        'award_value' => 'float',
        'award_type' => 'tinyint',
        'create_time' => 'int',
        'update_time' => 'int'
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'rid' => lang('活动id'),
            'uid' => lang('用户id'),
            'type' => lang('类型'),
            'level' => lang('奖励区间'),
            'savings' => lang('流水倍数'),
            'award_value' => lang('奖励数量'),
            'award_type' => lang('奖励类型'),
            'create_time' => lang('创建时间'),
            'update_time' => lang('更新时间')
        ];
        $set_key = [
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}