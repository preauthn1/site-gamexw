<?php
declare (strict_types = 1);

namespace app\command;

use app\api\factory\GameFactory;
use think\console\{Input, Output, Command};
use think\console\input\{Argument, Option};
use app\common\model\GameSetModel;
use think\helper\Str;

//查询某个平台指定时间的所有记录
class ManualTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('manualtask')
            ->setDescription('Manual game_logs');
    }

    protected function execute(Input $input, Output $output)
    {
        //选择时区 比如fb是使用时间戳去获取 就可以用时区 或者下面直接用转时区后的时间
//        date_default_timezone_set('Asia/Shanghai');
        //开始时间 - 结束时间 根据平台要求传递
        //WM
        $start = 20221128000000;// strtotime('20221128000000');
        $end = 20221128235959; // strtotime('');
        //一个周期多长 没有周期 可以直接执行获取 $this->operation($start, $end);
        $this->operation($start, $end, 'FB');
        die;
        //FB
        $start = strtotime('2022-11-23 00:00:00');
        $end = strtotime('2022-11-26 02:59:59');
        //一个周期多长 没有周期 可以直接执行获取 $this->operation($start, $end);
        for($i = $start; $i <= $end; $i += 3600){
            $start_time = $i - 3600;
            $start = date('Y-m-d H:i:s', $start_time);
            try{
                $res =  $this->operation($start_time, $i);
                $output->writeln("=== {$start} 游戏记录生成成功===");
            }catch (\Exception $e){
                $output->writeln("=== {$start} 游戏记录生成异常===");
                continue;
            }
        }

    }

    public function  operation($start, $end, $tc_code){
        return (new GameFactory())->get_game_logs($start, $end, $tc_code);
    }
}
