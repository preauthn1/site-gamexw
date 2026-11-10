<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\lib\TokenCheck;
use app\common\lib\Util;
use app\common\model\GameBetsModel;
use app\common\model\IntegralMxModel;
use app\common\model\TaskContentModel;
use app\common\model\TaskModel;
use app\common\model\TaskRecordModel;
use app\common\model\TaskStageModel;
use app\common\model\TaskStageRecordModel;
use app\common\model\TaskTypeModel;
use app\common\model\UserAwardCfgsModel;
use app\common\model\UserBankModel;
use app\common\model\UserLoginModel;
use app\common\model\UserLogModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserOnlineModel;
use app\common\model\UserTagModel;
use app\common\model\WuFuCountModel;
use app\common\redis\CommomRedis;
use app\common\service\AgentService;
use app\common\service\BetService;
use app\common\service\MemberService;
use app\common\service\MessageService;
use app\common\service\TaskService;
use app\common\service\WalletService;
use app\common\validate\TaskValidate;
use Carbon\Carbon;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Request;

class TaskFactory{

    private $param;//请求参数
    private $uid;//用户id
    //验证
    public function __construct($uid = 0){
        $this->param=Request::param();
        $this->uid = $uid;
    }

    //任务列表
    public function index_run($typeId = 0){
        $params = $this->param;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $condition = [];
        $condition[] = ['tc.lang', '=', $lang];
        $stageTaskId = 0;
        if( $typeId > 0 ){
            $params['task_type'] = $typeId;
        }
        if( isset($params['task_type']) && $params['task_type'] !== '' ){
            //获取分类数据
            $stageTaskId = TaskTypeModel::where('id', '=', $params['task_type'])->value('stage_id');
            $condition[] = ['t.task_type', '=', $params['task_type']];
        }
        //如果登录就获取用户uid
        if( !$this->uid ){
            $header = request()->header();
            try {
                $token = TokenCheck::run($header);
                if( $token ){
                    $this->uid = $token->uid;
                }
            }catch (\Exception $e){}
        }
        //获取当前货币符号
        $currentSymbol = Util::i18n_currency_no_amount();

        $where_str = [];

        //获取域名或用户对应的代理对应代理条件
        $_whereStr = AgentService::getAgentWhere('receive_agent_id');
        if( $_whereStr ){
            $where_str[] = $_whereStr;
        }

        if ($this->uid > 0) {
            $uinfo = UserModel::where("id", $this->uid)->field("tree,level_id")->find();
            if(isset($uinfo['tree'])){
                $level_id = $uinfo['level_id'];
                $where_str[] = "(receive_level_id=0 or receive_level_id='{$level_id}')";
            }

            $user_tag_id = MemberService::getUserTagId($this->uid);
            $user_tag_id[] = 0;
            $user_tag_id_str = implode(',', $user_tag_id);
            $where_str[] = "receive_tag_id IN ({$user_tag_id_str})";
        } else {
            $where_str[] = "receive_tag_id = 0";
            $where_str[] = "receive_level_id = 0";
        }
        $condition1 = implode(" and ", $where_str);
        if($stageTaskId == 0){
            //获取普通任务
            $condition[] = ['t.task_mode', '=', TaskModel::NORMAL_TASK_MODE];#查询普通任务
            $condition[] = ['t.status', '=', 1];
            $list = $this->getTaskList($condition, $condition1);
        }else{
            //获取阶段任务
            $list = $this->getStageTask($stageTaskId, $this->uid, $condition1);
        }
        //任务分类
        $taskTypeList = TaskTypeModel::field('id,name,icon')->order('sort desc,id desc')->select();

        $rsps['data']=[
            'list' => $list,
            'current_symbol' => $currentSymbol,
            'task_type_list' => $taskTypeList,
        ];
        $rsps['code']=1;
        return $rsps;
    }


