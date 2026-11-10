<?php

namespace app\common\model;

use think\Model;

class SignDayModel extends Model {

    protected $name = 'sign_day';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $scene = [
        'id'                        => 'int',
        'sign_id'                   => 'int',
        'day'                       => 'int',
        'reward_mode'               => 'tinyint',
        'reward_type'               => 'tinyint',
        'reward_amount'             => 'decimal',
        'reward_integral'           => 'decimal',
        'savings'                   => 'int',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'sign_id'=>lang('签到id'),
            'day'=>lang('天数'),
            'reward_mode'=>lang('奖励模式（0每日领取，1累计领取）'),
            'reward_type'=>lang('奖励类型（0固定金额，1奖励占比%）'),
            'reward_amount'=>lang('奖励金额'),
            'reward_integral'=>lang('奖励积分'),
            'savings'=>lang('流水倍数'),
        ];
        $set_key = [
            ['reward_mode'=>[0=>lang('每日领取'), 1=>lang('累计领取')],],
            ['reward_type'=>[0=>lang('固定金额'), 1=>lang('奖励占比%')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}