<?php

namespace app\command;

use app\common\FastGame\Base\Utils;
use app\common\FastGame\Socket\socketSrv;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Exception;

class SocketServer extends Command
{
    protected function configure()
    {
        $this->setName('socket:server')->setDescription('socket消息服务,最少启动2个服务[一个主服务，一个消息服务]');
        $this->addArgument('srv', Argument::OPTIONAL, "服务器名称（socketmsgcfg.ini中的服务器配置段名)", 'server');
    }

    protected function execute(Input $input, Output $output)
    {
        $srv = $input->getArgument("srv");
        $config = Utils::ini("{$srv}");
        if (empty($config)) {
            throw new Exception('服务器名称错误，请查看[socketmsgcfg.ini]');
        }
        if ($srv) {
            new socketSrv($srv);
        }
    }

}