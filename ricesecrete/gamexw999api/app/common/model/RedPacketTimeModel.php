<?php

namespace app\common\model;

use think\Model;

/**
 * 红包活动时间区间
 * Class RedPacketTimeModel
 * @package app\common\model
 */
class RedPacketTimeModel extends Model
{
    protected $name = 'red_packet_time';
    protected $schema = [
        'id' => 'int',
        'rid' => 'int',
        'model' => 'tinyint',
        'week' => 'int',
        'status' => 'tinyint',
        'validity_time' => 'text',
        'create_time' => 'int',
        'update_time' => 'int'
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'rid' => lang('活动id'),
            'model' => lang('活动模式'),
            'week' => lang('周活动有效天'),
            'status' => lang('状态'),
            'validity_time' => lang('有效参与活动时间区间'),
            'create_time' => lang('创建时间'),
            'update_time' => lang('更新时间')
        ];
        $set_key = [
            ['status' => [0 => lang('禁用'), 1 => lang('启用')]],
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}