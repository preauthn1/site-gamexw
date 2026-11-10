<?php

namespace app\common\model;

use think\Model;

/**
 * 参与燃料消耗记录
 * Class RocketActivityFuelLogModel
 * @package app\common\model
 */
class RocketActivityFuelLogModel extends Model
{
    protected $name = 'rocket_activity_fuel_log';
    protected $schema = [
        'id' => 'int',
        'rid' => 'int',
        'uid' => 'int',
        'num' => 'int',
        'mileage' => 'float',
        'all_mileage' => 'float',
        'use_fuel' => 'float',
        'use_money' => 'float',
        'transf_ratio' => 'float',
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
            'num' => lang('燃料消耗次数'),
            'mileage' => lang('前进公里数量'),
            'all_mileage' => lang('累计前进里程'),
            'use_fuel' => lang('消耗的燃料'),
            'use_money' => lang('消耗的的金额'),
            'transf_ratio' => lang('转化比例'),
            'create_time' => lang('创建时间'),
            'update_time' => lang('更新时间')
        ];
        $set_key = [
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}