    //获取阶段任务
    public function getStageTask($stageId, $uid, $condition1 = []){
        // 获取用户当前活跃度
        $field = 'id,type,reg_json,name';
        $condition = [];
        $condition[] = ['id', '=', $stageId];
        $condition[] = ['status', '=', 1];
        $taskStageDetail = TaskStageModel::field($field)->where($condition)->where($condition1)->find();
        //获取用户活跃值
        $taskStageDetail['activity'] = $this->getUserActivity($taskStageDetail, $uid);
        if (!empty($taskStageDetail) && !empty($taskStageDetail['reg_json'])){
            $reg_json = json_decode($taskStageDetail['reg_json'],true);
            $taskStageDetail['min_activity'] = 0;
            $taskStageDetail['max_activity'] = 0;
            $taskList = [];
            foreach ($reg_json as &$item){
                //取出最大和最小活跃值
                $taskStageDetail['min_activity'] = min($item['min_activity'], $taskStageDetail['min_activity']);
                $taskStageDetail['max_activity'] = max($item['max_activity'], $taskStageDetail['max_activity']);
                //获取对应阶段的任务
                if($taskStageDetail['activity']+1 >= $item['min_activity'] && $taskStageDetail['activity'] <= $item['max_activity']){
                    $_condition = [];
                    $_condition[] = ['stage', '=', $item['stage']];
                    $_condition[] = ['task_mode', '=', TaskModel::STAGE_TASK_MODE];
                    $_condition[] = ['stage_id', '=', $taskStageDetail['id']];
                    $_condition[] = ['status', '=', 1];
                    //获取任务列表
                    $taskList = $this->getTaskList($_condition);
                }
                //是否完成任务
                $item['is_finish'] = 0;
                if( $taskStageDetail['activity'] >= $item['max_activity'] ){
                    $item['is_finish'] = 1;
                    $this->createTaskStageRecord($taskStageDetail, $uid, $item['stage'], $item);
                }
                //判断是否领取
                $item['is_receive'] = $this->judgeTaskStageIsReceive($taskStageDetail, $item['stage'], $uid);
            }
            $taskStageDetail['reg_json'] = $reg_json;
            $taskStageDetail['task_list'] = $taskList;
            //获取阶段任务开始时间、结束时间、剩余时间
            if( $taskStageDetail->type = TaskModel::TODAY_TYPE ){
                $taskStageDetail['start_time'] = strtotime('today');
                $taskStageDetail['end_time'] = strtotime('tomorrow');
            }else{
                $taskStageDetail['start_time'] = strtotime( "previous monday" );
                $taskStageDetail['end_time'] = strtotime(date('Y-m-d 00:00:00', strtotime('next week')));
            }
            $taskStageDetail['time'] = $taskStageDetail['end_time'] - $taskStageDetail['start_time'] ?: 0;
        }

        return $taskStageDetail;
    }

    //获取用户活跃值
    public function getUserActivity($taskStageDetail, $uid = 0){
        if( !$taskStageDetail ){
            return 0;
        }
        //根据类型获取对应时间的活跃值
        if( $taskStageDetail->type == TaskModel::TODAY_TYPE ){
            $time = strtotime('today');
        }elseif( $taskStageDetail->type == TaskModel::WEEK_TYPE ) {
            $time = strtotime( "previous monday" );
        }else{
            return 0;
        }
        $taskSql = TaskModel::where('stage_id', '=', $taskStageDetail->id)->field('id')->buildSql();
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['create_time', '>=', $time];
        $condition[] = ['receive_status', '=', TaskRecordModel::RECEIVED_STATUS];
        $condition1 = 'task_id in '.$taskSql;
        return TaskRecordModel::where($condition)->where($condition1)->sum('activity');
    }

    //获取任务列表
    public function getTaskList($condition, $condition1 = []){
        $field = 't.id,t.task_type,t.type,t.task_condition,t.min_bet,t.cumulative_recharge,t.online_time,t.cumulative_bet,t.game_online_time,t.min_recharge,t.receive_level_id,t.multiple,t.background_img,t.task_num,t.reward_type,t.amount_or_integral,tc.title,tc.introduce,t.savings,t.activity,t.w_count,t.invite_people,t.start_time,t.end_time,t.task_mode';
        $list = TaskModel::alias('t')
            ->join('task_content tc', 'tc.task_id=t.id', 'left')
            ->where($condition)->where($condition1)->field($field)
            ->order('t.sort desc, id desc')
            ->select();
        foreach ($list as $v){
            //获取用户完成任务数量
            $finishNum = $this->getUserFinishTaskNumByTaskDetail($v);
            $v->finish_num = min($finishNum, $v->task_num);
            //是否完成任务
            $v->is_finsh = 0;
            if( $v->finish_num >= $v->task_num ){
                $v->is_finsh = 1;
                //生成未领取记录
                $this->createTaskRecord($v, $this->uid);
            }
            //判断是否领取
            $v->is_receive = $this->judgeTaskIsReceive($v);
            //处理金额两位小数
            $v->amount_or_integral = round($v->amount_or_integral, 2);
            $v->cumulative_bet = round($v->cumulative_bet, 2);
            $v->cumulative_recharge = round($v->cumulative_recharge, 2);
            $v->min_bet = round($v->min_bet, 2);
            $v->min_recharge = round($v->min_recharge, 2);
            $v->multiple = round($v->multiple, 2);
            $v->savings = round($v->savings, 2);
        }
        return $list;
    }

