<?php

namespace app\common\service;

use app\api\factory\BotFactory;
use app\api\factory\ReceiveCodeFactory;
use app\api\factory\TaskFactory;
use app\api\factory\WuFuFactory;
use app\api\redis\ApiRedis;
use app\common\lib\EmailCode;
use app\common\lib\Util;
use app\common\model\AdvertisingModel;
use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\redis\CommomRedis;
use think\facade\Queue;

class UserOfflineService{

    //获取复充人数
    public static function getAgainRechargeAmount($where_day1, $redis_key = '', $where2 = []){
        $CommomRedis=new CommomRedis();
        if (!$redis_key || !$CommomRedis->has($redis_key)) {
            $total1 = UserOfflineModel::getRechargeUserCount($where_day1, $where2);
//            $againUidArr = UserOfflineModel::alias("a")->where([["a.status", 'in',"3,4"]])->where($where_day1)->group('a.uid')->column('a.uid');
            $condition = [];
//            $condition[] = ['a.uid', 'not in', $againUidArr];
            $condition[] = ["a.first", "=", 1];
            //查询没有复充过的首充人数
            $total2 = UserOfflineModel::getRechargeUserCount($where_day1, $condition, $where2);
            $total = $total1 - $total2;
            $CommomRedis->set($redis_key, $total, 600);
        }else{
            $total = $CommomRedis->get($redis_key);
        }
        return $total;
    }

    //获取复充人数-K59规则
    public static function getAgainRechargeAmount2($where_day1, $redis_key = '', $where2 = []){
        $CommomRedis=new CommomRedis();
        if (!$redis_key || !$CommomRedis->has($redis_key)) {
            $condition = [];
            //查询没有复充过的首充人数
            $total = UserOfflineModel::getRechargeUserCountHaving('count(a.id) >= 2',$where_day1, $condition, $where2);
            $CommomRedis->set($redis_key, $total, 600);
        }else{
            $total = $CommomRedis->get($redis_key);
        }
        return $total;
    }

    //充值成功回调
    public static function successCallback($recharge){
        //参与活动
        if( isset($recharge['not_auto_action']) ){
            if( $recharge['not_auto_action'] == 0 ){
                //自动参与活动
                action_task($recharge['uid'], [1,2,7], $recharge['id'], 5);
            }elseif ( $recharge['not_auto_action'] == 1 ){
                //不参与活动，但是推荐活动必须参与
                action_task($recharge['uid'], [7]);
            }elseif( $recharge['not_auto_action'] > 1 ){
                //强制参与的活动
                action_task($recharge['uid'], [1,2,7], $recharge['id'], 3, $recharge['not_auto_action']);
                //自动参与的活动
                action_task($recharge['uid'], [1,2,7], $recharge['id'], 5);
            }
        }

        //检测用户VIP升级
//        Queue::push("User@upgrade", ['id' => $recharge['uid'], 'money' => 0], 'upgrade');

        $userDetail = UserModel::uinfo($recharge['uid']);

        //pdd增加抽奖次数
        #1、自己充值活动抽奖次数
        $sql = PddTurntableModel::where('count_type', '=', PddTurntableModel::RECHARGE_COUNT_TYPE)->field('id')->buildSql();
        $condition = [];
        $condition[] = ['uid', '=', $recharge['uid']];
        $condition[] = ['invite_uid', '=', $recharge['uid']];
        $condition[] = ['expire_time', '>=', time()];
        PddInviteRecordModel::where($condition)->where('turntable_id in'.$sql)->inc('turntable_num', 1)->update();

        //下级用户是否赠送过抽奖次数
        $condition = [];
        $condition[] = ['invite_uid', '=', $recharge['uid']];
        $condition[] = ['uid', '<>', $recharge['uid']];
        $condition[] = ['expire_time', '>=', time()];
        $inviteRecord = PddInviteRecordModel::where($condition)->find();
        if(!empty($inviteRecord)&&intval($inviteRecord['turntable_num']) == 0){
            //如果充值条件满足 并且没有领取过抽奖次数
            #2、下级充值上级获得抽奖次数
            $sql = PddTurntableModel::where('count_type', 'in', [PddTurntableModel::RECHARGE_COUNT_TYPE, PddTurntableModel::CHILD_RECHARGE_COUNT_TYPE])->field('id')->buildSql();
            $condition = [];
            $condition[] = ['invite_uid', '=', $recharge['uid']];
            $condition[] = ['uid', '<>', $recharge['uid']];
            $condition[] = ['expire_time', '>=', time()];
            PddInviteRecordModel::where($condition)->where('turntable_id in'.$sql)->inc('turntable_num', 1)->update();
        }
        //1表示首次充值  2表示再次充值
        //只有首次充值增加抽奖次数
//        if($recharge['first'] == 1){
//            //完成首次充值赠送五福刮奖机会
//            (new WuFuFactory())->giveCardFirstRecharge($recharge['uid']);
//        }

        //邀请好友达到有效充值赠送刮奖机会
        $apiRedis = new ApiRedis();
        $sys = $apiRedis->systemInfo();
        if( $recharge['arrival_price'] >= $sys['tg_condition'] ){
            //获取该充值用户的上级
            $rec_id = UserModel::where('id', '=', $recharge['uid'])->value('rec_id') ?: 0;
            (new WuFuFactory())->giveCardInvite($recharge['uid'], $rec_id);
        }

        //获取广告信息
        $adv_info = AdvertisingModel::getAdvInfo($recharge['first'],$recharge['uid']);
        if(!empty($adv_info)){
            MessageService::sendAdvertisingMsg($recharge['uid'],$adv_info, 'advertising', env('message_reset_time', 0));
        }

        //任务完成推送
        (new TaskFactory($recharge['uid']))->pushMessageToFinishTaskUser($recharge['uid']);
        //邀请任务推送
        if( $userDetail->rec_id ){
            (new TaskFactory($userDetail->rec_id))->pushMessageToFinishTaskUser($userDetail->rec_id);
        }
        //领取码自动领取
        (new ReceiveCodeFactory())->userReceiveCode($recharge['uid']);

        Util::email_notify('off', $recharge['uid']);

    }

}