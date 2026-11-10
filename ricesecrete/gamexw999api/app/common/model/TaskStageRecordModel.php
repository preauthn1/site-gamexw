<?php


namespace app\common\model;


use think\Model;

class TaskStageRecordModel extends Model
{
    protected $name = 'task_stage_record';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    const NO_RECEIVE_STATUS = 0;#未领取状态
    const RECEIVED_STATUS = 1;#已领取

    protected $scene = [
        'id'                        => 'int',
        'uid'                       => 'varchar',
        'stage_id'                  => 'int',
        'type'                      => 'tinyint',
        'activity'                  => 'int',
        'reward_amount'             => 'decimal',
        'reward_integral'           => 'decimal',
        'savings'                   => 'decimal',
        'receive_status'            => 'tinyint',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'stage_id'=>lang('阶段任务id'),
            'type'=>lang('类型'),
            'activity'=>lang('活跃度'),
            'savings'=>lang('流水倍数'),
            'reward_type'=>lang('奖励类型'),
            'reward_amount'=>lang('奖励金额'),
            'reward_integral'=>lang('奖励积分'),
            'receive_status'=>lang('领取状态'),
        ];
        $set_key = [
            ['type'=>[0=>lang('每日任务'), 1=>lang('每周任务')],],
            ['receive_status'=>[0=>lang('未领取'), 1=>lang('已领取')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    //获取金额
    public static function getamount($uid, $receiveStatus = false){
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        if( $receiveStatus !== false ){
            $condition[] = ['receive_status', '=', $receiveStatus];
        }
        return self::where($condition)->sum('reward_amount');
    }
}