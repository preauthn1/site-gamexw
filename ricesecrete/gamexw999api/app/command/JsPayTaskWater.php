<?php


namespace app\command;


use app\common\FastGame\Base\JsTaskWater;
use app\common\Utils;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class JsPayTaskWater extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('JsPayTaskWater')
            ->setDescription('返佣自动发放任务-每日2点，用以支持用户代理按日周月流水分佣，内部代理还是原方案,此任务不可与jspaytask并行执行');
        $this->addOption('dt', 'd', Option::VALUE_OPTIONAL, '执行结算日期（指定日期的前一日）');
    }

    protected function execute(Input $input, Output $output)
    {
        $dt=null;
        if ($input->hasOption("dt")) {
            $dt = $input->getOption("dt");
            $dt = empty($dt) ? null : $dt;
        }
        JsTaskWater::jsfypay($dt);
    }
}
