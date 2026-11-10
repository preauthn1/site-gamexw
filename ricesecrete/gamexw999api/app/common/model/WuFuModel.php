<?php

namespace app\common\model;

use think\Model;

class WuFuModel extends Model{

    protected $name = 'wufu';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'          => 'int',
        'lang' => 'varchar',
        'name' => 'varchar',
        'image' => 'varchar',
        'losing_lottery_img' => 'varchar',
        'intro' => 'varchar',
        'agent_ids' => 'varchar',
        'start_time' => 'int',
        'end_time' => 'int',
        'amount' => 'decimal',
        'savings' => 'tinyint',
        'introduce' => 'text',
        'status' => 'tinyint',
        'register_give_count' => 'tinyint',
        'create_time' => 'int',
        'update_time' => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lang'=>lang('语言'),
            'name'=>lang('名称'),
            'image'=>lang('封面'),
            'losing_lottery_img'=>lang('未中奖图片'),
            'intro'=>lang('简介'),
            'agent_ids'=>lang('代理id串'),
            'start_time'=>lang('开始时间'),
            'end_time'=>lang('结束时间'),
            'amount'=>lang('奖励金额'),
            'savings'=>lang('流水倍数'),
            'introduce'=>lang('介绍'),
            'status'=>lang('状态'),
            'register_give_count'=>lang('注册赠送次数'),
            'create_time'=>lang('创建时间'),
            'update_time'=>lang('修改时间'),
        ];
        $set_key = [];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}