<?php
declare (strict_types = 1);

namespace app\command;

use app\api\factory\GameFactory;
use app\common\lib\Util;
use think\console\{Input, Output, Command};
use think\console\input\{Argument, Option};
use app\common\model\GameSetModel;
use think\helper\Str;

//查询某个平台指定时间的所有记录
class AecricketTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('AecricketTask')->setDescription('Aecricket game_logs');
    }

    protected function execute(Input $input, Output $output)
    {
        $time = time();
        $thistime = Util::getTimeZoneConversion(date('Y-m-d H:i:s',$time),'UTC');
        $start = $thistime - 600; //前10分钟
        $end = $thistime - 300;  //前5分钟
        try{
            $date = date('Y-m-d H:i:s',time());
            $output->writeln("=== {$date} 游戏记录生成成功===");
            return (new GameFactory())->get_game_logs($start, $end, 'aecricket');
        }catch (\Exception $e){
            $output->writeln("=== {$date} 游戏记录生成异常===");
            return false;
        }

    }

}
