<?php


namespace app\command;


use app\api\redis\ApiRedis;
use app\common\FastGame\Base\JsTask;
use app\common\FastGame\Base\JsTaskTest;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class JsTaskHot extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('JsTaskHot')
            ->setDescription(' 热表记录迁移');
    }

    protected function execute(Input $input, Output $output)
    {
        $apiRedis = new ApiRedis();
        $redisKey = 'bet_hot_move';
        if( $apiRedis->has($redisKey) ){
            $output->writeln('热表迁移执行中');
            return false;
        }
        do{
            $apiRedis->set($redisKey,1);
            $startTime = time();
            //死循环执行热表迁移
            JsTask::transHotBets();
            //休眠等待下次执行
            $sleepTime = env('HOT_BET_SLEEP_TIME', 60);
            $endTime = time();
            $output->info("休眠{$sleepTime}秒,执行开始时间" . date('Y-m-d H:i:s', $startTime) . ",执行结束时间" . date('Y-m-d H:i:s', $endTime) ."本次执行时间(s)：" . (time() - $startTime) );
            sleep($sleepTime);
        }while(true);
    }
}