    //根据任务数据返回用户完成任务的数量
    public function getUserFinishTaskNumByTaskDetail($taskDetail, $uid = 0, $isDatabase = false){
        if( !$uid ){
            $uid = $this->uid;
        }
        if(!$uid || !$taskDetail)return 0;
        $apiRedis=new ApiRedis();
        $key = 'user_finish_task_num_'.$uid.'_'.$taskDetail->id;
        if( $apiRedis->has($key) && !$isDatabase ){
//            return $apiRedis->get($key);
        }
        $timeData = $this->getTimeByType($taskDetail);
        $startTime = $timeData['start_time'];
        $endTime = $timeData['end_time'];
        //获取任务条件数组
        $taskCondition = explode(',', $taskDetail->task_condition);
        //循环任务条件数组
        $taskFinishNumArr = [];
        foreach ($taskCondition as $v){
            $condition = [];
            switch ($v){
                case TaskModel::MIN_BET_TASK_CONDITION:
                    //最低下注
                    $condition[] = ['gb_price', '>=', $taskDetail->min_bet];
                    $condition[] = ['gb_uid', '=', $uid];
                    $condition[] = ['gb_winlose', '<>', 0];#不是无效就可以
                    //条件转化 将gb_bettime 字段条件转换到gb_id 上增加查询效率
                   /* $start_condition = $condition;
                    $start_condition[] = ['gb_bettime', '>=', $startTime];
                    $start_temp = GameBetsModel::where($start_condition)->field('gb_id')->order('gb_id asc')->find();
                    $start_id = 0;
                    if(!empty($start_temp)){
                        $start_id = $start_temp['gb_id'];
                    }
                    $start_condition = $condition;
                    $start_condition[] = ['gb_bettime', '<=', $endTime];
                    $start_temp = GameBetsModel::where($start_condition)->field('gb_id')->order('gb_id desc')->find();
                    $end_id = 0;
                    if(!empty($start_temp)){
                        $end_id = $start_temp['gb_id'];
                    }
                    if((!empty($start_id))||(!empty($end_id))){
                        $condition[] = ['gb_id', 'between', [$start_id, $end_id]];
                    }else{
                        $condition[] = ['gb_bettime', 'between', [$startTime, $endTime]];
                    }*/
                    $betWhere = BetService::getGbidByTime($startTime, $endTime)['sql'];
//                    $condition[] = ['gb_bettime', 'between', [$startTime, $endTime]];
                    $taskFinishNumArr[] = GameBetsModel::where($condition)->where($betWhere)->count();
                    break;
                case TaskModel::MIN_RECHARGE_TASK_CONDITION:
                    //最低充值
                    $condition[] = ['arrival_price', '>=', $taskDetail->min_recharge];
                    $condition[] = ['uid', '=', $uid];
                    $condition[] = ['status', 'in', [3, 4]];#充值通过的状态
                    $condition[] = ['add_time', 'between', [$startTime, $endTime]];
                    $taskFinishNumArr[] = UserOfflineModel::where($condition)->count();
                    break;
                case TaskModel::MIN_MULTIPLE_TASK_CONDITION:
                    //最低倍数
                    $condition1 = 'gb_uid = '.$uid.' and gb_winprice > gb_price and (gb_winprice-gb_price)/gb_price > '.$taskDetail->multiple;
                    $condition[] = ['gb_winlose', '=', 3];#赢的状态
                    //条件转化 将gb_bettime 字段条件转换到gb_id 上增加查询效率
                    /*$start_condition = $condition;
                    $start_condition[] = ['gb_bettime', '>=', $startTime];
                    $start_temp = GameBetsModel::where($start_condition)->where($condition1)->field('gb_id')->order('gb_id asc')->find();
                    $start_id = 0;
                    if(!empty($start_temp)){
                        $start_id = $start_temp['gb_id'];
                    }
                    $start_condition = $condition;
                    $start_condition[] = ['gb_bettime', '<=', $endTime];
                    $start_temp = GameBetsModel::where($start_condition)->where($condition1)->field('gb_id')->order('gb_id desc')->find();
                    $end_id = 0;
                    if(!empty($start_temp)){
                        $end_id = $start_temp['gb_id'];
                    }
                    if((!empty($start_id))||(!empty($end_id))){
                        $condition[] = ['gb_id', 'between', [$start_id, $end_id]];
                    }else{
                        $condition[] = ['gb_bettime', 'between', [$startTime, $endTime]];
                    }*/
                    $betWhere = BetService::getGbidByTime($startTime, $endTime)['sql'];
//                    $condition[] = ['gb_bettime', 'between', [$startTime, $endTime]];
                    $taskFinishNumArr[] = GameBetsModel::where($condition)->where($betWhere)->where($condition1)->count();
                    break;
                case TaskModel::CUMULATIVE_RECHARGE_TASK_CONDITION:
                    //累计充值
                    $condition[] = ['uid', '=', $uid];
                    $condition[] = ['status', 'in', [3, 4]];#充值通过的状态
                    $condition[] = ['add_time', 'between', [$startTime, $endTime]];
                    $arrivalPrice = UserOfflineModel::where($condition)->sum('arrival_price');
                    $taskFinishNumArr[] = $arrivalPrice >= $taskDetail->cumulative_recharge ? 1 : 0;
                    break;
                case TaskModel::ONLINE_TIME_TASK_CONDITION:
                    //在线时长
                    $condition[] = ['uid', '=', $uid];
                    $condition[] = ['datetime', 'between', [$startTime, $endTime]];
                    $condition[] = ['type', '=', 'online_time'];
                    $onlineTime = UserLogModel::where($condition)->sum('data');
                    $taskFinishNumArr[] = ($onlineTime / 60) > $taskDetail->online_time ? 1 : 0;
                    break;
                case TaskModel::CUMULATIVE_BET_TASK_CONDITION:
                    //累计下注
                    $condition[] = ['gb_uid', '=', $uid];
//                    $condition[] = ['gb_bettime', 'between', [$startTime, $endTime]];
                    $betWhere = BetService::getGbidByTime($startTime, $endTime)['sql'];
                    $price = GameBetsModel::where($condition)->where($betWhere)->sum('gb_price');
                    $taskFinishNumArr[] = $price >= $taskDetail->cumulative_bet ? 1 : 0;
                    break;
                case TaskModel::GAME_ONLINE_TIME_TASK_CONDITION:
                    //游戏时长
                    $condition[] = ['uid', '=', $uid];
                    $condition[] = ['datetime', 'between', [$startTime, $endTime]];
                    $condition[] = ['type', '=', 'game_time'];
                    $onlineTime = UserLogModel::where($condition)->sum('data');
                    $taskFinishNumArr[] = ($onlineTime / 60) > $taskDetail->game_online_time ? 1 : 0;
                    break;
                case TaskModel::INVITE_PEOPLE_TASK_CONDITION:
                    //邀请人数
                    $inviteNum = UserFactory::getUserInvitePerson($uid, $startTime, $endTime);
                    $taskFinishNumArr[] =  $inviteNum >= $taskDetail->invite_people ? 1 : 0;
                    break;
                case TaskModel::APP_LOGIN_TASK_CONDITION:
                    //APP登录
                    $_condition = [];
                    $_condition[] = ['uid', '=', $uid];
                    $_condition[] = ['login_time', 'between', [$startTime, $endTime]];
                    $_condition[] = ['device_type', 'in', ['IPHONE', 'IPHONE', 'ANDROID']];
                    $taskFinishNumArr[] = UserLoginModel::where($_condition)->count()?:0;
                    break;
                case TaskModel::ADD_BANK_TASK_CONDITION:
                    //添加银行卡
                    $_condition = [];
                    $_condition[] = ['uid', '=', $uid];
                    $taskFinishNumArr[] = UserBankModel::where($_condition)->count()?:0;
                    break;
                default :
                    return 0;
            }
        }
        //任务数量取所有条件完成数量中最小的，（例如最低下注完成10，最低充值完成5，任务完成数量为5）
        $taskFinishNum = min($taskFinishNumArr);

        //设置缓存五分钟
        $expireTime = 300;
        $laveTime = $endTime - time();
        if( $laveTime < $expireTime ){
            //如果十二点距离当前时间不足5分钟，设置为剩下时间
            $expireTime = $laveTime;
        }
        $apiRedis->set($key, $taskFinishNum, $expireTime);
        return $taskFinishNum;
    }

