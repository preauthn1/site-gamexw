<?php

namespace app\common\model;

use think\Model;

/**
 * 参与火箭活动记录
 * Class RocketActivityRecordModel
 * @package app\common\model
 */
class RocketActivityRecordModel extends Model
{
    protected $name = 'rocket_activity_record';
    protected $schema = [
        'id' => 'int',
        'rid' => 'int',
        'uid' => 'int',
        'now_mileage' => 'float',
        'now_level' => 'int',
        'now_get_level' => 'int',
        'create_time' => 'int',
        'update_time' => 'int'
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'rid' => lang('活动id'),
            'uid' => lang('参与用户id'),
            'now_mileage' => lang('当前总里程'),
            'now_level' => lang('当前高度区间id'),
            'now_get_level' => lang('当前领取的礼包 区间id'),
            'create_time' => lang('创建时间'),
            'update_time' => lang('更新时间')
        ];
        $set_key = [
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}