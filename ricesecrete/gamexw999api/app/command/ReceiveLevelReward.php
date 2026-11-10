<?php

namespace app\command;

use app\api\factory\GameFactory;
use app\api\factory\UserFactory;
use app\common\model\GameSetModel;
use app\common\model\UserLevelRewardModel;
use app\common\model\UserLogModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;

class ReceiveLevelReward extends Command{

    protected function configure()
    {
        // 指令配置
        $this->setName('receive level reward')
            ->setDescription('receive level reward');//自动领取等级奖励
    }

    protected function execute(Input $input, Output $output)
    {
        $this->output = $output;

        //处理数据 TODO 自动领取该方法已弃用，若重新启用需检查下面时间算法
        $this->handleAutoReceive();

        $this->log();
    }

    //处理每月每周自动领取
    private function handleAutoReceive(){
        //本月和本周分开处理，防止数据量大
        //上周时间
        $weekStartTime = strtotime(date('Y-m-01 00:00:00',strtotime('-1 month')));
        $weekEndTime = strtotime(date('Y-m-t 23:59:59',strtotime('-1 month')));
        //上月时间
        $monthStartTime = strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),date("d")-date("N")+1-7,date("Y"))));
        $monthEndTime = strtotime(date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("d")-date("N")+7-7,date("Y"))));
        //先处理周自动领取
        $condition = [];
        $condition[] = ['status', '=', 1];
        $condition[] = ['create_time', 'between', [$weekStartTime, $weekEndTime]];
        $condition[] = ['reward_type', '=', UserLevelRewardModel::WEEK_REWARD_TYPE];
        $weekRewardData = UserLevelRewardModel::where($condition)->select();
        $weekSuccessNum = 0;
        $weekSuccessData = [];
        foreach ($weekRewardData as $v){
            //领取等级奖励
            $res = (new UserFactory($v->uid))->receiveLevelReward_run(['level_id' => $v->level_id, 'reward_type' => UserLevelRewardModel::WEEK_REWARD_TYPE], $v->uid);
            if( $res['code'] == 1){
                $weekSuccessNum++;
                $weekSuccessData[] = ['uid' => $v->uid, 'level_id' => $v->level_id];
            }
        }
        $this->log('week success num：'.$weekSuccessNum.',data: '.json_encode($weekSuccessData));
        //处理月自动领取
        $condition = [];
        $condition[] = ['status', '=', 1];
        $condition[] = ['create_time', 'between', [$monthStartTime, $monthEndTime]];
        $condition[] = ['reward_type', '=', UserLevelRewardModel::MONTH_REWARD_TYPE];
        $monthRewardData = UserLevelRewardModel::where($condition)->select();
        $monthSuccessNum = 0;
        $monthSuccessData = [];
        foreach ($monthRewardData as $v){
            //领取等级奖励
            $res = (new UserFactory($v->uid))->receiveLevelReward_run(['level_id' => $v->level_id, 'reward_type' => UserLevelRewardModel::MONTH_REWARD_TYPE], $v->uid);
            if( $res['code'] == 1){
                $monthSuccessNum++;
                $monthSuccessData[] = ['uid' => $v->uid, 'level_id' => $v->level_id];
            }
        }
        $this->log('month success num：'.$monthSuccessNum.',data: '.json_encode($monthSuccessData));
    }

    protected function log($content = 'success')
    {
        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }
        $this->output->writeln(date('Y-m-d H:i:s') . ' ' . $content);
    }
}