    //领取任务奖励
    public function receive(){
        $params = $this->param;
        try {
            validate(TaskValidate::class)->scene('receive')->check($params);
        }catch (ValidateException $e){
            ajax_return($e->getError());
        }
        // 判断是否禁止参与任务
        if (MemberService::tagBan($this->uid, 'is_task')) {
            ajax_return("禁止参与任务，请联系客服", 0);
        }
        //验证实名认证
        if( !MemberService::judgeUserIsCanReceiveDiscount($this->uid) ){
            ajax_return('请先实名后再参与');
        }
        //获取任务数据
        $taskDetail = TaskModel::where('id', '=', $params['task_id'])->where('status', '=', 1)->find();
        if(!$taskDetail)ajax_return('任务数据不存在');
        //获取用户数据
        $userDetail = UserModel::uinfo($this->uid);
        if(!$userDetail)ajax_return('用户异常，请重新登录');

        //双钱包判断当前用户是否可以重复领取优惠
        if( !WalletService::judgeUserIsRePeatReceive(0, $userDetail) && $taskDetail->reward_type == TaskModel::BALANCE_REWARD_TYPE ){
            ajax_return('你的账户还存在彩金，请使用完后再来领取');
        }
        //判断VIP等级
        if( $taskDetail->receive_level_id != 0 && $userDetail->level_id != $taskDetail->receive_level_id ){
            ajax_return('领取会员等级不满足');
        }
        //判断标签
        if( $taskDetail->receive_tag_id != 0 ){
            $userTags = MemberService::getUserTagId($this->uid);
            if( !in_array($taskDetail->receive_tag_id, $userTags) ){
                ajax_return('用户标签条件不满足');
            }
        }
        //获取任务完成数量
        $taskFinishNum = $this->getUserFinishTaskNumByTaskDetail($taskDetail);
        if( $taskFinishNum < $taskDetail->task_num ){
            ajax_return('任务未完成');
        }
        //判断是否领取
        if( $this->judgeTaskIsReceive($taskDetail) ){
            ajax_return('已领取，无需重复领取');
        }
        //领取奖励
        return $this->handleReceiveTaskReward($taskDetail);
    }

