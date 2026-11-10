<?php

namespace app\common\model;

use think\Model;

class PddTurntableModel extends Model {

    protected $name = 'pdd_turntable';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    //下级注册赠送
    const CHILD_REGISTER_COUNT_TYPE = 0;

    //下级充值赠送
    const CHILD_RECHARGE_COUNT_TYPE = 1;

    //充值赠送
    const RECHARGE_COUNT_TYPE = 2;
    public static function getCountTypeArr(){
        return [
            self::CHILD_REGISTER_COUNT_TYPE,#下级注册赠送
            self::CHILD_RECHARGE_COUNT_TYPE,#下级充值赠送
            self::RECHARGE_COUNT_TYPE,#充值赠送
        ];
    }



    protected $scene = [
        'id'                                => 'int',
        'lang'                              => 'varchar',
        'init_amount'                       => 'varchar',
        'amount'                            => 'decimal',
        'start_give_count'                  => 'int',
        'savings'                           => 'int',
        'min_invite_people'                 => 'int',
        'title'                             => 'varchar',
        'introduce'                         => 'text',
        'time'                              => 'int',
        'odds_of_winning'                   => 'int',
        'minimum_amount'                    => 'decimal',
        'people_num'                        => 'int',
        'count_type'                        => 'tinyint',
        'amount_range'                      => 'varchar',
        'amount_odds'                       => 'tinyint',
        'status'                            => 'int',
        'create_time'                       => 'int',
        'update_time'                       => 'int',
        'agent_id'                          => 'varchar',
        'start_time'                        => 'int',
        'ent_time'                          => 'int',
        'register_day_show'                 => 'int',
        'give_savings'                      => 'int',
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
            'lang'=>lang('语言'),
            'init_amount'=>lang('初始赠送金额，范围用-隔开'),
            'amount'=>lang('赠送金额'),
            'start_give_count'=>lang('开始赠送次数'),
            'savings'=>lang('流水倍数'),
            'min_invite_people'=>lang('最低邀请人数'),
            'title'=>lang('标题'),
            'introduce'=>lang('规则'),
            'time'=>lang('时间限制（单位：小时）'),
            'odds_of_winning'=>lang('中将几率（单位：%）'),
            'minimum_amount'=>lang('最低金额赠送次数'),
            'people_num'=>lang('每人参与次数'),
            'count_type'=>lang('赠送类型（0下级注册赠送，1下级充值赠送，2充值赠送）'),
            'amount_range'=>lang('赠送余额（范围用_隔开，例：10_20）'),
            'amount_odds'=>lang('赠送余额几率'),
            'status'=>lang('状态（1开启，0关闭）'),
            'agent_id'=>lang('代理线'),
            'start_time'=>lang('开始时间'),
            'end_time'=>lang('结束时间'),
            'give_savings'=>lang('彩金流水倍数'),
        ];
        $set_key = [
            ['count_type'=>[0=>lang('下级注册赠送'), 1=>lang('下级充值赠送'), 2=>lang('充值赠送'),],],
            ['status'=>[0=>lang('关闭'), 1=>lang('开启')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}