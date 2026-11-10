<?php

namespace app\command;

use app\common\model\UserModel;
use Carbon\Carbon;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Queue;

class UpdateUserVipDown extends Command{

    //TODO 该程序只能在0点1分后执行
    protected function configure()
    {
        // 指令配置
        $this->setName('updatevipdown')
            ->setDescription('update user vip down');//降级一天内登录的用户VIP
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output = $output;
        $output->writeln("开始执行VIP降级");
        $this->handleVipDowngrade();
        $output->writeln("VIP降级执行完毕");
        $this->log();
    }

    //处理VIP升级
    private function handleVipDowngrade($startId = 0){
        $limit = env('vip_downgrade_limit', 5000);
        //查询一天内登录的用户
        $condition = [];
        $condition[] = ['login_time', '>=', Carbon::yesterday()->startOfDay()->timestamp];
        $condition[] = ['id', '>', $startId];
        $userList = UserModel::where($condition)->limit($limit)->field('id')->select();
        if( !$userList ){
            $this->output->writeln("没有需要升级的用户");
            return;
        }
        $this->output->writeln("处理起始id为{$startId}的用户，查询到{$userList->count()}个用户");
        foreach ($userList as $user){
            Queue::push("app\job\User@downgrade", ['id' => $user->id, 'money' => 0], 'upgrade');
            $startId = $user->id;
        }
        $this->output->writeln("已将用户加入队列，等待处理");
        if ($userList->count() == $limit) {
            sleep(3);
            $this->output->writeln("休眠3秒后继续处理下一批用户，起始id为{$startId}");
            $this->handleVipDowngrade($startId);
        }
    }


    protected function log($content = 'success')
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        $this->output->writeln(date('Y-m-d H:i:s') . ' ' . $content);
    }
}
