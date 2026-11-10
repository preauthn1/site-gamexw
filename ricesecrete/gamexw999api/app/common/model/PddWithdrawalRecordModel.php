<?php

namespace app\common\model;

use think\Model;

class PddWithdrawalRecordModel extends Model {

    protected $name = 'pdd_withdrawal_record';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';



    protected $scene = [
        'id'                        => 'int',
        'uid'                       => 'int',
        'turntable_id'              => 'int',
        'amount'                    => 'decimal',
        'savings'                   => 'int',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('UID'),
            'turntable_id'=>lang('转盘id'),
            'amount'=>lang('取款金额'),
            'savings'=>lang('流水倍数'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}