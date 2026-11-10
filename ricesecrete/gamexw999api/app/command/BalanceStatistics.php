<?php

namespace app\command;

use app\common\model\UserModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class BalanceStatistics extends Command{

    protected function configure()
    {
        $this->setName('balance:statistics')
            ->setDescription('user balance statistics');
    }

    protected function execute(Input $input, Output $output)
    {
        //每分钟执行
        Db::name('balance_statistics')->insert([
            'balance' => (new UserModel())->sum('balance'),
            'create_time' => time()
        ]);
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