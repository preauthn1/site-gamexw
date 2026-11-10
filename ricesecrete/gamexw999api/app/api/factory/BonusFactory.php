<?php

namespace app\api\factory;

use app\common\lib\Util;
use app\common\model\AgentSettingModel;
use app\common\model\TaskModel;
use app\common\model\TaskTypeModel;
use app\common\model\UserLevelModel;
use app\common\model\UserLevelRewardModel;
use app\common\model\UserModel;
use think\facade\Request;

class BonusFactory{

    private $param;//请求参数
    private $uid;//用户id
    private $uinfo;//用户数据

    //验证
    public function __construct($uid=0,$agent=null){
        $this->param=Request::param();
        $this->uid=$uid;
        $this->uinfo=UserModel::where("id",$this->uid)->find();
    }


    //获取所有奖金列表
    public function BonusList(){
        $bonusList = [];

        $userFactory = new UserFactory($this->uid);
        #1、邀请用户
        $getRemuneration = (new AgentFactory($this->uid))->getRemuneration()['data']['list'];
        foreach ($getRemuneration as $v){
            $status = 0;
            if( $v['is_finish'] == 1 ){
                $status = 1;
            }
            $isReceive = 0;
            if( $v['is_receive'] == 1 ){
                $isReceive = 1;
            }
            //判断是否是重复领取的奖励
            $receiveNum = 1;
            //获取用户邀请人数
            $people = $userFactory->getUserInvitePerson($this->uid, $v['start_time']);
            if( $v['reward_type'] == AgentSettingModel::REWARD_TYPE_ONE ){
                $receiveNum = (new AgentFactory($this->uid))->getReceivePeopleNum($this->uid, $v, $people);
                //判断是否能领取
                if($receiveNum > 0){
                    $status = 1;
                }
            }
            $amount = $receiveNum * $v['stratum_bonus'];
            if($amount > 0 && !$isReceive){
                if( $status == 1 ){
                    $bonusList[] = $this->formatData(
                        'team/receiveInviteRebate', 'invite', ['id' => $v['id']],
                        '邀请好友', '邀请好友获得奖金{:bonus}', ['bonus' => Util::i18n_currency($amount)],
                        $status,$isReceive, 'api', [], 'post', $v['icon']
                    );
                }else{
                    $bonusList[] = $this->formatData(
                        '/team-center', 'invite', [],
                        '邀请好友', '邀请好友获得奖金{:bonus}', ['bonus' => Util::i18n_currency($amount)],
                        $status,$isReceive, 'url', [], 'post', $v['icon']
                    );
                }

                break;//只显示一个邀请奖励，后续的奖励不显示
            }
        }
        #2、签到奖励
        $signData = (new SignFactory())->getSignActivity($this->uid)['data'];
        if($signData){
            $bonusList[] = $this->formatData(
                '/singin', 'sign', [],
                '签到奖励', '签到奖励{:amount}', ['amount' => Util::i18n_currency($signData['total_amount'])],
                0, 0, 'url'
            );
        }
        #3、VIP周月礼金
        //获取等级数据
        $levelDetail = UserLevelModel::where('id', '=', $this->uinfo['level_id'])->find();
        if( $levelDetail ){
            //周礼金
            $rewardType = UserLevelRewardModel::WEEK_REWARD_TYPE;
            $amount = $levelDetail->week_reward_amount;
            if( $amount > 0 ){
                //判断本次奖励是否领取
                $status = 1;
                $isReceive = 0;
                if( (new UserFactory($this->uid))->judgeUserIsReceiveLevel($this->uid, $levelDetail, $rewardType) ){
                    $isReceive = 1;
                }
                //计算周礼金倒计时
                $condition = [];
                $condition[] = ['uid', '=', $this->uid];
                $condition[] = ['reward_type', '=', UserLevelRewardModel::WEEK_REWARD_TYPE];
                $_weekRecordTime = UserLevelRewardModel::where($condition)->order('create_time desc')->value('create_time') ?: 0;
                if ($_weekRecordTime) $weekRecordTime = $_weekRecordTime;
                $weekExtraData = ['week_is_receive_vip_reward' => $isReceive, 'week_last_receive_time' => $weekRecordTime ?? 0];
                $bonusList[] = $this->formatData(
                    'user/receiveLevelReward', 'vip_week', ['level_id' => $this->uinfo['level_id'], 'reward_type' => $rewardType],
                    'VIP周礼金', '给尊贵的{:vip_name}用户的周奖励{:amount}', ['vip_name' => $levelDetail->level_name, 'amount' => Util::i18n_currency($amount)],
                    $status, $isReceive, 'api', $weekExtraData, 'post', $levelDetail['image']
                );
            }
            //月礼金
            $rewardType = UserLevelRewardModel::MONTH_REWARD_TYPE;
            $amount = $levelDetail->month_reward_amount;
            if( $amount > 0 ){
                //判断本次奖励是否领取
                $status = 1;
                $isReceive = 0;
                if( (new UserFactory($this->uid))->judgeUserIsReceiveLevel($this->uid, $levelDetail, $rewardType) ){
                    $isReceive = 1;
                }
                //计算月礼金倒计时
                $condition = [];
                $condition[] = ['uid', '=', $this->uid];
                $condition[] = ['reward_type', '=', UserLevelRewardModel::MONTH_REWARD_TYPE];
                $_monthRecordTime = UserLevelRewardModel::where($condition)->order('create_time desc')->value('create_time') ?: 0;
                if ($_monthRecordTime) $monthRecordTime = $_monthRecordTime;
                //判断VIP等级奖励是否领取
                $monthExtraData = ['month_is_receive_vip_reward' => $isReceive, 'month_last_receive_time' => $monthRecordTime ?? 0];
                $bonusList[] = $this->formatData(
                    'user/receiveLevelReward', 'vip_month', ['level_id' => $this->uinfo['level_id'], 'reward_type' => $rewardType],
                    'VIP月礼金', '给尊贵的{:vip_name}用户的月奖励{:amount}', ['vip_name' => $levelDetail->level_name, 'amount' => Util::i18n_currency($amount)],
                    $status, $isReceive, 'api', $monthExtraData, 'post', $levelDetail['image']
                );
            }
        }

        #4、PDD奖励
        $pddData = (new PddFactory($this->uid))->getTurntable()['data'];
        if($pddData){
            $bonusList[] = $this->formatData(
                '/?pdd=show', 'pdd', [],
                'PDD奖励', 'PDD奖励{:amount}', ['amount' => Util::i18n_currency($pddData['amount'])],
                0, 0, 'url', [], 'post', $pddData['image']
            );
        }

        #5、任务
        //普通任务
        $taskList = (new TaskFactory($this->uid))->index_run()['data']['list'];
        foreach ($taskList as $v){
            $status = 0;
            if( $v['is_finish'] == 1 ){
                $status = 1;
            }
            $isReceive = 0;
            if( $v['is_receive'] == 1 ){
                $isReceive = 1;
            }

            $content = $v['reward_type'] == TaskModel::INTEGRAL_REWARD_TYPE ? '完成 {:task_name} 获得{:integral}积分' : '完成 {:task_name} 获得{:amount}';
            if( $v['amount_or_integral'] > 0 && !$isReceive ){
                if($status == 1){
                    $bonusList[] = $this->formatData(
                        'task/receive', 'task', ['task_id' => $v['id']],
                        '完成{:task_name}', $content, ['task_name' => $v['title'], 'amount' => Util::i18n_currency($v['amount_or_integral']), 'integral' => $v['amount_or_integral']],
                        $status, $isReceive
                    );
                }else{
                    $bonusList[] = $this->formatData(
                        '/layout/task-center', 'task', [],
                        '完成{:task_name}', $content, ['task_name' => $v['title'], 'amount' => Util::i18n_currency($v['amount_or_integral']), 'integral' => $v['amount_or_integral']],
                        $status, $isReceive, 'url'
                    );
                }

                break;//只显示一个任务奖励，后续的奖励不显示
            }
        }
        #6、阶段任务
        //查询绑定阶段任务的分类id
        $taskTypeId = TaskTypeModel::where('stage_id', '>', 0)->value('id');
        if( $taskTypeId > 0 ){
            $taskStageDetail = (new TaskFactory($this->uid))->index_run($taskTypeId)['data']['list'];
            if(isset($taskStageDetail['type'])){
                //获取用户活跃值
                $activity = (new TaskFactory($this->uid))->getUserActivity($taskStageDetail, $this->uid);
                foreach ($taskStageDetail['reg_json'] as $v){
                    $status = 0;
                    if( $v['is_finish'] == 1 ){
                        $status = 1;
                    }
                    $isReceive = 0;
                    if( $v['is_receive'] == 1 ){
                        $isReceive = 1;
                    }
                    if( $v['bl_or_amount'] > 0 && !$isReceive ){
                        $msg = '活跃值达到{:activity}获得{:amount}';
                        if( $status == 1 ){
                            $bonusList[] = $this->formatData(
                                'task/stage_receive', 'task_stage', ['stage_id' => $taskStageDetail['id'], 'stage' => $v['stage']],
                                '活跃值达到{:activity}', $msg, ['activity' => $activity, 'amount' => Util::i18n_currency($v['bl_or_amount'])],
                                $status, $isReceive
                            );
                        }else{
                            $bonusList[] = $this->formatData(
                                '/layout/task-center', 'task', [],
                                '活跃值达到{:activity}', $msg, ['activity' => $activity, 'amount' => Util::i18n_currency($v['bl_or_amount'])],
                                $status, $isReceive, 'url'
                            );
                        }
                        break;//只显示一个阶段任务奖励，后续的奖励不显示
                    }

                }
            }
        }

        #7、活动
        $actionList = (new ActionFactory($this->uid))->list_run()['data'];
        foreach ($actionList as $v){
            //自动申请或者救援金的不显示
            if( $v['auto_apply'] != 1 && $v['base_type'] != 6 ){
//                $bonusList[] = $this->formatData(
//                    'action/apply', 'action', ['action_id' => $v['id']],
//                    '活动奖励', '完成活动 {:action_name} 领取奖励', ['action_name' => $v['c_title']]
//                );
                $bonusList[] = $this->formatData(
                    '/activity-hall', 'action', [],
                    '活动奖励', '完成活动 {:action_name} 领取奖励', ['action_name' => $v['c_title']],
                    0, 0, 'url', [], 'post', $v['c_img']
                );
            }
        }

        $rsps['data']=$bonusList;
        $rsps['code']=1;
        return $rsps;
    }

    //格式化数据
    private function formatData(
        $url, $type, $params, $title, $content, $contentData = [],
        $status = 0, $isReceive = 0, $urlType = 'api', $extraData = [],
        $method = 'post', $image = ''
    ){
        if( $status == 0 ){
            $isReceive = 0;
        }
        return [
            'url' => $url,
            'type' => $type,
            'url_type' => $urlType,
            'method' => $method,
            'params' => $params,
            'image' => $image,
            'status' => $status,
            'is_receive' => $isReceive,
            'title' => lang($title, $contentData),
            'content' => lang($content, $contentData),
            'extra_data' => $extraData,
        ];
    }

}