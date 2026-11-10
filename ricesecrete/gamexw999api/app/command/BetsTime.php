<?php

namespace app\command;

use app\common\service\BetService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class BetsTime extends Command{

    protected function configure()
    {
        // 指令配置 TODO 该计划任务必须得和设置执行时间一致
        $this->setName('bets:time')
            ->setDescription('add gb_id and time');//添加注单id和时间
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output = $output;
        BetService::cacheBetIdByTime();
        $this->log();
    }


    protected function log($content = 'success')
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        $this->output->writeln(date('Y-m-d H:i:s') . ' ' . $content);
    }
}