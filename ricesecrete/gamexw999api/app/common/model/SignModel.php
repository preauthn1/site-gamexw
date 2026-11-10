<?php

namespace app\common\model;

use think\Model;

class SignModel extends Model {

    protected $name = 'sign';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    //参与条件
    const CONDITION_FIRST = 1;#首充金额
    const CONDITION_ONE = 2;#单笔金额
    const CONDITION_TOTAL = 3;#累计金额
    const CONDITION_APPOINT = 4;#指定任务

    //获取参与条件列表
    public static function getConditionArr(){
        return [
            self::CONDITION_FIRST,#首充金额
            self::CONDITION_ONE,#单笔金额
            self::CONDITION_TOTAL,#累计金额
            self::CONDITION_APPOINT,#指定任务
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

    //奖励模式
    const REWARD_MODE_PER_DAY = 0;#每日领取
    const REWARD_MODE_TOTAL = 1;#累计领取
    //获取奖励模式数组
    public static function getRewardModelArr(){
        return [
            self::REWARD_MODE_PER_DAY,#每日领取
            self::REWARD_MODE_TOTAL,#累计领取
        ];
    }

    //奖励类型
    const REWARD_TYPE_FIXATION = 0;#固定金额
    const REWARD_TYPE_SCALE = 1;#奖励占比%
    //获取奖励类型数组
    public static function getRewardTypeArr(){
        return [
            self::REWARD_TYPE_FIXATION,#固定金额
            self::REWARD_TYPE_SCALE,#奖励占比%
        ];
    }


    protected $scene = [
        'id'                        => 'int',
        'sign_day'                  => 'int',
        'sign_condition'            => 'tinyint',
        'recharge_amount'           => 'decimal',
        'sign_id'                   => 'int',
        'condition_date_type'       => 'tinyint',
        'agent_id'                  => 'varchar',
        'tag_id'                    => 'int',
        'vip_id'                    => 'int',
        'week_sign_count'           => 'int',
        'join_count'                => 'int',
        'reward_mode'               => 'tinyint',
        'reward_type'               => 'tinyint',
        'reward_amount'             => 'decimal',
        'reward_integral'           => 'decimal',
        'min_bet'                   => 'decimal',
        'min_recharge'              => 'decimal',
        'savings'                   => 'int',
        'status'                    => 'tinyint',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'sign_day'=>lang('签到天数'),
            'sign_condition'=>lang('参与条件（1首充金额，2单笔金额，3累计金额，4完成指定签到任务）'),
            'recharge_amount'=>lang('条件金额'),
            'sign_id'=>lang('签到id'),
            'condition_date_type'=>lang('条件对应时间（0所有，1上月，2本月，3昨天，4今天，5本周，6七天内）'),
            'agent_id'=>lang('代理线id限制（可参与）'),
            'tag_id'=>lang('标签限制（可参与）'),
            'vip_id'=>lang('VIP限制（可参与）'),
            'week_sign_count'=>lang('每周补签次数'),
            'join_count'=>lang('可参与次数'),
            'reward_mode'=>lang('奖励模式（0每日领取，1累计领取）'),
            'reward_type'=>lang('奖励类型（0固定金额，1奖励占比%）'),
            'reward_amount'=>lang('奖励金额'),
            'reward_integral'=>lang('奖励积分'),
            'savings'=>lang('流水倍数'),
            'status'=>lang('状态'),
            'min_recharge'=>lang('最低充值'),
            'min_bet'=>lang('最低下注'),
        ];
        $set_key = [
            ['sign_condition'=>[1=>lang('首充金额'), 2=>lang('单笔金额'), 3=>lang('累计金额'), 4=>lang('指定任务')],],
            ['condition_date_type'=>[0=>lang('所有'), 1=>lang('上月'), 2=>lang('本月'), 3=>lang('昨天'), 4=>lang('今天'), 5=>lang('本周'), 6=>lang('七天内')]],
            ['reward_mode'=>[0=>lang('每日领取'), 1=>lang('累计领取')],],
            ['reward_type'=>[0=>lang('固定金额'), 1=>lang('奖励占比%')],],
            ['status'=>[0=>lang('禁用'), 1=>lang('启用')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}