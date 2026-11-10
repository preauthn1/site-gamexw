<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/6/9
 * Time: 14:50
 */
namespace app\common\model;
use think\Model;
class AgentSettingModel extends Model
{
    protected $name = 'agent_setting';
    protected $schema = [
        'id'          => 'int',
        'stratum'  =>  'int',
        'name'  =>  'varchar',
        'icon'  =>  'varchar',
        'stratum_number'  =>  'int',
        'stratum_bonus'  =>  'decimal',
        'is_check'      =>  'tinyint',
        'receive_agent_type'  =>  'tinyint',
        'agent_id'  =>  'varchar',
        'savings'  =>  'tinyint',
        'reward_type'  =>  'tinyint',
        'start_time'  =>  'int',
        'agent_type'  =>  'tinyint',
    ];

    const REWARD_TYPE_TOTAL = 0;#总数奖励
    const REWARD_TYPE_ONE = 1;#单词奖励

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'stratum'=>lang('阶层'),
            'stratum_number'=>lang('累积推广人数'),
            'stratum_bonus'=>lang('领取奖金'),
            'sub_recharge_amount'=>lang('下级充值有效总额'),
            'sub_water_amount'=>lang('下级效流水总额'),
            'name'=>lang('名称'),
            'icon'=>lang('图标'),
            'is_check'=>lang('是否需要审核'),
        ];
        $set_key = [
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }


}