    //判断任务是否领取（1已领取，0未领取）
    public function judgeTaskIsReceive($taskDetail, $isDatabase = false, $uid = 0){
        if( !$uid ){
            $uid = $this->uid;
        }
        if(!$uid)return 0;
        $apiRedis=new ApiRedis();
        //先从缓存判断
        $key = 'judge_user_is_receive_task_v3_'.$uid.'_'.$taskDetail->id;
        if( $apiRedis->has($key) && !$isDatabase ){
            return $apiRedis->get($key);
        }
        $timeData = $this->getTimeByType($taskDetail);
        $startTime = $timeData['start_time'];
        $endTime = $timeData['end_time'];

        //缓存没有从数据库判断
        $condition = [];
        $condition[] = ['task_id', '=', $taskDetail->id];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        $condition[] = ['receive_status', '=', TaskRecordModel::RECEIVED_STATUS];
        if( TaskRecordModel::where($condition)->value('id') ){
            $redisValue = 1;
        }else{
            $redisValue = 0;
        }
        //设置缓存为12点距离当前时间的秒数
        $apiRedis->set($key, $redisValue, $endTime - time());
        return $redisValue;
    }

    /**
     * 执行领取任务奖励
     * @param $taskDetail
     * @return \think\response\Json|void
     * @throws \app\common\exception\MessageException
     */
    private function handleReceiveTaskReward($taskDetail){
        //获取任务名称
        $lang = Lang::getLangSet();
        $condition = [];
        $condition[] = ['task_id', '=', $taskDetail->id];
        $condition[] = ['lang', '=', $lang];
        $taskTitle = TaskContentModel::where($condition)->value('title');
        //获取默认币种
        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();
        $currency = $currency['defa_data']['currency_code'];

        //根据时间判断改时间范围内是否完成的
        $timeData = $this->getTimeByType($taskDetail);
        $startTime = $timeData['start_time'];
        $endTime = $timeData['end_time'];
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
        $condition[] = ['task_id', '=', $taskDetail->id];
        $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        $taskRecordDetail = TaskRecordModel::where($condition)->find();

        Db::startTrans();
        try {
            //生成领取记录
            if( $taskRecordDetail ){
                $taskRecordDetail->save(['receive_status' => TaskRecordModel::RECEIVED_STATUS]);
                $result = $taskRecordDetail;
            }else{
                $result = TaskRecordModel::create([
                    'uid' => $this->uid,
                    'task_id' => $taskDetail->id,
                    'type' => $taskDetail->type,
                    'task_condition' => $taskDetail->task_condition,
                    'task_data' => json_encode($taskDetail),
                    'task_num' => $taskDetail->task_num,
                    'finish_num' => $this->getUserFinishTaskNumByTaskDetail($taskDetail),
                    'reward_type' => $taskDetail->reward_type,
                    'reward_amount_or_integral' => $taskDetail->reward_type == TaskModel::WUFU_REWARD_TYPE ? $taskDetail->w_count : $taskDetail->amount_or_integral,
                    'savings' => $taskDetail->savings,
                    'activity'=> $taskDetail->activity,
                    'task_mode'=> $taskDetail->task_mode,
                    'receive_status'=> TaskRecordModel::RECEIVED_STATUS,
                ]);
            }
            $msgTitle = '任务奖励';
            //任务奖励类型
            switch ($taskDetail->reward_type){
                case TaskModel::BALANCE_REWARD_TYPE:
                    //余额奖励
                    UserModel::cz_balance($this->uid, $taskDetail->amount_or_integral, "+", true, "任务奖励", 11, $taskDetail->water_type == 0 ? $taskDetail->savings : 0, 1, $result->id, true, 101);
                    $msgContent = '任务奖励已到账, 任务: {:title}, 金额 {:price} {:currency}';
                    $msgData = ['price'=>$taskDetail->amount_or_integral,'currency'=>$currency, 'title' => $taskTitle];
                    //判断流水是否是指定流水
                    if( $taskDetail->water_type != 0 ){
                        $fee = round($taskDetail->amount_or_integral * $taskDetail->savings, 2);
                        UserAwardCfgsModel::publicCreateData(
                            $this->uid, $taskDetail->amount_or_integral, $taskDetail->savings, $fee,
                            UserAwardCfgsModel::UAC_TYPE_TASK, $taskDetail->water_type,
                            $taskDetail->water_type_ids, $taskDetail->id, $taskDetail->wallet_water_type
                        );
                    }
                    break;
                case TaskModel::INTEGRAL_REWARD_TYPE:
                    //积分奖励
                    IntegralMxModel::cz_mx($this->uid,1,$taskDetail->amount_or_integral,'任务奖励');
                    $msgContent = '任务奖励已到账, 任务: {:title}, 积分 {:integral}';
                    $msgData = ['integral' => $taskDetail->amount_or_integral, 'title' => $taskTitle];
                    break;
                case TaskModel::WUFU_REWARD_TYPE:
                    //五福奖励
                    $saveAll = [];
                    $time = time();
                    for ($i = 0; $i < $taskDetail->w_count; $i++){
                        $arr = [
                            'uid' => $this->uid,
                            'type' => WuFuCountModel::TYPE_TASK,
                            'w_id' => $taskDetail->w_id,
                            'card_id' => 0,
                            'create_time' => $time,
                            'update_time' => $time,
                        ];
                        $saveAll[] = $arr;
                    }
                    //赠送卡片
                    (new WuFuCountModel())->saveAll($saveAll);
                    //发送消息
//                    $lang = Lang::getLangSet();
//                    $msgContent = '恭喜您完成任务【{:task_name}】，获得{:count}次刮奖机会';
//                    $msgData = ['task_name' => $taskTitle, 'count' => $taskDetail->t_count];
                    //五福不提示站内消息直接领取刷新
                    //刷新是否领取缓存
                    $this->judgeTaskIsReceive($taskDetail, true);
                    Db::commit();
                    return Respons::run(1, [], '领取成功');
//                    UserMsgModel::add_msg($this->uid, '五福刮奖', $msgContent, 23, $result->id, $msgData, $lang);
                    break;
                default:
                    Db::rollback();
                    ajax_return('系统错误');
            }
            UserMsgModel::add_msg($this->uid, $msgTitle, $msgContent, 17, $result->id, $msgData, $lang);
            //刷新是否领取缓存
            $this->judgeTaskIsReceive($taskDetail, true);
            Db::commit();
            return Respons::run(1, [], '领取成功');
        }catch (\Exception $e){
            Db::rollback();
            ajax_return('系统错误');
        }
    }

