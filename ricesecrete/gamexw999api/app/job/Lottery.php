<?php

namespace app\job;

use app\common\lottery\App;
use app\common\Utils;
use think\facade\Queue;
use think\queue\Job;

class Lottery {
    public function fire(Job $job, $data) {
        if (empty($data['lottery']) || empty($data['qishu'])) {
            $job->delete();
            return;
        }
        try {
            $lottery = $data['lottery'];
            $qishu = $data['qishu'];
            $this->output("结算进程-彩种:{$lottery['name']};期数:{$qishu}");
            $obj = App::init($lottery['lottery_type'], $lottery);
            $is_done = $obj->js($qishu, 500);
            if (!$is_done) {
                $this->output("未结算完订单，延迟3秒下一波订单结算-彩种:{$lottery['name']};期数:{$qishu}");
                Queue::later(3, "app\job\Lottery", $data, 'lottery');
            }
        } catch (\Exception $e) {
            Utils::L('lottery-js', [$e->getMessage(), $e->getTraceAsString()]);
            $this->output("出错了，延迟3秒:" . $e->getMessage());
            Queue::later(3, "app\job\Lottery", $data, 'lottery');
        }
        $job->delete();
    }

    private function output($msg = '') {
        echo $msg . ";,时间:" . date("Y-m-d H:i:s") . "\n";
    }
}