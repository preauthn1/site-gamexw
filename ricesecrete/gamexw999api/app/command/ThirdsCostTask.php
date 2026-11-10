<?php


namespace app\command;


use app\common\model\ThirdCostLogsModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class ThirdsCostTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('ThirdsCostTask')
            ->setDescription('平台全局处理三方平台服务费预储分扣除任务 1分钟级');
    }

    protected function execute(Input $input, Output $output)
    {
        ThirdCostLogsModel::thirdCostTask();
    }
}
