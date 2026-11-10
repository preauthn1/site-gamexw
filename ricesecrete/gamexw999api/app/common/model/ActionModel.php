<?php
namespace app\common\model;
use think\Model;
use think\facade\Db;
class ActionModel extends Model
{
    protected $name = 'action';
    protected $schema = [
        'id'          => 'int',
        'add_time' =>  'bigint',
        'base_type' =>  'int',
        'base_name' =>  'varchar',
        'base_show_level' =>  'varchar',
        'base_apply_number_max' =>  'int',
        'base_apply_dr_max' =>  'int',
        'base_y_apply_dr' =>  'int',
        'base_ty_ts' =>  'varchar',
        'zg_level' =>  'varchar',
        'zg_cz_type' =>  'varchar',
        't_apply_start_time' =>  'bigint',
        't_apply_end_time' =>  'bigint',
        't_show_start_time' =>  'bigint',
        't_show_end_time' =>  'bigint',
        'jl_type' =>  'int',
        'jl_ck_or_level_s' =>  'varchar',
        'jl_ck_or_level_e' =>  'varchar',
        'jump_url' =>  'varchar',
        'jl_bl_price' =>  'float',
        'c_label' =>  'varchar',
        'c_img' =>  'text',
        'c_detail' =>  'text',
        'is_banner' =>  'int',
        'show_local' =>  'int',
        'dis_code' =>  'int',
        'reg_json' =>  'text',
        'auto_review' =>  'tinyint',
        'water' => 'int',
        'is_hot' => 'tinyint',
        'sort' => 'int',
        'agent_id' => 'varchar',
        'tag_id' => 'int',
        'auto_apply' => 'int',
        'type_id' => 'tinyint',
        'user_type' => 'tinyint',
        'mutually_exclusive_id' => 'int',
        'everyday_person_num' => 'int',
        'previous_action_id' => 'int',
        'date_type' => 'tinyint',
        'is_consist' => 'tinyint',
        'game_type' => 'text',
        'level_json' => 'text',
        'cumulative_days' => 'int',
        'day_recharge_amount' => 'int',
        'month_activity'    => 'text',
        'water_type'    => 'tinyint',
        'water_type_ids'    => 'varchar',
        'wallet_water_type'    => 'tinyint',
        'rebate_type' => 'tinyint',
        'is_first_tomorrow_join' => 'tinyint',
        'savings_is_include_fee' => 'tinyint',
        'reward_max' => 'decimal',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'base_type'=>lang('优惠类型'),
            'base_apply_number_max'=>lang('申请上限'),
            'base_apply_dr_max'=>lang('个人上限'),
            'base_y_apply_dr'=>lang('已申请人数'),
            't_apply_start_end_time'=>lang('申请开始/结束时间'),
            't_show_start_end_time'=>lang('显示开始/结束时间'),
            'zg_cz_type'=>lang('充值要求'),
            'auto_review'=>lang('是否自动审核'),
            'ps_status'=>lang('状态'),
            'is_hot'=>lang('热门'),
            'agent_id'=>lang('代理ID'),
            'tag_id'=>lang('用户标签'),
            'auto_apply'=>lang('自动申请'),
            'mutually_exclusive_id'=>lang('互斥活动ID'),
            'date_type'=>lang('日期类型'),
            'is_consist'=>lang('是否在内'),
            'rebate_type'=>lang('返利比例类型（游戏救援金用到，0等级区间，1金额区间）'),
            'reward_max'=>lang('奖励上限（救援金用到）'),
            'savings_is_include_fee'=>lang('流水是否包含本金'),
            'water_type'=>lang('流水类型（0所有，1指定平台，2指定游戏）'),
            'water_type_ids'=>lang('流水类型对应id字符串'),
            'wallet_water_type'=>lang('指定流水钱包类型（0真金1彩金）'),
        ];
        $set_key = [
            ['base_type'=>[1=>lang('注册首存'),2=>lang('再存优惠'),3=>lang('一般优惠'),4=>lang('体验金'),6=>lang('游戏救援金')]],
            ['zg_cz_type'=>['all'=>lang('不限'),1=>lang('充值限制')]],
            ['auto_review'=>['0'=>lang('否'),1=>lang('是')]],
            ['ps_status'=>['0'=>lang('未派送'),1=>lang('已派送'),3=>lang('已拒绝')]],
            ['is_hot'=>['0'=>lang('否'),1=>lang('是')]],
            ['date_type'=>[1=>lang('昨日'),2=>lang('上周')]],
            ['is_consist'=>[1=>lang('是'),2=>lang('否')]],
            ['rebate_type'=>[0=>lang('等级区间'),1=>lang('金额区间')]],
            ['savings_is_include_fee'=>[0=>lang('不包含'),1=>lang('包含')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    //关联活动内容
    public function news(){
        return $this->hasMany(NewsModel::class, 'action_id', 'id');
    }
}