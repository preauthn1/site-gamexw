<?php

namespace app\common\service;

use app\api\factory\UserFactory;
use app\api\redis\ApiRedis;
use app\common\model\GameBetsModel;
use app\common\model\LotteryConditionRecordModel;
use app\common\model\LotteryRecordModel;
use app\common\model\TaskModel;
use app\common\model\UserBankModel;
use app\common\model\UserLoginModel;
use app\common\model\UserOfflineModel;

//转盘
class LotteryService{

    //根据转盘数据返回用户完成任务的进度
    public static function getUserTaskDetailRate($lotteryDetail, $uid = 0, $isDatabase = false){
        if(!$uid || !$lotteryDetail)return 0;
        $apiRedis=new ApiRedis();
        $key = 'user_finish_lottery_rate_'.$uid.'_'.$lotteryDetail->id;
        if( $apiRedis->has($key) && !$isDatabase ){
//            return $apiRedis->get($key);
        }
        $startTime = strtotime($lotteryDetail['starttime']);
        $endTime = strtotime($lotteryDetail['endtime']);
        //获取任务条件数组
        $taskCondition = explode(',', $lotteryDetail->task_condition);
        //任务完成进度
        $taskFinishNumRate = [];
        foreach ($taskCondition as $v){
            $taskFinishNumRate[] = self::getLotteryConditionDetail($v, $lotteryDetail, $uid, $startTime, $endTime);
        }
        //设置缓存五分钟
        $expireTime = 300;
        $laveTime = $endTime - time();
        if( $laveTime < $expireTime ){
            //如果十二点距离当前时间不足5分钟，设置为剩下时间
            $expireTime = $laveTime;
        }
        $apiRedis->set($key,$taskFinishNumRate, $expireTime);
        return $taskFinishNumRate;
    }



    //获取剩余次数
    public static function getTimes($lotteryDetail){
        $time = $lotteryDetail['free_count'];
        if($time<0){
            $time = 0;
        }
        $balance_time = 0;
        if($lotteryDetail['required_balance'] > 0){
            $balance_time = intval($lotteryDetail['balance']/$lotteryDetail['required_balance']);
        }
        $integral_time = 0;
        if($lotteryDetail['required_integral'] > 0){
            $integral_time = intval($lotteryDetail['integral']/$lotteryDetail['required_integral']);
        }
        $min_time = max(min($balance_time,$integral_time), 0);
        return $time+$min_time;
    }


    //根据类型获取条件完成情况
    public static function getLotteryConditionDetail($type, $lotteryDetail, $uid, $startTime = 0,$endTime = 99999999999){
        $data = [];
        $condition = [];
        switch ($type){
            case TaskModel::MIN_BET_TASK_CONDITION:
                //最低下注
                $condition[] = ['gb_price', '>=', $lotteryDetail->min_bet];
                $condition[] = ['gb_uid', '=', $uid];
                $condition[] = ['gb_winlose', '<>', 0];#不是无效就可以
                $betWhere = BetService::getGbidByTime($startTime, $endTime)['sql'];
                $complete = GameBetsModel::where($condition)->where($betWhere)->value('gb_price');
                $data = [
                    'name'=>'最低下注',
                    'type'=>$type,//类型
                    'condition'=>floatval($lotteryDetail->min_bet),//完成条件
                    'complete'=>$complete //已完成数量
                ];
                break;
            case TaskModel::MIN_RECHARGE_TASK_CONDITION:
                //最低充值
                $condition[] = ['arrival_price', '>=', $lotteryDetail->min_recharge];
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['status', 'in', [3, 4]];#充值通过的状态
                $condition[] = ['add_time', 'between', [$startTime, $endTime]];
                $complete = UserOfflineModel::where($condition)->value('arrival_price');
                $data = [
                    'name'=>'最低充值',
                    'type'=>$type,//类型
                    'condition'=>floatval($lotteryDetail->min_recharge),//完成条件
                    'complete'=>$complete //已完成数量
                ];
                break;
            case TaskModel::MIN_MULTIPLE_TASK_CONDITION:
                //最低倍数
                $condition1 = 'gb_uid = '.$uid.' and gb_winprice > gb_price and (gb_winprice-gb_price)/gb_price > '.$lotteryDetail->multiple;
                $condition[] = ['gb_winlose', '=', 3];#赢的状态
                $betWhere = BetService::getGbidByTime($startTime, $endTime)['sql'];
                $complete = GameBetsModel::where($condition)->where($betWhere)->where($condition1)->count();
                $data = [
                    'name'=>'最低倍数',
                    'type'=>$type,//类型
                    'condition'=>1,//最低倍数为1
                    'complete'=>$complete //已完成数量
                ];
                break;
            case TaskModel::CUMULATIVE_RECHARGE_TASK_CONDITION:
                //累计充值
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['status', 'in', [3, 4]];#充值通过的状态
                $condition[] = ['add_time', 'between', [$startTime, $endTime]];
                $complete = UserOfflineModel::where($condition)->sum('arrival_price');
                $data = [
                    'name'=>'累计充值',
                    'type'=>$type,//类型
                    'condition'=>floatval($lotteryDetail->cumulative_recharge),//完成条件
                    'complete'=>$complete //已完成数量
                ];
                break;
//                case TaskModel::ONLINE_TIME_TASK_CONDITION:
//                    //在线时长
//                    $condition[] = ['uid', '=', $uid];
//                    $condition[] = ['datetime', 'between', [$startTime, $endTime]];
//                    $condition[] = ['type', '=', 'online_time'];
//                    $onlineTime = UserLogModel::where($condition)->sum('data');
//                    $taskFinishNumArr[] = ($onlineTime / 60) > $lotteryDetail->online_time ? 1 : 0;
//                    break;
            case TaskModel::CUMULATIVE_BET_TASK_CONDITION:
                //累计下注
                $condition[] = ['gb_uid', '=', $uid];
//                    $condition[] = ['gb_bettime', 'between', [$startTime, $endTime]];
                $betWhere = BetService::getGbidByTime($startTime, $endTime)['sql'];
                $complete = GameBetsModel::where($condition)->where($betWhere)->sum('gb_price');
                $data = [
                    'name'=>'累计下注',
                    'type'=>$type,//类型
                    'condition'=>floatval($lotteryDetail->cumulative_bet),//完成条件
                    'complete'=>$complete //已完成数量
                ];
                break;
//                case TaskModel::GAME_ONLINE_TIME_TASK_CONDITION:
//                    //游戏时长
//                    $condition[] = ['uid', '=', $uid];
//                    $condition[] = ['datetime', 'between', [$startTime, $endTime]];
//                    $condition[] = ['type', '=', 'game_time'];
//                    $onlineTime = UserLogModel::where($condition)->sum('data');
//                    $taskFinishNumArr[] = ($onlineTime / 60) > $lotteryDetail->game_online_time ? 1 : 0;
//                    break;
            case TaskModel::INVITE_PEOPLE_TASK_CONDITION:
                //邀请人数
                $complete = UserFactory::getUserInvitePerson($uid, $startTime, $endTime);
                $data = [
                    'name'=>'邀请人数',
                    'type'=>$type,//类型
                    'condition'=>floatval($lotteryDetail->invite_people),//完成条件
                    'complete'=>$complete //已完成数量
                ];
                break;
            case TaskModel::APP_LOGIN_TASK_CONDITION:
                //APP登录
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['login_time', 'between', [$startTime, $endTime]];
                $condition[] = ['device_type', 'in', ['IPHONE', 'IPHONE', 'ANDROID']];
                $complete = UserLoginModel::where($condition)->count();
                $data = [
                    'name'=>'APP登录',
                    'type'=>$type,//类型
                    'condition'=>1,//完成条件
                    'complete'=>$complete //已完成数量
                ];
                break;
            case TaskModel::ADD_BANK_TASK_CONDITION:
                //添加银行卡
                $condition[] = ['uid', '=', $uid];
                $complete = UserBankModel::where($condition)->count();
                $data = [
                    'name'=>'添加银行卡',
                    'type'=>$type,//类型
                    'condition'=>1,//完成条件
                    'complete'=>$complete //已完成数量
                ];
                break;
        }
        return $data;
    }

