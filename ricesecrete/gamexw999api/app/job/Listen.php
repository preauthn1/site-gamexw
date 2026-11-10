<?php
namespace app\job;
use app\api\redis\ApiRedis;
use app\common\model\UserModel;
use think\console\Output;
use think\facade\Queue;
use think\facade\Log;
use think\facade\Db;
use think\queue\Job;
class Listen {
    public function fire(Job $job, $data){

    }

    public function agent(Job $job, $data){
        $cache = new ApiRedis();
        if ($cache->has("deleteQueue:Agent")) {
            $job->delete();
            return;
        }
        $page = isset($data['page'])?intval($data['page']):0;
        $max_id = isset($data['max_id'])?$data['max_id']:0;
        $startuptime = 600;
        $sys = $cache->systemInfo();
        $agent_num = env("agent_num", 1000);
        $agent_mod = env("agent_mod", 1);
        $agent_page = env("agent_page", 50);
        $agent_settlement_time = intval($sys['agent_settlement_time']);
        if(isset($sys['agent_settlement_type']) && $sys['agent_settlement_type'] == 1){
            if(isset($sys['agent_settlement']) && $sys['agent_settlement'] != 0){
                $is_sell = false;
                if($sys['agent_settlement'] == 2){ // 星期制度
                    if($agent_settlement_time > 6) $agent_settlement_time = 0;
                    $week = date("w");
                    if($week == $agent_settlement_time){ //当前时间大于设定时间
                        $is_sell = true;
                    }
                    $start_time = mktime(0,0,0,date('m'),date('d')-date('w')+1-7,date('Y'));
                    $end_time = mktime(23,59,59,date('m'),date('d')-date('w')+7-7,date('Y'));
                }
                
                if($sys['agent_settlement'] == 3){ // 月度制度
                    if($agent_settlement_time > 30) $agent_settlement_time = 1;
                    $day = date("d");
                    if($day == $agent_settlement_time){ //当前时间大于设定时间
                        $is_sell = true;
                    }
                    $start_time = mktime(00, 00, 00, date('m', strtotime(date('Y-m')))-1, 01);
                    $end_time = mktime(23, 59, 59, date('m', strtotime(date('Y-m')))+1-1, 00);
                }
                
                if($sys['agent_settlement'] == 1){ // 隔天结算制度
                    $hour = date("H");
                    if($hour >= $agent_settlement_time){ //当前时间大于设定时间
                        $is_sell = true;
                    }
                    $start_time = mktime(0,0,0,date('m'),date('d')-1,date('Y'));
                    $end_time = mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
                }
                
                if($is_sell){
                    $where = [];
                    $where[] = ['id', '>', $max_id];
                    $where[] = ['is_rebate', '=', 0];
                    $where[] = ['start_time', '=', $start_time];
                    $where[] = ['end_time', '=', $end_time];

                    $this->output("总盈亏返佣，页码：[{$page}]，初始ID：[{$max_id}]");
                    $match = Db::name('agent_report')->where($where)->order('id asc')->limit(2000)->column('id');
                    if(!empty($match)){
                        $startuptime = 10;
                        foreach($match as $key => $val){
                            if ($cache->has("agent_settlement_report_{$val}")) {
                                continue;
                            }
                            $cache->set("agent_settlement_report_{$val}", 1, 86400);
                            $index = $val % $agent_mod;
                            if($index > 0){
                                Queue::push("app\job\Settlement@report", $val, 'agent'.$index);
                            }else{
                                Queue::push("app\job\Settlement@report", $val, 'agent');
                            }
                            $max_id = $val;
                        }

                        if($agent_page <= $page){
                            Queue::later(600, "app\job\Listen@agent", ['page' => 0], 'task');
                        }else{
                            Queue::push("app\job\Listen@agent", ['page' => $page+1, 'max_id' => $max_id], 'task');
                        }                       
                    }else{
                        Queue::later($startuptime, "app\job\Listen@agent", ['page' => 0], 'task');
                    }
                } else {
                    Queue::later($startuptime, "app\job\Listen@agent", ['page' => 0], 'task');
                }
            }
        }else{
            if(isset($sys['agent_settlement']) && $sys['agent_settlement'] != 0){
                if($sys['agent_settlement'] == 2){ // 星期制度
                    $agent_settlement_time = intval($sys['agent_settlement_time']);
                    if($agent_settlement_time > 6) $agent_settlement_time = 0;
                    $week = date("w");
                    $strat_time = strtotime(date('Y-m-d').'00:00:00');
                    if($week == $agent_settlement_time){
                        echo "页码：".$page."\r\n";
                        $match = Db::name('agent_rake_back')->where("status = 1 and create_time < ".$strat_time)->order('id asc')->limit($page*$agent_num, $agent_num)->column('id');
                        if(!empty($match)){
                            $startuptime = 10;
                            foreach($match as $key => $val){
                                if ($cache->has("agent_settlement_{$val}")) {
                                    continue;
                                }
                                $cache->set("agent_settlement_{$val}", 1, 86400);
                                $index = $val % $agent_mod;
                                if($index > 0){
                                    Queue::push("app\job\Settlement@agent", $val, 'agent'.$index);
                                }else{
                                    Queue::push("app\job\Settlement@agent", $val, 'agent');
                                }
                            }

                            if($agent_page <= $page){
                                Queue::later(600, "app\job\Listen@agent", ['page' => 0], 'task');
                            }else{
                                Queue::push("app\job\Listen@agent", ['page' => $page+1], 'task');
                            }                       
                        }else{
                            Queue::later($startuptime, "app\job\Listen@agent", ['page' => 0], 'task');
                        }
                    }else{
                        Queue::later($startuptime, "app\job\Listen@agent", ['page' => 0], 'task');
                    }
                }

                if($sys['agent_settlement'] == 3){ // 月度制度
                    $agent_settlement_time = intval($sys['agent_settlement_time']);
                    if($agent_settlement_time > 30) $agent_settlement_time = 1;
                    $day = date("d");
                    $strat_time = strtotime(date('Y-m-d').'00:00:00');
                    if($day == $agent_settlement_time){
                        echo "页码：".$page."\r\n";
                        $match = Db::name('agent_rake_back')->where("status = 1 and create_time < ".$strat_time)->order('id asc')->limit($page*$agent_num, $agent_num)->column('id');
                        if(!empty($match)){
                            $startuptime = 10;
                            foreach($match as $key => $val){
                                if ($cache->has("agent_settlement_{$val}")) {
                                    continue;
                                }
                                $cache->set("agent_settlement_{$val}", 1, 86400);
                                $index = $val % $agent_mod;
                                if($index > 0){
                                    Queue::push("app\job\Settlement@agent", $val, 'agent'.$index);
                                }else{
                                    Queue::push("app\job\Settlement@agent", $val, 'agent');
                                }
                            }
                            if($agent_page <= $page){
                                Queue::later(600, "app\job\Listen@agent", ['page' => 0], 'task');
                            }else{
                                Queue::push("app\job\Listen@agent", ['page' => $page+1], 'task');
                            }                       
                        }else{
                            Queue::later($startuptime, "app\job\Listen@agent", ['page' => 0], 'task');
                        }
                    }else{
                        Queue::later($startuptime, "app\job\Listen@agent", ['page' => 0], 'task');
                    }
                }

                if($sys['agent_settlement'] == 1){ // 隔天结算制度
                    $agent_settlement_time = intval($sys['agent_settlement_time']);
                    if($agent_settlement_time > 23){
                        $agent_settlement_time = 0;
                    }
                    $hour = date("H");
                    if($hour >= $agent_settlement_time){ //当前时间大于设定时间
                        $strat_time = strtotime(date('Y-m-d').'00:00:00');
                        echo "页码：".$page."\r\n";
                        $match = Db::name('agent_rake_back')->where("status = 1 and create_time < ".$strat_time)->order('id asc')->limit($page*$agent_num, $agent_num)->column('id');
                        if(!empty($match)){
                            $startuptime = 10;
                            foreach($match as $key => $val){
                                if ($cache->has("agent_settlement_{$val}")) {
                                    continue;
                                }
                                $cache->set("agent_settlement_{$val}", 1, 86400);
                                $index = $val % $agent_mod;
                                if($index > 0){
                                    Queue::push("app\job\Settlement@agent", $val, 'agent'.$index);
                                }else{
                                    Queue::push("app\job\Settlement@agent", $val, 'agent');
                                }
                            }

                            if($agent_page <= $page){
                                Queue::later(600, "app\job\Listen@agent", ['page' => 0], 'task');
                            }else{
                                Queue::push("app\job\Listen@agent", ['page' => $page+1], 'task');
                            }
                        }else{
                            Queue::later($startuptime, "app\job\Listen@agent", ['page' => 0], 'task');
                        }
                    }else{
                        Queue::later($startuptime, "app\job\Listen@agent", ['page' => 0], 'task');
                    }
                }

                if($sys['agent_settlement'] == 4){ // 不返佣
                    Queue::later($startuptime, "app\job\Listen@agent", ['page' => 0], 'task');
                }
            }else{
                Queue::later($startuptime, "app\job\Listen@agent", ['page' => 0], 'task');
            }
        }
        $job->delete(); //删除
        //$job->release($startuptime); // 间隔 $later 秒 重新发布任务
    }


