<?php


namespace app\command;


use app\common\FastGame\Base\JsTask;
use app\common\Utils;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class JsPayTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('JsPayTask')
            ->setDescription('返佣自动发放任务-每月2号2点，如果是按周结算的话请设置不每周周2凌晨2点');
        $this->addOption('dt', 'd', Option::VALUE_OPTIONAL, '执行结算日期（指定日期的前一日）');
    }

    protected function execute(Input $input, Output $output)
    {
        $dt=null;
        if ($input->hasOption("dt")) {
            $dt = $input->getOption("dt");
            $dt = empty($dt) ? null : $dt;
        }
        JsTask::jsfypay($dt);
    }
}
