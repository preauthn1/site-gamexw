<?php
declare (strict_types = 1);
namespace app\command;


use app\common\redis\redis;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;


class ReportExport extends Command {
    protected function configure() {
        $this->setName('ReportExport')
            ->addArgument("clear_cache", Argument::OPTIONAL, 'clear cache')
            ->setDescription('报表异步导出任务（分钟级）');
    }

    protected function execute(Input $input, Output $output) {
        ini_set('memory_limit', '10240M');#
        if ($input->getArgument('clear_cache') == 1) {
            redis::del('generateReports');
        }
        \app\common\lib\ReportExport::generateReports();
    }

}
