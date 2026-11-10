<?php

namespace app\common\model;

use think\Model;

class ReceiveCodeRecordModel extends Model{

    protected $name = 'receive_code_record';

    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $scene = [
        'id' => 'int',
        'uid' => 'int',
        'code' => 'varchar',
        'receive_code_id' => 'int',
        'receive_code_data' => 'text',
        'amount_type' => 'tinyint',
        'reward_amount' => 'decimal',
        'create_time' => 'int',
        'update_time' => 'int',
        'ip' => 'varchar',
        'savings' => 'int',
        'tag_list' => 'varchar',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'code'=>lang('领取码'),
            'receive_code_id'=>lang('领取码id'),
            'receive_code_data'=>lang('领取码数据快照'),
            'amount_type'=>lang('金额类型'),
            'reward_amount'=>lang('奖励金额'),
            'ip'=>lang('领取ip'),
            'savings'=>lang('流水字段'),
            'tag_list'=>lang('领取后赋予标签'),
        ];
        $set_key = [
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}