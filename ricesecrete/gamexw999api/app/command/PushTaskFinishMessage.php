<?php

namespace app\command;

use app\api\factory\TaskFactory;
use app\common\service\BetService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Cache;

class PushTaskFinishMessage extends Command{

    protected function configure()
    {
        // 指令配置
        $this->setName('push:task')
            ->addArgument("uid", Argument::OPTIONAL, 'appoint user id')
            ->setDescription('push finish task message to user');//推送任务完成消息给用户
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output = $output;
        $uid = $input->getArgument('uid') ?: 0;
        if( $uid ){
            (new TaskFactory($uid))->pushMessageToFinishTaskUser($uid);
        }else{
            BetService::betPush();
        }
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