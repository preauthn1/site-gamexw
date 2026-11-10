<?php


namespace app\command;


use app\common\FastGame\Base\ThirdTask;
use app\common\Utils;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class GenTask extends Command
{
    private $logfile="GenTask";
    protected function configure()
    {
        // 指令配置
        $this->setName('fastgamegentask')
            ->setDescription('平台全局调度任务-分钟级');
    }

    protected function execute(Input $input, Output $output)
    {
        //本模式下暂时用不到此文件，但未来可能会用到 TWT 20221022
        //ThirdTask::run();
    }
}
