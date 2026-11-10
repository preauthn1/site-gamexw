<?php

namespace app\common\model;

use think\Model;

class UserLevelRewardModel extends Model{

    protected $name = 'user_level_reward';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    const WEEK_REWARD_TYPE = 1;
    const MONTH_REWARD_TYPE = 2;


    protected $scene = [
        'id'                        => 'int',
        'level_id'                  => 'int',
        'level_data'                => 'text',
        'uid'                       => 'int',
        'reward_type'               => 'tinyint',
        'reward_amount'             => 'decimal',
        'status'                    => 'tinyint',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'level_id'=>lang('等级id'),
            'level_data'=>lang('等级数据快照'),
            'uid'=>lang('用户id'),
            'reward_type'=>lang('奖励类型'),
            'reward_amount'=>lang('奖励金额'),
            'status'=>lang('状态'),
        ];
        $set_key = [
            ['reward_type'=>[1=>lang('按周奖励'), 2=>lang('按月奖励')],],
            ['status'=>[0=>lang('停止'), 1=>lang('正常')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}