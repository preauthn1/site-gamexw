<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
class ActivityTask extends Command
{
    protected function configure()
    {
        $this->setName('activity:task')->setDescription('重新活动发放奖励');
        $this->addArgument('type', Argument::REQUIRED, "活动类型")
            ->addArgument('user_id', Argument::REQUIRED, "用户ID")
            ->addArgument('recharge_id', Argument::OPTIONAL, "recharge id");
    }

    protected function execute(Input $input, Output $output)
    {
        $user_id = $input->getArgument("user_id");
        $recharge_id = $input->getArgument("recharge_id");
        $type = $input->getArgument("type");
        if(is_null($user_id) || !in_array($type, [1,2,3,7])) {
            throw new \InvalidArgumentException('参数有误');
        }
        if(in_array($type, [1,2]) && is_null($recharge_id)) {
            throw new \InvalidArgumentException('参数有误');
        }
        action_task($user_id, $type, $recharge_id);
        $output->info("done！");

    }
}