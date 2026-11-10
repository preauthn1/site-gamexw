<?php

namespace app\common\model;

use think\Model;

class LotteryOnlineTaskModel extends Model{

    protected $name = 'lottery_online_task';

    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';


    const DAY = 0;#按天
    const WEEK = 1;#按周
    const MONTH = 2;#按月

    public static function getTypeArr(){
        return [
            self::DAY, //天
            self::WEEK, //周
            self::MONTH, //月
        ];
    }



    protected $scene = [
        'id'                        => 'int',
        'type'                      => 'tinyint',
        'lang'                      => 'varchar',
        'time'                      => 'int',
        'lottery_num'               => 'int',
        'level_id'                  => 'int',
        'tag_id'                    => 'int',
        'agent_id'                  => 'int',
        'join_num'                  => 'tinyint',
        'status'                    => 'tinyint',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'lang'=>lang('语言'),
            'type'=>lang('类型（0每天，1每周，2每月）'),
            'time'=>lang('时长（单位：小时）'),
            'lottery_num'=>lang('赠送抽奖次数'),
            'level_id'=>lang('参与vip等级限制'),
            'tag_id'=>lang('参与标签限制'),
            'agent_id'=>lang('参与代理线限制'),
            'join_num'=>lang('可参与数量'),
            'status'=>lang('状态'),
            'create_time'=>lang('创建时间'),
            'update_time'=>lang('修改时间'),
        ];
        $set_key = [
            ['type'=>[0=>lang('每天'), 1=>lang('每周'), 2=>lang('每月')],],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}