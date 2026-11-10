<?php

namespace app\command;

use app\api\redis\ApiRedis;
use app\common\lottery\App;
use Carbon\Carbon;
use think\console\Command;
use think\console\input\Argument;
use think\console\Input;
use think\console\Output;
use think\facade\Db;
use app\common\Utils;
use think\facade\Queue;

class LotteryDraw extends Command {

    public $logfile = 'lottery_draw';
    public $count = 1;
    protected function configure() {
        // 指令配置
        $this->setName('lottery:draw')
            ->addArgument("lottery_id", Argument::OPTIONAL, '彩种ID')
            ->addArgument("qishu", Argument::OPTIONAL, '彩种期数')
            ->setDescription('自营彩票开奖');
    }

    protected function execute(Input $input, Output $output) {
        $key = "lottery:draw:st";
        $redis = new ApiRedis();
        $st = $redis->get($key);
        if ($st && time()-$st < 60){
            //如果60秒内发现程序还在执行，则退出任务
            return;
        }
        $lottery_id = $input->getArgument('lottery_id');
        $qishu = $input->getArgument('qishu');
        $now = Carbon::now()->timestamp;
        $next_minute = strtotime(Carbon::now()->addMinute()->format("Y-m-d H:i:00"));
        if ($next_minute - $now < 55) {
            sleep($next_minute - $now);
        }
        while (true) {
            if ($this->count > 10000) {
                break;
            }
            $this->draw($lottery_id, $qishu);
            $redis->set($key, time(),86400);
            $now = Carbon::now()->timestamp;
            $next_minute = strtotime(Carbon::now()->addMinute()->format("Y-m-d H:i:00"));
//            $this->output->writeln("{$now} -- {$next_minute}---" . ($next_minute - $now));
            $this->count++;
            sleep($next_minute - $now);
        }
    }

    private function draw($lottery_id, $qishu) {
        $where = ['status' => 1];
        if ($lottery_id) {
            $where['id'] = $lottery_id;
        }
        $lotterys = Db::name('s_lottery')->where($where)->select();
        if (empty($lotterys)) {
            $this->output->error('没有彩种数据');
            return;
        }
        foreach ($lotterys AS $lottery) {
            $obj = App::init($lottery['lottery_type'], $lottery);
            $result = $obj->draw($qishu);
            if (isset($result['open_time'])) {
                Queue::push("app\job\Lottery", [
                    'lottery' => $lottery,
                    'qishu' => $result['qishu']
                ], 'lottery');
            }
            $this->output->writeln(date("Y-m-d H:i:s") . "-{$this->count}-彩种:[{$lottery['name']}], ID：{$lottery['id']},开奖结果:" . json_encode($result));
        }
    }
}