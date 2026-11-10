<?php
namespace app\common\validate;
use app\common\model\TaskModel;
use think\Validate;

class TaskValidate extends Validate
{
    protected $rule = [
        'background_img' => 'require',
        'task_type' => 'require',
        'type' => 'require|checkType',
        'task_condition'  =>  'require',
        'min_bet' =>  'checkBet|requireCallback:checkBetRequire',
        'min_recharge' =>  'checkRecharge|requireCallback:checkRechargeRequire',
        'multiple' =>  'checkMultiple|requireCallback:checkMultipleRequire',
        'cumulative_recharge' =>  'checkCumulativeRecharge|requireCallback:checkCumulativeRechargeRequire',
        'online_time' =>  'checkOnlineTime|requireCallback:checkOnlineTimeRequire',
        'cumulative_bet' =>  'checkCumulativeBet|requireCallback:checkCumulativeBetRequire',
        'game_online_time' =>  'checkGameOnlineTime|requireCallback:checkGameOnlineTimeRequire',
        'task_num' =>  'require|egt:0',
        'reward_type' =>  'require|checkRewardType',
//        'amount_or_integral' =>  'require|egt:0',
        'task_id' =>  'require|egt:0',
        'stage_id' =>  'require|egt:0',
        'stage'    => 'require|number|egt:1',
    ];

    protected $message  =   [
        'background_img.require' => '请上传任务背景图',
        'task_type.require' => '请选择任务分类',
        'type.require' => '请选择类型',
        'type.checkType' => '请选择正确的类型',
        'task_condition.require' => '请选择任务条件',
        'min_bet'     => '请输入最低下注金额',
        'min_recharge'     => '请输入最低充值金额',
        'multiple'     => '请输入最低倍数',
        'task_num.require'     => '请输入任务数量',
        'reward_type.require'     => '请选择奖励类型',
//        'amount_or_integral.require'     => '请输入奖励数值',
        'task_id'     => '缺少重要参数',
        'cumulative_recharge'     => '请输入累计充值金额',
        'online_time'     => '请输入在线时长',
        'cumulative_bet'     => '请输入累计下注',
        'game_online_time'     => '请输入游戏时长',
        'stage_id'     => '缺少重要参数',
        'stage'     => '缺少重要参数',
    ];

    //验证最低下注
    public function checkBet($value, $rule, $data){
        //任务类型为最低下注额时该参数必填且不小于等于零
        if( in_array(TaskModel::MIN_BET_TASK_CONDITION, $data['task_condition'])  && ($value < 0 || !is_numeric($value)) ){
            return false;
        }
        return true;
    }

    //验证最低下注（为空验证）
    public function checkBetRequire($value, $data){
        //任务类型为最低下注额时该参数必填且不小于等于零
        if( in_array(TaskModel::MIN_BET_TASK_CONDITION, $data['task_condition'])  && ($value < 0 || !is_numeric($value)) ){
            return true;
        }
        return false;
    }

    //验证最低充值
    public function checkRecharge($value, $rule, $data){
        //任务类型为最低充值时该参数必填且不小于等于零
        if( in_array(TaskModel::MIN_RECHARGE_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return false;
        }
        return true;
    }

    //验证最低充值（为空验证）
    public function checkRechargeRequire($value, $data){
        //任务类型为最低充值时该参数必填且不小于等于零
        if( in_array(TaskModel::MIN_RECHARGE_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return true;
        }
        return false;
    }

    //验证倍数
    public function checkMultiple($value, $rule, $data){
        //任务类型为最低倍数时该参数必填且不小于等于零
        if( in_array(TaskModel::MIN_MULTIPLE_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return false;
        }
        return true;
    }

    //验证倍数（为空验证）
    public function checkMultipleRequire($value, $data){
        //任务类型为最低倍数时该参数必填且不小于等于零
        if( in_array(TaskModel::MIN_MULTIPLE_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return true;
        }
        return false;
    }

    //验证累计充值
    public function checkCumulativeRecharge($value, $rule, $data){
        //任务类型为累计充值时该参数必填且不小于等于零
        if( in_array(TaskModel::CUMULATIVE_RECHARGE_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return false;
        }
        return true;
    }

    //验证累计充值（为空验证）
    public function checkCumulativeRechargeRequire($value, $data){
        //任务类型为游戏胜利、任务类型为累计充值时该参数必填且不小于等于零
        if( in_array(TaskModel::CUMULATIVE_RECHARGE_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return true;
        }
        return false;
    }

    //验证在线时长
    public function checkOnlineTime($value, $rule, $data){
        //任务类型为在线时长时该参数必填且不小于等于零
        if( in_array(TaskModel::ONLINE_TIME_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return false;
        }
        return true;
    }

    //验证在线时长（为空验证）
    public function checkOnlineTimeRequire($value, $data){
        //任务类型为在线时长时该参数必填且不小于等于零
        if( in_array(TaskModel::ONLINE_TIME_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return true;
        }
        return false;
    }

    //验证累计下注
    public function checkCumulativeBet($value, $rule, $data){
        //任务类型为累计下注时该参数必填且不小于等于零
        if( in_array(TaskModel::CUMULATIVE_BET_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return false;
        }
        return true;
    }

    //验证累计下注（为空验证）
    public function checkCumulativeBetRequire($value, $data){
        //任务类型为累计下注时该参数必填且不小于等于零
        if( in_array(TaskModel::CUMULATIVE_BET_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return true;
        }
        return false;
    }

    //验证游戏时长
    public function checkGameOnlineTime($value, $rule, $data){
        //任务类型为游戏时长时该参数必填且不小于等于零
        if( in_array(TaskModel::GAME_ONLINE_TIME_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return false;
        }
        return true;
    }

    //验证游戏时长（为空验证）
    public function checkGameOnlineTimeRequire($value, $data){
        //任务类型为游戏时长时该参数必填且不小于等于零
        if( in_array(TaskModel::GAME_ONLINE_TIME_TASK_CONDITION, $data['task_condition']) && ($value < 0 || !is_numeric($value)) ){
            return true;
        }
        return false;
    }

    //验证类型
    public function checkType($value, $rule, $data){
        if(in_array($value, TaskModel::getTypeArr())){
            return true;
        }
        return false;
    }

    //验证奖励类型
    public function checkRewardType($value, $rule, $data){
        if( in_array($value, TaskModel::getRewardTypeArr()) ){
            return true;
        }
        return false;
    }


    protected $scene = [
        'add'  =>  ['background_img', 'type', 'task_condition', 'min_bet', 'min_recharge', 'multiple', 'task_num', 'reward_type', 'amount_or_integral', 'cumulative_recharge', 'online_time', 'cumulative_bet', 'game_online_time'],
        'receive'  =>  ['task_id'],
        'stage_receive'  =>  ['stage_id','stage'],
    ];
}