<?php

namespace app\common\model;

use think\Model;

class WuFuWithdrawalModel extends Model{

    protected $name = 'wufu_withdrawal';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'          => 'int',
        'uid' => 'int',
        'username' => 'varchar',
        'w_id' => 'int',
        'card_id' => 'int',
        'amount' => 'decimal',
        'savings' => 'tinyint',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'uid'=>lang('用户id'),
            'username'=>lang('用户名'),
            'w_id'=>lang('五福id'),
            'card_id'=>lang('卡片id'),
            'amount'=>lang('金额'),
            'savings'=>lang('流水倍数'),
            'create_time'=>lang('创建时间'),
            'update_time'=>lang('修改时间'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}