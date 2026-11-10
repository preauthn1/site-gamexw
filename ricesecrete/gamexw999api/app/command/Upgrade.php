<?php

declare (strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\console\input\Option;
use app\common\upgrade\App;

class Upgrade extends Command {

    protected function configure() {
        $this->setName('app:upgrade')->setDescription('代码升级');
        $this->addOption('reset', 'r', Option::VALUE_OPTIONAL, '重置版本号并执行升级,不指定版本号表示清除缓存从初始版本升级，指定版本号表示从版本号开始升级');
    }

    protected function execute(Input $input, Output $output) {
        if ($input->hasOption("reset")) {
            $ver = $input->getOption("reset");
            $ver = empty($ver) ? 0 : intval($ver);
            App::clearCacheVersion($ver);
        }
        $output->writeln("当前版本号: " . App::getCacheVersion());
        App::upgrade();
        $output->writeln("最新版本号: " . App::getVersion());
    }

}
