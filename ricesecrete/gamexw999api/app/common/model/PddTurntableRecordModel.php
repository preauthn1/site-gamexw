<?php

namespace app\common\model;

use think\Model;

class PddTurntableRecordModel extends Model {

    protected $name = 'pdd_turntable_record';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $scene = [
        'id'                        => 'int',
        'turntable_id'              => 'int',
        'invite_record_id'          => 'int',
        'type'                      => 'tinyint',
        'uid'                       => 'int',
        'amount'                    => 'decimal',
        'withdrawal_amount'         => 'decimal',
        'is_withdrawal'             => 'tinyint',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'turntable_id'=>lang('转盘id'),
            'invite_record_id'=>lang('邀请记录id'),
            'type'=>lang('类型（0暂无，1转盘余额，2钱包余额）'),
            'uid'=>lang('用户id'),
            'amount'=>lang('抽奖金额'),
            'withdrawal_amount'=>lang('取款金额'),
            'is_withdrawal'=>lang('是否取款（1已取款，0待取款）'),
        ];
        $set_key = [
            ['type'=>[0=>lang('暂无'), 1=>lang('转盘余额'), 2=>lang('钱包余额')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}