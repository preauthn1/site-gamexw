<?php

namespace app\common\service;

use app\api\factory\ActionFactory;
use app\api\factory\AgentFactory;
use app\api\factory\PddFactory;
use app\api\factory\ReceiveCodeFactory;
use app\api\factory\SignFactory;
use app\api\factory\TaskFactory;
use app\api\factory\VipFactory;
use think\facade\Queue;

class TaskFinishNotifyService{

    //执行通知
    public static function handleNotify($uid){
        //执行用户领取码领取 TODO 自动领取，没有推送
        (new ReceiveCodeFactory($uid))->userReceiveCode($uid);

        //检测用户VIP升级
//        Queue::push("User@upgrade", ['id' => $uid, 'money' => 0], 'upgrade');

        //socket通知用户已完成的任务
        //任务通知
        (new TaskFactory($uid))->pushMessageToFinishTaskUser($uid);
        //签到通知
        (new SignFactory())->pushMessageToFinishTaskUser($uid);
        //活动
        (new ActionFactory($uid))->pushMessageToFinishTaskUser($uid);
        //PDD
        (new PddFactory($uid))->pushMessageToFinishTaskUser($uid);
        //邀请
        (new AgentFactory($uid))->pushMessageToFinishTaskUser($uid);
        //VIP周/月礼金
        (new VipFactory($uid))->pushMessageToFinishTaskUser($uid);
    }



}