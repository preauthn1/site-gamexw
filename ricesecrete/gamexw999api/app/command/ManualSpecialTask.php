<?php
declare (strict_types = 1);

namespace app\command;

use app\api\factory\GameFactory;
use think\console\{Input, Output, Command};
use think\console\input\{Argument, Option};
use app\common\model\GameSetModel;
use think\helper\Str;

//查询指定时间特殊平台的特殊记录 比如 wm平台的小费
class ManualSpecialTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('manualspecialtask')
            ->setDescription('ManualSpecial game_logs');
    }

    protected function execute(Input $input, Output $output)
    {
        //wm时区是+8的
//        date_default_timezone_set('Asia/Shanghai');
        $time = time();
        $start = 20221128000000;
        $end = 20221128235959;
        return (new GameFactory())->get_special_logs($start, $end);
    }
}
