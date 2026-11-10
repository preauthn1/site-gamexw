<?php

namespace app\common\model;

use think\Model;

class TaskRecordModel extends Model {

    protected $name = 'task_record';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $scene = [
        'id'                        => 'int',
        'uid'                       => 'varchar',
        'task_id'                   => 'tinyint',
        'type'                      => 'tinyint',
        'task_type'                 => 'decimal',
        'task_data'                 => 'decimal',
        'task_num'                  => 'decimal',
        'finish_num'                => 'int',
        'reward_type'               => 'tinyint',
        'receive_status'            => 'tinyint',
        'amount_or_integral'        => 'decimal',
        'reward_amount_or_integral' => 'decimal',
        'create_time'               => 'int',
        'update_time'               => 'int',

    ];

    const NO_RECEIVE_STATUS = 0;#未领取状态
    const RECEIVED_STATUS = 1;#已领取

    const REWARD_TYPE_BALANCE = 0;#当前货币余额

    const REWARD_TYPE_INTEGRAL = 1;#积分

    public static function getReceiveStatusArr(){
        return [
            self::NO_RECEIVE_STATUS => '未领取',
            self::RECEIVED_STATUS => '已领取',
        ];
    }

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'task_id'=>lang('任务id'),
            'type'=>lang('类型'),
            'task_type'=>lang('任务类型'),
            'receive_status'=>lang('领取状态'),
            'task_data'=>lang('任务快照'),
            'task_num'=>lang('任务数量'),
            'finish_num'=>lang('完成数量'),
            'reward_type'=>lang('奖励类型'),
            'reward_amount_or_integral'=>lang('奖励数值'),
        ];
        $set_key = [
            ['type'=>[0=>lang('每日任务'), 1=>lang('每周任务')],],
            ['task_type'=>[0=>lang('最低下注'), 1=>lang('最低充值'), 2=>lang('最低倍数'), 3=>lang('累计充值'), 4=>lang('在线时长'), 5=>lang('累计下注'), 6=>lang('游戏时长')]],
            ['reward_type'=>[0=>lang('当前货币余额'), 1=>lang('积分')],],
            ['receive_status'=>[0=>lang('未领取'), 1=>lang('已领取')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    //获取金额
    public static function getamount($uid, $receiveStatus = false, $rewardType = self::REWARD_TYPE_BALANCE){
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        if( $receiveStatus !== false ){
            $condition[] = ['receive_status', '=', $receiveStatus];
        }
        $condition[] = ['reward_type', '=', $rewardType];
        return self::where($condition)->sum('reward_amount_or_integral');
    }

}