    // 领取阶段任务奖励
    public function stage_receive(){
        $params = $this->param;
        try {
            validate(TaskValidate::class)->scene('stage_receive')->check($params);
        }catch (ValidateException $e){
            ajax_return($e->getError());
        }
        // 判断是否禁止参与任务
        if (MemberService::tagBan($this->uid, 'is_task')) {
            ajax_return("禁止参与任务，请联系客服", 0);
        }
        //验证实名认证
        if( !MemberService::judgeUserIsCanReceiveDiscount($this->uid) ){
            ajax_return('请先实名后再参与');
        }
        //获取任务数据
        $taskstageDetail = TaskStageModel::where('id', '=', $params['stage_id'])->find();
        if(!$taskstageDetail)ajax_return('任务数据不存在');
        //获取用户数据
        $userDetail = UserModel::uinfo($this->uid);
        if(!$userDetail)ajax_return('用户异常，请重新登录');

        //双钱包判断当前用户是否可以重复领取优惠
        if( !WalletService::judgeUserIsRePeatReceive(0, $userDetail) ){
            ajax_return('你的账户还存在彩金，请使用完后再来领取');
        }
        //判断VIP等级
        if( $taskstageDetail->receive_level_id != 0 && $userDetail->level_id != $taskstageDetail->receive_level_id ){
            ajax_return('领取会员等级不满足');
        }
        //判断标签
        if( $taskstageDetail->receive_tag_id != 0 ){
            $userTags = MemberService::getUserTagId($this->uid);
            if( !in_array($taskstageDetail->receive_tag_id, $userTags) ){
                ajax_return('用户标签条件不满足');
            }
        }

        // 判断阶段信息，阶段信息格式化
        $regJson = json_decode($taskstageDetail['reg_json'], true);
        $stage = [];
        foreach ($regJson AS $item){
            if ($item['stage'] == $params['stage']){
                $stage = $item;
                break;
            }
        }
        if (empty($stage)){
            ajax_return('所选阶段不存在');
        }else{
            $taskstageDetail->reg_json = $stage;
        }
        // 判断活跃度是否满足
        $activity = $this->getUserActivity($taskstageDetail, $this->uid);
        if ($activity < $taskstageDetail->reg_json['max_activity']){
            ajax_return('未达到领取条件');
        }
        //判断是否领取
        if ( $this->judgeTaskStageIsReceive($taskstageDetail, $params['stage'], $this->uid) ){
            ajax_return('已领取，无需重复领取');
        }
        $taskstageDetail->stage = $params['stage'];
        //领取奖励
        return $this->handleStageReceiveTaskReward($taskstageDetail);
    }

    //判断阶段任务是否领取
    public function judgeTaskStageIsReceive($taskStageDetail, $stage, $uid, $isDatabase = false){
        if( !$taskStageDetail || !$stage || !$uid ){
            return 0;
        }
        //根据类型获取对应时间的活跃值
        $timeData = $this->getTimeByType($taskStageDetail);
        $startTime = $timeData['start_time'];
        $endTime = $timeData['end_time'];

        $apiRedis=new ApiRedis();
        //先从缓存判断
        $key = 'judge_user_is_receive_task_stage_v3_'.$this->uid.'_'.$taskStageDetail->id.'_'.$stage;
        if( $apiRedis->has($key) && !$isDatabase ){
            return $apiRedis->get($key);
        }
        $tomorrowTime = strtotime('tomorrow');
        //缓存没有从数据库判断
        $condition = [];
        $condition[] = ['stage_id', '=', $taskStageDetail->id];
        $condition[] = ['uid', '=', $this->uid];
        $condition[] = ['stage', '=', $stage];
        $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        $condition[] = ['receive_status', '=', TaskStageRecordModel::RECEIVED_STATUS];
        if( TaskStageRecordModel::where($condition)->value('id') ){
            $redisValue = 1;
        }else{
            $redisValue = 0;
        }
        //设置缓存为12点距离当前时间的秒数
        $apiRedis->set($key, $redisValue, $tomorrowTime - time());
        return $redisValue;
    }

