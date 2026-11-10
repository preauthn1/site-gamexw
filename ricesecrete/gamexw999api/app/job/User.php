<?php

namespace app\job;
use app\common\model\UserModel;
use app\common\model\UserAgentModel;
use app\common\model\UserLevelModel;
use app\common\model\UserLevelDownModel;
use app\api\redis\ApiRedis;
use app\common\service\MessageService;
use think\facade\Queue;
use think\facade\Cache;
use think\queue\Job;

class User {

    public function fire(Job $job, $data) {
        
    }

    /**
     * 升级VIP 发放推广奖励
     */
    public function upgrade(Job $job, $data) {
        $uid = $data['id'];
        $money = $data['money'];
        $redis = Cache::store('redis');
        $redis_key = "user:upgrade:{$uid}";
        if ($redis->has($redis_key)) {
            $job->delete();
            return;
        }
        $redis->set($redis_key, 1, 10);
        
        $cache = new ApiRedis();
        $sys = $cache->systemInfo();
        
        $res = UserLevelModel::upgrade_level($uid, $money, $sys);
        
        /* 判断推广有效人数发放推广佣金 */
        // 推广奖金用户手动领取
//        $rec_id = UserModel::where(["id" => $uid])->value('rec_id');
//        $userAgent = UserAgentModel::where("uid", $rec_id)->where("status",3)->field("uid")->find();//要是代理且是通过
//        if(!empty($userAgent)){
//            UserModel::rec_person_add($rec_id, $sys['tg_condition']);
//        }
        // 升级VIP等级
        if (!empty($res) && $res['level_id'] > $res['old_level_id']) {
            MessageService::sendMsg("恭喜您，升级到新的VIP级别了", $uid, 'pop');
        }
        $this->output("用户[{$uid}]检查升级VIP--".json_encode($res));
       
        $job->delete(); //删除
    }

    
    /**
     * 降级VIP
     */
    public function downgrade(Job $job, $data) {
        $uid = $data['id'];
        $money = $data['money'];
        $redis = Cache::store('redis');
        $redis_key = "user:downgrade:{$uid}";
        if ($redis->has($redis_key)) {
            $job->delete();
            return;
        }
        $redis->set($redis_key, 1, 10);
        $cache = new ApiRedis();
        $sys = $cache->systemInfo();
        $res = UserLevelDownModel::downgrade_level($uid, $money, $sys);
        // 降级VIP等级
        if (!empty($res) && $res['level_id'] < $res['old_level_id']) {
            MessageService::sendMsg("降级到新的VIP级别了", $uid, 'pop');
        }
        $this->output("用户[{$uid}]检查降级VIP--".json_encode($res));
        $job->delete(); //删除
    }
    private function output($msg = '') {
        echo $msg . ";,时间:" . date("Y-m-d H:i:s") . "\n";
    }
}
