<?php

namespace app\common\model;

use think\Model;

class ReceiveCodeModel extends Model{

    protected $name = 'receive_code';

    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    const AMOUNT_TYPE_NORMAL = 0;#普通

    const AMOUNT_TYPE_TOTAL = 1;#总计

    //参与条件
    const CONDITION_FIRST = 1;#首充金额
    const CONDITION_ONE = 2;#单笔金额
    const CONDITION_TOTAL = 3;#累计金额

    //获取参与条件列表
    public static function getConditionArr(){
        return [
            self::CONDITION_FIRST,#首充金额
            self::CONDITION_ONE,#单笔金额
            self::CONDITION_TOTAL,#累计金额
        ];
    }

    //条件时间
    const CONDITION_DATE_ALL = 0;#所有
    const CONDITION_DATE_LAST_MONTH = 1;#上月
    const CONDITION_DATE_MONTH = 2;#本月
    const CONDITION_DATE_YESTERDAY = 3;#昨天
    const CONDITION_DATE_TODAY = 4;#今天
    const CONDITION_DATE_WEEK = 5;#本周
    const CONDITION_DATE_SEVEN = 6;#七天内
    //获取条件时间列表
    public static function getConditionDateArr(){
        return [
            self::CONDITION_DATE_ALL,#所有
            self::CONDITION_DATE_LAST_MONTH,#上月
            self::CONDITION_DATE_MONTH,#本月
            self::CONDITION_DATE_YESTERDAY,#昨天
            self::CONDITION_DATE_TODAY,#今天
            self::CONDITION_DATE_WEEK,#本周
            self::CONDITION_DATE_SEVEN,#七天内
        ];
    }

    protected $scene = [
        'id' => 'int',
        'code' => 'varchar',
        'lang' => 'varchar',
        'title' => 'varchar',
        'image' => 'text',
        'introduce' => 'varchar',
        'content' => 'text',
        'receive_num' => 'int',
        'everyone_receive_num' => 'int',
        'level_id' => 'int',
        'agent_id' => 'int',
        'tag_id' => 'int',
        'receive_savings' => 'decimal',
        'start_time' => 'int',
        'end_time' => 'int',
        'amount_type' => 'tinyint',
        'reward_amount' => 'varchar',
        'savings' => 'decimal',
        'recharge_amount' => 'decimal',
        'sort' => 'int',
        'status' => 'tinyint',
        'create_time' => 'int',
        'update_time' => 'int',
        'receive_ip_num' => 'tinyint',
        'receive_condition' => 'tinyint',
        'condition_date_type' => 'tinyint',
        'is_auto' => 'tinyint',
        'per_person_per_day_receive_num' => 'int',
        'tag_list' => 'varchar',
    ];

    //设置开始时间时
    public function setStartTimeAttr($value){
        return $value ? strtotime($value) : '';
    }

    //设置结束时间时
    public function setEndTimeAttr($value){
        return $value ? strtotime($value) : '';
    }

    //取出开始时间时
    public function getStartTimeAttr($value){
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

    //取出结束时间时
    public function getEndTimeAttr($value){
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'code'=>lang('领取码'),
            'lang'=>lang('语言'),
            'title'=>lang('标题'),
            'image'=>lang('封面'),
            'introduce'=>lang('简介'),
            'content'=>lang('内容'),
            'receive_num'=>lang('可领取数量'),
            'everyone_receive_num'=>lang('每人限领次数'),
            'level_id'=>lang('等级id'),
            'agent_id'=>lang('代理线id'),
            'tag_id'=>lang('标签id'),
            'receive_savings'=>lang('流水限制'),
            'receive_condition'=>lang('参与条件'),
            'recharge_amount'=>lang('充值金额'),
            'condition_date_type'=>lang('条件对应时间'),
            'start_time'=>lang('开始时间'),
            'end_time'=>lang('结束时间'),
            'amount_type'=>lang('金额类型'),
            'reward_amount'=>lang('奖励金额'),
            'savings'=>lang('流水倍数'),
            'sort'=>lang('排序'),
            'status'=>lang('状态'),
            'is_auto'=>lang('是否自动领取'),
            'per_person_per_day_receive_num'=>lang('每人每天限领次数'),
            'tag_list'=>lang('领取后赋予标签'),
        ];
        $set_key = [
            ['receive_condition'=>[1=>lang('首充金额'), 2=>lang('单笔金额'), 3=>lang('累计金额')],],
            ['condition_date_type'=>[0=>lang('所有'), 1=>lang('上月'), 2=>lang('本月'), 3=>lang('昨天'), 4=>lang('今天'), 5 => lang('本周'), 6 => lang('七天内')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}