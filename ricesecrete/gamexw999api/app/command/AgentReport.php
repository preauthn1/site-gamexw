<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use app\manage\controller\ReportAgent;
use app\common\model\UserAgentModel;
use app\common\model\UserModel;
use think\facade\Db;


class AgentReport extends Command
{
    protected function configure()
    {
        $this->setName('agentreport')
            ->setDescription('代理报表数据统计')
            ->addArgument('status', Argument::REQUIRED, "类型 1自动统计昨天数据 2手动按日期统计")
            ->addArgument('and_day', Argument::REQUIRED, "开始日期")
            ->addArgument('end_day', Argument::REQUIRED, "结束日期");

    }

    protected function execute(Input $input, Output $output)
    {

        $status = $input->getArgument("status");
        $and_day = $input->getArgument("and_day");
        $end_day = $input->getArgument("end_day");
        if($status == 1) {
            $start_time = strtotime('-1 day 00:00:00');
            $end_time = strtotime('-1 day 23:59:59');
        }else{
            $start_time = strtotime($and_day.' 00:00:00');
            $end_time = strtotime($end_day.' 23:59:59');
        }

        $list = UserAgentModel::where(['status' => 3])->column('uid');

        if(empty($list)){
            $output->info("没有代理数据");
            return;
        }


        foreach ($list as $uid) {
            $user = UserModel::where('id', '=', $uid)->field('id,tree')->find();
            if(empty($user)){
                continue;
            }
            if($status == 1){
                $report = Db::name('operating_agent')->where(['user_id' => $user->id])->whereTime('day_time', 'yesterday')->value('id');
                if(empty($report)){
                    $data = ReportAgent::getAgentReport($start_time, $end_time, $user,2);
                    Db::name('operating_agent')->insert($data);
                }
            }else{
                for ($i = $start_time; $i <= $end_time; $i += 86400) {
                    $date = date('Y-m-d', $i);
                    $report = Db::name('operating_agent')->where(['user_id' => $user->id, 'day_time' => $date])->value('id');
                    $data = ReportAgent::getAgentReport($start_time, $end_time, $user,2);
                    if(empty($report)){
                        $start_time = strtotime($date.' 00:00:00');
                        $end_time = strtotime($date.' 23:59:59');
                        Db::name('operating_agent')->insert($data);
                    }else{
                        $data['day_time'] = $date;
                        Db::name('operating_agent')->where(['id' => $report])->update($data);
                    }

                }
            }
        }

        $output->info("done！");

    }
}