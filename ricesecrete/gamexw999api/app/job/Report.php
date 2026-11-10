<?php
namespace app\job;
use app\api\redis\ApiRedis;
use think\facade\Queue;
use think\facade\Cache;
use think\queue\Job;

class Report {
    public function fire(Job $job, $data) {
        
        $job->delete();
    }

    private function output($msg = '') {
        echo $msg . ";,时间:" . date("Y-m-d H:i:s") . "\n";
    }

}
