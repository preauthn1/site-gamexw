<?php


namespace app\command;


use app\common\FastGame\Base\JsTaskWater as Js;
use app\common\FastGame\Base\JsTask;
use app\common\model\UserModel;
use app\common\upgrade\App;
use app\common\Utils;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class JsTaskWater extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('JsTaskWater')
            ->setDescription('返水和返佣统计任务-每天凌晨1点，用以支持用户代理按日周月流水分佣，内部代理还是原方案,此任务不可与jstask并行执行');
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
        if (strexists($dt, 'all')) {
            $dt = explode(":", $dt);
            if (isset($dt[1])) {
                $st = strtotime($dt[1]);
            } else {
                $output->error('请指定开始日期，格式=all:2023-03-01');
                return;
            }
            for ($i = $st; $i < time(); $i += 86400) {
                $dt = date("Y-m-d", $i);
                $r = $this->js($dt, $fyfy_mode);
                if ($r === false) {
                    $output->error('返佣模式不对');
                    break;
                }
            }
        } else {
            $r = $this->js($dt, $fyfy_mode);
            if ($r === false) {
                $output->error('返佣模式不对');
            }
        }
    }

    private function js($dt, $fyfy_mode) {
        if ($fyfy_mode == 1) {
            Js::jsfy($dt);
        } elseif ($fyfy_mode == 0) {
            JsTask::jsback($dt);
        } else {
            return false;
        }
        return true;
    }
}
