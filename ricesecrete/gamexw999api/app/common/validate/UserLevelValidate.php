<?php
namespace app\common\validate;
use think\Validate;
class UserLevelValidate extends Validate
{
    protected $rule = [
        'level_name'  =>  'require',
        'recharge_amount' =>  'require',
        'transaction_amount' =>  'require',
        'min_amount' =>  'require',
        'max_amount' =>  'require',
        'day_limit' =>  'require',
        'level_id' =>  'require|gt:0',
        'reward_type' =>  'require|in:1,2',
    ];

    protected $message  =   [
        'level_name.require' => '会员名称必须',
        'recharge_amount.require'     => '累积充值金额必须',
        'transaction_amount.in'     => '累积交易金额错误',
        'min_amount.require'   => '最低出款金额必须',
        'max_amount.require'  => '每日出款限制必须',
        'day_limit.require'        => '状态必须',
        'level_id'        => '缺少重要参数',
        'reward_type'        => '缺少重要参数',
    ];

    protected $scene = [
        'edit' => ['level_name', 'recharge_amount', 'transaction_amount', 'min_amount', 'max_amount', 'day_limit'],
        'receive' => ['level_id', 'reward_type']
    ];

}