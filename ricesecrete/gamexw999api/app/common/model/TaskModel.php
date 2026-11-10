<?php

namespace app\common\model;

use think\Model;

class TaskModel extends Model {

    protected $name = 'task';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    //类型
    const ALL_TYPE = -1;#所有
    const TODAY_TYPE = 0;#每日任务类型
    const WEEK_TYPE = 1;#每周任务类型
    const CUSTOM_TYPE = 2;#自定义时间类型

    //获取所有类型列表
    public static function getTypeArr(){
        return [
            self::ALL_TYPE,#所有
            self::TODAY_TYPE,#每日任务
            self::WEEK_TYPE,#每周任务
            self::CUSTOM_TYPE,#自定义时间
        ];
    }

    //任务条件
    const MIN_BET_TASK_CONDITION = 0;#最低下注额任务条件
    const MIN_RECHARGE_TASK_CONDITION = 1;#最低充值任务条件
    const MIN_MULTIPLE_TASK_CONDITION = 2;#最低倍数条件
    const CUMULATIVE_RECHARGE_TASK_CONDITION = 3;#累计充值
    const ONLINE_TIME_TASK_CONDITION = 4;#在线时长
    const CUMULATIVE_BET_TASK_CONDITION = 5;#累计下注
    const GAME_ONLINE_TIME_TASK_CONDITION = 6;#游戏时长
    const INVITE_PEOPLE_TASK_CONDITION = 7;#邀请人数
    const APP_LOGIN_TASK_CONDITION = 8;#APP登录
    const ADD_BANK_TASK_CONDITION = 9;#添加银行卡
    //获取任务类型列表
    public static function getTaskTypeArr(){
        return [
            self::MIN_BET_TASK_CONDITION,#最低下注额任务条件
            self::MIN_RECHARGE_TASK_CONDITION,#最低充值任务条件
            self::MIN_MULTIPLE_TASK_CONDITION,#最低倍数任务条件
            self::CUMULATIVE_RECHARGE_TASK_CONDITION,#累计充值
            self::ONLINE_TIME_TASK_CONDITION,#在线时长
            self::CUMULATIVE_BET_TASK_CONDITION,#累计下注
            self::GAME_ONLINE_TIME_TASK_CONDITION,#游戏时长
            self::INVITE_PEOPLE_TASK_CONDITION,#邀请人数
            self::APP_LOGIN_TASK_CONDITION,#APP登录
            self::ADD_BANK_TASK_CONDITION,#APP登录
        ];
    }

    //奖励类型
    const BALANCE_REWARD_TYPE = 0;#当前货币余额
    const INTEGRAL_REWARD_TYPE = 1;#积分
    const WUFU_REWARD_TYPE = 2;#五福
    //获取奖励类型数组
    public static function getRewardTypeArr(){
        return [
            self::BALANCE_REWARD_TYPE,#当前货币余额
            self::INTEGRAL_REWARD_TYPE,#积分
            self::WUFU_REWARD_TYPE,#五福
        ];
    }

    //任务模式
    const NORMAL_TASK_MODE = 0;#普通任务
    const STAGE_TASK_MODE = 1;#阶段任务


    protected $scene = [
        'id'                        => 'int',
        'background_img'            => 'varchar',
        'task_type'                 => 'int',
        'type'                      => 'tinyint',
        'task_condition'            => 'varchar',
        'min_bet'                   => 'decimal',
        'min_recharge'              => 'decimal',
        'multiple'                  => 'decimal',
        'task_num'                  => 'int',
        'reward_type'               => 'tinyint',
        'amount_or_integral'        => 'decimal',
        'sort'                      => 'tinyint',
        'savings'                   => 'decimal',
        'cumulative_recharge'       => 'decimal',
        'online_time'               => 'int',
        'cumulative_bet'            => 'decimal',
        'game_online_time'          => 'int',
        'receive_agent_id'          => 'varchar',
        'receive_level_id'          => 'int',
        'receive_tag_id'            => 'int',
        'create_time'               => 'int',
        'update_time'               => 'int',
        'task_mode'                 => 'tinyint',
        'activity'                  => 'int',
        'stage'                     => 'tinyint',
        'give_savings'              => 'tinyint',
        'stage_id'                  => 'int',
        'w_id'                      => 'int',
        'w_count'                   => 'int',
        'invite_people'             => 'int',
        'start_time'                => 'int',
        'end_time'                  => 'int',
        'status'                    => 'tinyint',
        'water_type'                => 'tinyint',
        'water_type_ids'            => 'varchar',
        'wallet_water_type'         => 'tinyint',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'background_img'=>lang('背景图'),
            'type'=>lang('类型'),
            'task_condition'=>lang('任务条件'),
            'min_bet'=>lang('最低下注'),
            'min_recharge'=>lang('最低充值'),
            'multiple'=>lang('最低倍数'),
            'task_num'=>lang('任务数量'),
            'reward_type'=>lang('奖励类型'),
            'amount_or_integral'=>lang('奖励金额或积分'),
            'sort'=>lang('排序'),
            'savings'=>lang('流水倍数'),
            'cumulative_recharge'=>lang('累计充值'),
            'online_time'=>lang('在线时长'),
            'cumulative_bet'=>lang('累计下注'),
            'game_online_time'=>lang('游戏时长'),
            'receive_level_id'=>lang('领取等级id'),
            'receive_tag_id'=>lang('领取标签id'),
            'give_savings'=>lang('领取金额流水倍数'),
            'w_id'=>lang('奖励五福id'),
            'w_count'=>lang('奖励五福次数'),
            'invite_people'=>lang('邀请人数'),
            'start_time'=>lang('自定义开始时间'),
            'end_time'=>lang('自定义结束时间'),
            'status'=>lang('状态'),
            'water_type'=>lang('流水类型'),
            'water_type_ids'=>lang('流水类型对应id字符串'),
            'water_type_ids'=>lang('指定流水钱包类型（0真金1彩金）'),
        ];
        $set_key = [
            ['type'=>[-1=>lang('所有'), 0=>lang('每日任务'), 1=>lang('每周任务')],],
            ['task_type'=>[0=>lang('最低下注'), 1=>lang('最低充值'), 2=>lang('最低倍数'), 3=>lang('累计充值'), 4=>lang('在线时长'), 5=>lang('累计下注'), 6=>lang('游戏时长'), 7=>lang('邀请人数'), 8=>lang('APP下载'), 9=>lang('添加银行卡')]],
            ['reward_type'=>[0=>lang('当前货币余额'), 1=>lang('积分'), 2=>lang('五福次数')],],
            ['status'=>[0=>lang('关闭'), 1=>lang('开启')],],
            ['status'=>[0=>lang('关闭'), 1=>lang('开启')],],
            ['water_type'=>[0=>lang('所有'), 1 => lang('指定平台'), 2 => lang('指定游戏')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}