    /**
     * 执行领取任务奖励
     * @param $taskDetail
     * @return \think\response\Json|void
     * @throws \app\common\exception\MessageException
     */
    private function handleStageReceiveTaskReward($taskstageDetail){
        //获取默认币种
        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();
        $currency = $currency['defa_data']['currency_code'];
        //根据时间判断改时间范围内是否完成的
        $timeData = $this->getTimeByType($taskstageDetail);
        $startTime = $timeData['start_time'];
        $endTime = $timeData['end_time'];
        $condition = [];
        $condition[] = ['stage_id', '=', $taskstageDetail->id];
        $condition[] = ['uid', '=', $this->uid];
        $condition[] = ['stage', '=', $taskstageDetail->stage];
        $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        $taskRecordDetail = TaskRecordModel::where($condition)->find();
        Db::startTrans();
        try {

            //生成领取记录
            if( $taskRecordDetail ){
                $result = $taskRecordDetail->save(['receive_status' => TaskRecordModel::RECEIVED_STATUS]);
            }else{
                $result = TaskRecordModel::create([
                    'uid' => $this->uid,
                    'stage_id' => $taskstageDetail->id,
                    'stage' => $taskstageDetail->stage,
                    'activity' => $taskstageDetail->reg_json['max_activity'],
                    'reward_amount' => $taskstageDetail->reg_json['bl_or_amount'],
                    'reward_integral' => $taskstageDetail->reg_json['integral_amount'],
                    'savings' => $taskstageDetail->reg_json['savings'],
                    'create_time' => time(),
                    'update_time' => time(),
                    'receive_status'=> TaskRecordModel::RECEIVED_STATUS,
                ]);
            }
            if(!$result) {
                Db::rollback();
                ajax_return('系统错误');
            }
            $msgTitle = '阶段任务奖励';
            if( $taskstageDetail->reg_json['bl_or_amount'] > 0 ){
                UserModel::cz_balance($this->uid, $taskstageDetail->reg_json['bl_or_amount'], "+", true, "阶段任务奖励", 11, $taskstageDetail->reg_json['savings'], 1, $result->id, true, 101);
                $msgContent = lang('完成任务：').$taskstageDetail->name.'，'.lang('奖励金额 {:price} {:currency}');
                $msgData = ['price'=>$taskstageDetail->reg_json['bl_or_amount'],'currency'=>$currency];
                UserMsgModel::add_msg($this->uid, $msgTitle, $msgContent, 17, $result->id, $msgData, $taskstageDetail->lang);
            }
            if($taskstageDetail->reg_json['integral_amount'] > 0){
                //积分奖励
                IntegralMxModel::cz_mx($this->uid,1,$taskstageDetail->reg_json['integral_amount'],'阶段任务奖励');
                $msgContent = lang('完成任务：').$taskstageDetail->name.'，'.lang('奖励积分 {:integral}');
                $msgData = ['integral' => $taskstageDetail->reg_json['integral_amount']];
                UserMsgModel::add_msg($this->uid, $msgTitle, $msgContent, 17, $result->id, $msgData, $taskstageDetail->lang);
            }
            //刷新是否领取缓存
            $this->judgeTaskStageIsReceive($taskstageDetail, $taskstageDetail->stage, $this->uid, true);
            Db::commit();
            return Respons::run(1, [], '领取成功');
        }catch (\Exception $e){
            echo $e->getMessage();
            Db::rollback();
            ajax_return('系统错误');
        }
    }

    //推送消息给完成任务的用户
    public function pushMessageToFinishTaskUser($uid = 0, $isTaskHandle = false){
        if( $isTaskHandle ){
            $taskHandleKey = 'task_uid_handle_key';
            Cache::store('redis')->rpush($taskHandleKey, $uid);
            return true;
        }
        $apiRedis=new ApiRedis();
        //获取用户数据
        $userDetail = UserModel::where("id", $uid)->field('tree,level_id')->find();
        if( !$userDetail ){
            return false;
        }
        //获取任务列表
        $taskList = TaskModel::where('id', '>', 0)->select();
        foreach ($taskList as $_v){
            //两小时内推送过则不再推送
            $pushUserKey = 'push_user_finish_key_v3_'.$uid.'_'.$_v['id'];
            if( $apiRedis->has($pushUserKey) ){
                continue;
            }
            //判断代理线
            $userAgentIds = array_filter(explode('/', $userDetail->tree));
            $judgeAgentIdResult = false;
            foreach (explode(',', $_v['receive_agent_id']) as $__v){
                if( in_array($__v, $userAgentIds) ){
                    $judgeAgentIdResult = true;
                }
            }
            if( $_v['receive_agent_id'] && !$judgeAgentIdResult ){
                continue;
            }
            //判断标签
            $userLevelId = $userDetail->level_id;
            if( $_v['receive_level_id'] != 0 && $_v['receive_level_id'] != $userLevelId){
                continue;
            }
            //判断标签
            $user_tag_id = MemberService::getUserTagId($uid);
            $user_tag_id[] = 0;
            if( !in_array($_v['receive_tag_id'], $user_tag_id) ){
                continue;
            }
            //判断用户是否完成该任务
            //获取用户完成任务数量
            $finishNum = $this->getUserFinishTaskNumByTaskDetail($_v, $uid, true);
            $finishNum = min($finishNum, $_v['task_num']);
            if( $finishNum >= $_v['task_num'] && !$this->judgeTaskIsReceive($_v, true, $uid) ){
                //完成任务且没有领取给用户发送没有领取通知
                MessageService::sendTaskFinishMessageNotify( $uid, 'task', $finishNum);
//                //完成任务且没有领取给用户发送没有领取通知
//                MessageService::sendMsg('您有新的任务完成', $uid, 'task', 5, '消息通知', env('message_reset_time', 0));
                //设置缓存两小时内不再推送
                $apiRedis->set($pushUserKey, 1, 3600 * 2);
                //生成了未领取记录
                $this->createTaskRecord($_v, $uid);
            }
        }
        return true;
    }

