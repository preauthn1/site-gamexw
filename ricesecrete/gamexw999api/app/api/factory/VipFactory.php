<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\model\UserLevelModel;
use app\common\model\UserLevelRewardModel;
use app\common\model\UserModel;
use app\common\service\MessageService;
use think\facade\Request;

class VipFactory{

    public function __construct($uid = 0){
        $this->param=Request::param();
        $this->uid = $uid;
    }

    //推送活动完成情况给用户
    public function pushMessageToFinishTaskUser($uid = 0){
        $apiRedis=new ApiRedis();
        //获取用户数据
        $userDetail = UserModel::where("id", $uid)->find();
        if( !$userDetail ){
            return false;
        }
        //获取等级数据
        $levelDetail = UserLevelModel::where('id', '=', $userDetail['level_id'])->find();
        if( $levelDetail ){
            //周礼金
            $pushUserKey = 'push_user_finish_vip_week_key_'.$uid.'_'.$levelDetail['id'];
            if( $apiRedis->has($pushUserKey) ){
                return false;
            }
            $rewardType = UserLevelRewardModel::WEEK_REWARD_TYPE;
            $amount = $levelDetail->week_reward_amount;
            if( $amount > 0 ){
                //判断本次奖励是否领取
                if( !(new UserFactory($this->uid))->judgeUserIsReceiveLevel($uid, $levelDetail, $rewardType) ){
                    //完成任务且没有领取给用户发送没有领取通知
                    MessageService::sendTaskFinishMessageNotify( $uid, 'vip_week');
                    //设置缓存两小时内不再推送
                    $apiRedis->set($pushUserKey, 1, 3600 * 2);
                }
            }
            //月礼金
            $pushUserKey = 'push_user_finish_vip_month_key_'.$uid.'_'.$levelDetail['id'];
            if( $apiRedis->has($pushUserKey) ){
                return false;
            }
            $rewardType = UserLevelRewardModel::MONTH_REWARD_TYPE;
            $amount = $levelDetail->month_reward_amount;
            if( $amount > 0 ){
                //判断本次奖励是否领取
                if( (new UserFactory($this->uid))->judgeUserIsReceiveLevel($this->uid, $levelDetail, $rewardType) ){
                    //完成任务且没有领取给用户发送没有领取通知
                    MessageService::sendTaskFinishMessageNotify( $uid, 'vip_month');
                    //设置缓存两小时内不再推送
                    $apiRedis->set($pushUserKey, 1, 3600 * 2);

                }
            }
        }
        return true;
    }
}