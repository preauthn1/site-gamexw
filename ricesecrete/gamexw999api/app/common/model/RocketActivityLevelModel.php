<?php

namespace app\common\model;

use think\Model;

/**
 * 箭活动阶梯配置
 * Class RocketActivityLevelModel
 * @package app\common\model
 */
class RocketActivityLevelModel extends Model
{
    protected $name = 'rocket_activity_level';
    protected $schema = [
        'id' => 'int',
        'rid' => 'int',
        'level' => 'int',
        'sec_mileage_min' => 'float',
        'sec_mileage_max' => 'float',
        'mileage_unit' => 'varchar',
        'award_value' => 'varchar',
        'award_type' => 'tinyint',
        'transf_ratio' => 'float',
        'use_fuel_num' => 'varchar',
        'use_fuel_mil' => 'varchar',
        'create_time' => 'int',
        'update_time' => 'int'
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'rid' => lang('活动id'),
            'level' => lang('阶梯级别'),
            'sec_mileage_min' => lang('里程区间最小值'),
            'sec_mileage_max' => lang('里程区间最大值'),
            'mileage_unit' => lang('里程单位'),
            'award_value' => lang('奖励数量'),
            'award_type' => lang('奖励类型'),
            'transf_ratio' => lang('转化比例'),
            'use_fuel_num' => lang('每次消耗的燃料数量'),
            'use_fuel_mil' => lang('前进随机公里数区间'),
            'create_time' => lang('创建时间'),
            'update_time' => lang('更新时间')
        ];
        $set_key = [
        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }
}