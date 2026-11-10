<?php

namespace app\command;

use app\common\FastGame\Socket\SockPlugin;
use app\common\service\MemberService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class UserOnline extends Command
{
    protected function configure()
    {
        $this->setName('user:online')
            ->setDescription('check user online');
    }

    protected function execute(Input $input, Output $output)
    {
        //每分钟执行
        SockPlugin::onlineUserInfoProcTask();
         //更新用户第三方余额
//        MemberService::updateUserThirdsBalanceList();
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