<?php


namespace app\command;


use app\api\factory\CollectionFactory;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class AgentSell extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('agentsell')
            ->setDescription('推广奖励自动派发');
    }

    protected function execute(Input $input, Output $output)
    {
        $time = date('Y-m-d H:i:s');
        try{
            (new CollectionFactory())->agent_run();
            $output->writeln("=== 推广奖励派发成功 ===  派发时间: {$time}");
        }catch (\Exception $e){
            $output->writeln($e->getMessage());
        }
    }
}