    public static function getConditionStatus($statusArr, $lotteryDetail, $uid, $type){
        //任务10次，已完成5次，最低
        //判断是否完成或领取，如果可重复完成则根据领取次数来判断当前是否完成和领取
        #1、已领取的次数
        $receiveCount = self::getUserLotteryConditionReceiveCount($lotteryDetail, $uid, $type);
        //排除计算的条件(APP登录、添加银行卡)，这些条件都是永久条件
        $excludeCondition = [TaskModel::APP_LOGIN_TASK_CONDITION, TaskModel::ADD_BANK_TASK_CONDITION];
//        #2、先计算转盘中所有条件最低完成的次数，去最低次数来当作完成次数
//        $minCount = 0;
//        foreach ($statusArr as $v) {
//            $minCount = min($minCount, intval($v['complete'] / $v['condition']));
//        }
        #3、处理数组中各项数据、减去已完成次数对应的数值
        foreach ($statusArr as $k => $v){
            if( in_array($v['type'], $excludeCondition) ){
                continue;
            }
            $statusArr[$k]['complete'] = $v['complete'] - $receiveCount * $v['condition'];
            if($statusArr[$k]['complete'] < 0){
                $statusArr[$k]['complete'] = 0;
            }
        }
        #4、判断是否完成，有一个未完成都算未完成
        $status = 1;
        foreach ($statusArr as $v){
            if( $v['complete'] < $v['condition'] ){
                $status = 0;
                break;
            }
        }
        //完成数量等于已领取数量则为已领取
        $isReceive = $receiveCount == $lotteryDetail->condition_finish_count && $lotteryDetail->condition_finish_count != -1 ? 1 : 0;
        return ['status' => $status, 'data' => $statusArr, 'is_receive' => $isReceive];
    }

    //用户领取条件奖励次数
    public static function getUserLotteryConditionReceiveCount($lotteryDetail, $uid, $type, $isDatabase = false){
        $redisKey = 'user_get_lottery_condition_reward_'. $uid . '_'. $lotteryDetail->id . '_'. $type;
        $apiRedis=new ApiRedis();
        if( $apiRedis->has($redisKey) && !$isDatabase ){
//            return $apiRedis->get($redisKey);
        }
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['lottery_id', '=', $lotteryDetail->id];
        $condition[] = ['type', '=', $type];
        $count = LotteryConditionRecordModel::where($condition)->count();
        $apiRedis->set($redisKey, $count);
        return $count;

    }
}