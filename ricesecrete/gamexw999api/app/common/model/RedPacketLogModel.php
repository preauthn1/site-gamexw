<?php

namespace app\common\model;

use think\Model;

/**
 * 红包活动领取记录
 * Class RedPacketLogModel
 * @package app\common\model
 */
class RedPacketLogModel extends Model
{
    protected $name = 'red_packet_log';
    protected $schema = [
        'id' => 'int',
        'uid' => 'int',
        'rid' => 'int',
        'tid' => 'int',
        'award_value' => 'float',
        'award_type' => 'tinyint',
        'validity_time' => 'varchar',
        'day' => 'varchar',
        'week' => 'varchar',
        'month' => 'varchar',
        'create_time' => 'int',
        'update_time' => 'int'
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'uid' => lang('UID'),
            'rid' => lang('活动id'),
            'tid' => lang('活动时间区间id'),
            'award_value' => lang('获得奖励'),
            'award_type' => lang('奖励类型'),
            'validity_time' => lang('时间区间'),
            'day' => lang('年月日'),
            'week' => lang('年周'),
            'month' => lang('年月'),
            'create_time' => lang('创建时间'),
            'update_time' => lang('更新时间')
        ];
        $set_key = [

        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}