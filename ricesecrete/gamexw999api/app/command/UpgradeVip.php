<?php

declare (strict_types=1);

namespace app\command;

use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Queue;

class UpgradeVip extends Command {

    protected function configure() {
        $this->setName('upgrade:vip')->setDescription('用户手动检测VIP升级');
        $this->addArgument('uid', Argument::REQUIRED, "用户ID");
    }

    protected function execute(Input $input, Output $output) {
        $uid = $input->getArgument("uid");
        if(!is_numeric($uid)) {
            throw new \InvalidArgumentException('用户ID只能是数字');
        }
        $user = UserModel::where("id", $uid)->find();
        if (empty($user)) {
            throw new \InvalidArgumentException('用户ID错误');
        }

        Queue::push("User@upgrade", ['id' => $uid, 'money' => 0], 'upgrade');
    }

}