    public function failed($data){

        // ...任务达到最大重试次数后，失败了
    }

    /*
   * vip升级
   */
    public function upgrade(Job $job, $data){
        $cache = new ApiRedis();
        if ($cache->has("deleteQueue:Upgrade")) {
            $job->delete();
            return;
        }
        $id = isset($data['id'])?intval($data['id']):0;
        $num = env('VIP_UPGRADE_NUL', 1000);
        $time = time() - 86400 / 2;
        $where[] = ['id', '>', $id];
        $where[] = ['lebal','=',1];
        $where[] = ['login_time', '>', $time];
        $User = UserModel::where($where)->limit($num)->column('id');
        $this->output("vip升级查询数据，当前id：[{$id}]");
        if(!empty($User)){
            foreach($User as $val){
                $redis_key = "user:upgrade:{$val}";
                if ($cache->has($redis_key)) {
                    continue;
                }
                Queue::push("app\job\User@upgrade", ['id' => $val, 'money' => 0], 'upgrade');
                $id = $val;
            }
            Queue::later(env('VIP_UPGRADE_TIME', 3), "app\job\Listen@upgrade", ['id' => $id], 'upgrade');
        } else {
            Queue::later(60, "app\job\Listen@upgrade", ['id' => 0], 'upgrade');
        }
        $job->delete(); //删除
        // ...任务达到最大重试次数后，失败了
    }

