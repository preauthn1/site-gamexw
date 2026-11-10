<?php
declare (strict_types = 1);

namespace app\command;

use app\api\factory\BotFactory;
use think\console\{Input, Output, Command};
use think\console\input\{Argument, Option};

//查询某个平台指定时间的所有记录
class BotTask extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('BotTask')->setDescription('BotTask BotMessage');
    }

    protected function execute(Input $input, Output $output)
    {
        try{
            $output->writeln("=== 机器人发送消息成功 ===");
            return (new BotFactory())->AutonMessage();
        }catch (\Exception $e){
            $output->writeln("=== 机器人发送消息异常 ===");
            return false;
        }

    }

}
