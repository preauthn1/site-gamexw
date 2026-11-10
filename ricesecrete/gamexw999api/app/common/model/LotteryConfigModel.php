<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/6/9
 * Time: 14:50
 */
namespace app\common\model;
use think\Model;
class LotteryConfigModel extends Model
{
    protected $name = 'lottery_config';
    protected $schema = [
        'id'          => 'int',
        'rule'  =>  'varchar',
        'starttime'  =>  'int',
        'endtime' => 'int',
        'status' => 'tinyint',
        'free_count' => 'tinyint',
        'required_balance' => 'decimal',
        'limit_count' => 'int',
        'img' => 'varchar',
        'uprice' => 'decimal',
        'integral' => 'int',
        'required_integral' => 'int',
        'free_time' => 'int',
        'min_price' => 'decimal',
        'title' => 'varchar',
        'max_award' => 'decimal',
        'theme_type' => 'varchar',
        'lang' => 'varchar',
        'task_condition' => 'varchar',
        'task_num' => 'int',
        'min_bet' => 'decimal',
        'min_recharge' => 'decimal',
        'multiple' => 'decimal',
        'cumulative_bet' => 'decimal',
        'cumulative_recharge' => 'decimal',
        'invite_people' => 'int',
        'reward_free_count' => 'int',
        'condition_finish_count' => 'int',
        'agent_id' => 'varchar',
        'update_time' => 'int',
        'create_time' => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'rule'=>lang('规则'),
            'starttime'=>lang('抽奖开始时间'),
            'endtime'=>lang('抽奖结束时间'),
            'status'=>lang('活动状态'),
            'free_count'=>lang('用户免费次数'),
            'required_balance'=>lang('消耗金额'),
            'limit_count'=>lang('用户每天抽奖次数'),
            'img'=>lang('活动图片'),
            'uprice'=>lang('充值usdt金额'),
            'integral'=>lang('赠送积分'),
            'required_integral'=>lang('消耗积分'),
            'min_price' => lang('抽奖活动最低充值金额'),
            'title' => lang('标题'),
            'max_award' => lang('最高奖励'),
            'theme_type' => lang('主题类型'),
            'lang' => lang('语言'),
            'task_condition' => lang('任务条件多选'),
            'task_num' => lang('任务数量'),
            'min_bet' => lang('最低下注'),
            'min_recharge' => lang('最低充值'),
            'multiple' => lang('倍数'),
            'cumulative_bet' => lang('累计下注'),
            'cumulative_recharge' => lang('累计充值'),
            'invite_people' => lang('邀请有效用户数量'),
            'condition_finish_count' => lang('条件奖励可完成次数（-1不限制）'),
            'reward_free_count' => lang('条件奖励的免费次数'),
            'agent_id' => lang('代理线id'),
        ];
        $set_key = [
            ['status'=>[0=>lang('关闭'),1=>lang('启用')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }
}