    /*
   * vip降级
   */
    public function downgrade(Job $job, $data){
        $cache = new ApiRedis();
        if ($cache->has("deleteQueue:Downgrade")) {
            $job->delete();
            return;
        }
        $id = isset($data['id'])?intval($data['id']):0;
        $num = env('VIP_DOWNGRADE_NUL', 1000);
        $time = time() - 86400 / 2;
        $where[] = ['id', '>', $id];
        $where[] = ['lebal','=',1];
        $where[] = ['login_time', '>', $time];
        $User = UserModel::where($where)->limit($num)->column('id');
        $this->output("vip降级查询数据，当前id：[{$id}]");
        if(!empty($User)){
            foreach($User as $val){
                $redis_key = "user:downgrade:{$val}";
                if ($cache->has($redis_key)) {
                    continue;
                }
                Queue::push("app\job\User@downgrade", ['id' => $val, 'money' => 0], 'downgrade');
                $id = $val;
            }
            Queue::later(env('VIP_DOWNGRADE_TIME', 3), "app\job\Listen@downgrade", ['id' => $id], 'downgrade');
        } else {
        Queue::later(60, "app\job\Listen@downgrade", ['id' => 0], 'downgrade');
        }
        $job->delete(); //删除
        // ...任务达到最大重试次数后，失败了
    }
    private function output($msg = '') {
        echo $msg . ";,时间:" . date("Y-m-d H:i:s") . "\n";
    }
}
