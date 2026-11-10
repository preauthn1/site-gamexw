<?php

namespace app\common\model;

use think\Model;

class SignRecordModel extends Model {

    protected $name = 'sign_record';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $scene = [
        'id'                        => 'int',
        'sign_id'                   => 'int',
        'uid'                       => 'int',
        'day'                       => 'int',
        'reward_amount'             => 'decimal',
        'reward_integral'           => 'decimal',
        'savings'                   => 'int',
        'status'                    => 'int',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'sign_id'=>lang('签到id'),
            'uid'=>lang('用户uid'),
            'day'=>lang('天数'),
            'reward_amount'=>lang('奖励金额'),
            'reward_integral'=>lang('奖励积分'),
            'savings'=>lang('流水倍数'),
            'status'=>lang('状态（0累计中，1发放当天，2发放所有）'),
        ];
        $set_key = [
            ['status'=>[0=>lang('累计中'), 1=>lang('发放当天'), 2=>lang('发放所有）')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}