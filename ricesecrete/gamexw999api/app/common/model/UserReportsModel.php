<?php
namespace app\common\model;
use think\facade\Session;
use think\Model;
class UserReportsModel extends Model
{
    protected $name = 'user_reports';
    protected $schema = [
        'id'          => 'int',
        'uid'          => 'int',
        'username'  =>  'varchar',
        'phone' =>  'varchar',
        'mail' =>  'varchar',
        'lebal' =>  'tinyint',
        'reg_time' =>  'int',
        'first_deposit_time' =>  'int',
        'recent_withdraw_money_time' =>  'int',
        'recent_deposit_money_time' =>  'int',
        'login_time' =>  'int',
        'deposit_nums' =>  'decimal',
        'deposit_total' =>  'decimal',
        'withdrawal_nums' =>  'int',
        'withdrawal_total' =>  'decimal',
        'total_gift' => 'decimal',
        'action_discount' => 'decimal',
        'lottery_total' => 'decimal',
        'rebate_total' => 'decimal',
        'rebate1_total' => 'decimal',
        'promotion_bonus' => 'decimal',
        'mission_rewards' => 'decimal',
        'vip_bonus' => 'decimal',
        'phone_status' => 'tinyint',
        'status' => 'tinyint',
        'level' => 'varchar',
        'rec_id' => 'int',
        'reg_ip' => 'varchar',
        'balance' => 'decimal',
        'televised' => 'int',
        'televised_status' => 'tinyint',
        'sms_status' => 'tinyint',
        'intention_status' => 'tinyint',
        'remark' => 'varchar',
        'admin_group' => 'varchar',
        'admin_name' => 'varchar',
        'admin_id' => 'int',
        'sms_time' => 'int',
        'feedback' => 'varchar',
        'role_id' => 'int',
        'is_connected' => 'tinyint',
        'assign_time' => 'int',
        'is_distribution' => 'tinyint',
        'follow_id' => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'uid'=>lang('用户id'),
            'televised_status'=>lang('电访状态'),
            'sms_status'=>lang('短信状态'),
            'intention_status'=>lang('客户意向状态'),
            'remark'=>lang('备注'),
            'admin_group'=>lang('电访组别'),
            'admin_name'=>lang('电访人员'),
            'admin_id'=>lang('电访人员id'),
            'role_id'=>lang('电访组别id'),
            'feedback'=>lang('反馈内容'),
            'is_connected'=>lang('电访状态'),
            'assign_time'=>lang('派发时间'),
            'is_distribution'=>lang('是否要领取赠金'),
        ];
        $set_key = [
            ['televised_status'=>[0=>lang('未安排'),1=>lang('待电访'),2=>lang('已电访'),3=>lang('电访2次以上')]],
            ['sms_status'=>[0=>lang('未发送'),1=>lang('已发送')]],
            ['is_connected'=>[0=>lang('未安排'),1=>lang('未接通'),2=>lang('已接通')]],
            ['intention_status'=>[0=>lang('低'),1=>lang('中'),2=>lang('高')]],
            ['is_distribution'=>[0=>lang('否'),1=>lang('是')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

}