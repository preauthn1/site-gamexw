<?php


namespace app\command;


use app\api\redis\ApiRedis;
use app\common\FastGame\Base\ThirdTask;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class ThirdsTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('ThirdsTask')
            ->addArgument('code', Argument::OPTIONAL, '指定获取平台代码数据')
            ->setDescription('平台全局获取注单任务 5分钟级');
    }

    protected function execute(Input $input, Output $output)
    {
        $apiRedis = new ApiRedis();
        $key = 'thirds_task_run_key';
        if( $apiRedis->has($key) ){
            $output->error('任务执行中。。。');
            return false;
        }
        $apiRedis->set($key, 1, 60);
        $code = $input->getArgument('code');
        ThirdTask::run($code);
        $apiRedis->delete($key);
    }
}
