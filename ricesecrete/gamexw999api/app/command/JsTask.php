<?php


namespace app\command;


use app\common\FastGame\Base\JsTask as Js;
use app\common\upgrade\App;
use app\common\Utils;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class JsTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('JsTask')
            ->setDescription('返水和返佣统计任务-每天凌晨1点');
        $this->addOption('dt', 'd', Option::VALUE_OPTIONAL, '执行结算日期（指定日期的前一日）');
    }

    protected function execute(Input $input, Output $output)
    {
        $fyfy_mode = env("FYFY_MODE", 1);
        $dt=null;
        if ($input->hasOption("dt")) {
            $dt = $input->getOption("dt");
            $dt = empty($dt) ? null : $dt;
        }
        if ($fyfy_mode == 2) {
            Js::jsfy($dt);
        } elseif ($fyfy_mode == 0) {
            Js::jsback($dt);
        } else {
            $output->error('返佣模式不对');
        }
    }
}