    //根据类型获取时间
    public function getTimeByType($taskDetail){
        $startTime = 1;
        $endTime = 99999999999;
        switch ($taskDetail->type){
            case TaskModel::ALL_TYPE:
                //所有

                break;
            case TaskModel::TODAY_TYPE:
                //每日
                $startTime = Carbon::now()->startOfDay()->timestamp;
                $endTime = Carbon::now()->endOfDay()->timestamp;
                break;
            case TaskModel::WEEK_TYPE:
                //每周
                $startTime = Carbon::now()->startOfWeek()->timestamp;
                $endTime = Carbon::now()->endOfWeek()->timestamp;
                break;
            case TaskModel::CUSTOM_TYPE:
                if($taskDetail->start_time && $taskDetail->end_time){
                    //自定义
                    $startTime = $taskDetail->start_time;
                    $endTime = $taskDetail->end_time;
                }else{
                    //没有开始和结束时间则结束时间为任务创建得时间
                    $endTime = time();
                }
                break;
        }
        return ['start_time' => $startTime, 'end_time' => $endTime];
    }

    //生成任务未领取记录
    public function createTaskRecord($taskDetail, $uid){
        $timeData = $this->getTimeByType($taskDetail);
        $startTime = $timeData['start_time'];
        $endTime = $timeData['end_time'];
        $apiRedis = new ApiRedis();
        //缓存判断如果已经存在直接返回
        $redisKey = 'judge_is_create_task_record_'.$uid.'_'.$taskDetail->id.'_'.$startTime.'_'.$endTime;
        if( $apiRedis->has($redisKey) ){
            return true;
        }
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['task_id', '=', $taskDetail->id];
        $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        $taskRecordDetail = TaskRecordModel::where($condition)->find();
        if( $taskRecordDetail ){
            //存在改记录返回
            return true;
        }
        TaskRecordModel::create([
            'uid' => $uid,
            'task_id' => $taskDetail->id,
            'type' => $taskDetail->type,
            'task_condition' => $taskDetail->task_condition,
            'task_data' => json_encode($taskDetail),
            'task_num' => $taskDetail->task_num,
            'finish_num' => $this->getUserFinishTaskNumByTaskDetail($taskDetail),
            'reward_type' => $taskDetail->reward_type,
            'reward_amount_or_integral' => $taskDetail->reward_type == TaskModel::WUFU_REWARD_TYPE ? $taskDetail->w_count : $taskDetail->amount_or_integral,
            'savings' => $taskDetail->savings,
            'activity'=> $taskDetail->activity,
            'task_mode'=> $taskDetail->task_mode,
            'receive_status'=> TaskRecordModel::NO_RECEIVE_STATUS,
        ]);
        //生成缓存
        $expireTime = Carbon::now()->endOfWeek()->timestamp - time();
        $apiRedis->set($redisKey, true, $expireTime);
        return true;
    }
    //生成任务未领取记录
    public function createTaskStageRecord($taskStageDetail, $uid, $stage, $stageData){
        $timeData = $this->getTimeByType($taskStageDetail);
        $startTime = $timeData['start_time'];
        $endTime = $timeData['end_time'];
        $apiRedis = new ApiRedis();
        //缓存判断如果已经存在直接返回
        $redisKey = 'judge_is_create_task_stage_record_'.$uid.'_'.$taskStageDetail->id.'_'.$startTime.'_'.$endTime;
        if( $apiRedis->has($redisKey) ){
            return true;
        }
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['task_id', '=', $taskStageDetail->id];
        $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        $taskRecordDetail = TaskRecordModel::where($condition)->find();
        if( $taskRecordDetail ){
            //存在改记录返回
            return true;
        }
        TaskStageRecordModel::create([
            'uid' => $this->uid,
            'stage_id' => $taskStageDetail->id,
            'stage' => $stage,
            'activity' => $stageData['max_activity'],
            'reward_amount' => $stageData['bl_or_amount'],
            'reward_integral' => $stageData['integral_amount'],
            'savings' => $stageData['savings'],
            'create_time' => time(),
            'update_time' => time(),
            'receive_status'=> TaskRecordModel::NO_RECEIVE_STATUS,
        ]);
        //生成缓存
        $expireTime = Carbon::now()->endOfWeek()->timestamp - time();
        $apiRedis->set($redisKey, true, $expireTime);
        return true;
    }

}