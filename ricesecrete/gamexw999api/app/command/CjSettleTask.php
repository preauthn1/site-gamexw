<?php
declare (strict_types=1);

namespace app\command;

use app\api\redis\ApiRedis;
use app\common\model\UserModel;
use app\common\service\MemberService;
use app\common\service\UserWithdrawalService;
use think\console\{Command, Input, input\Argument, Output};

/**
 * 彩金结算任务
 * 彩金流水为零后自动将彩金钱包转到余额余额钱包
 * Class CjSettleTask
 * @package app\command
 */
class CjSettleTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('cjsettletask')
            ->addArgument("is_clear", Argument::OPTIONAL, 'is clear cache')
            ->setDescription('Cj settle');
    }

    protected function execute(Input $input, Output $output)
    {

        $apiRedis = new ApiRedis();
        $redisKey = 'user_cj_clear_task';

        //是否清除缓存
        $isClear = $input->getArgument('is_clear') ?: 0;
        if( $isClear == 1 ){
            $apiRedis->delete($redisKey);
            $output->writeln('缓存已清除，等待下次执行');
            return false;
        }

        if( $apiRedis->has($redisKey) ){
            $output->writeln('清除彩金执行中');
            return false;
        }
        do{
            $startTime = time();
            $apiRedis->set($redisKey, $startTime, 3600 * 5);

            $user_ids = MemberService::updateCjUserList();
            //指令输出
            $output->writeln('update user_ids:' . json_encode($user_ids));

            //休眠等待下次执行
            $sleepTime = env('USER_CJ_CLEAR_DEALY_TIME', 10);
            $endTime = time();
            $output->info("休眠{$sleepTime}秒,执行开始时间" . date('Y-m-d H:i:s', $startTime) . ",执行结束时间" . date('Y-m-d H:i:s', $endTime) ."本次执行时间(s)：" . (time() - $startTime) );
            sleep($sleepTime);
            $redisValue = $apiRedis->get($redisKey);
        }while($redisValue);

    }
}
