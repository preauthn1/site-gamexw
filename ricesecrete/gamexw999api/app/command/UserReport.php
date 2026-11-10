<?php


namespace app\command;


use app\api\redis\ApiRedis;
use app\common\FastGame\Base\JsTask as Js;
use app\common\lib\ActiveReport;
use app\common\Utils;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class UserReport extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('UserReport')
            ->addArgument("is_clear", Argument::OPTIONAL, 'is clear cache')
            ->setDescription('用户报表测试服-每五分钟');
    }

    protected function execute(Input $input, Output $output)
    {
        $apiRedis = new ApiRedis();
        $redisKey = 'user_game_statistics_global_user';

        //是否清除缓存
        $isClear = $input->getArgument('is_clear') ?: 0;
        if( $isClear == 1 ){
            $apiRedis->delete($redisKey);
            $output->writeln('缓存已清除，等待下次执行');
            return false;
        }

        if( $apiRedis->has($redisKey) ){
            $output->writeln('用户游戏统计执行中');
            return false;
        }
        do{
            $startTime = time();
            $apiRedis->set($redisKey, $startTime, 3600 * 5);
            //死循环执行

            $output->writeln("------------------countUserBets--------------------");
            Js::countUserBets();
            $output->writeln("------------------countStatGlobal--------------------");
            Js::countStatGlobal();
            $output->writeln("------------------countActiveReport--------------------");
            ActiveReport::runtask();

            //休眠等待下次执行
            $sleepTime = env('USER_REPORT_DEALY_TIME', 60);
            $endTime = time();
            $output->info("休眠{$sleepTime}秒,执行开始时间" . date('Y-m-d H:i:s', $startTime) . ",执行结束时间" . date('Y-m-d H:i:s', $endTime) ."本次执行时间(s)：" . (time() - $startTime) );
            sleep($sleepTime);
            $redisValue = $apiRedis->get($redisKey);
        }while($redisValue);
    }
}
