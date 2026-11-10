<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\IntegralMxModel;
use app\common\model\PddNewsModel;
use app\common\model\SignDayModel;
use app\common\model\SignModel;
use app\common\model\SignNewsModel;
use app\common\model\SignRecordModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\model\UserOfflineModel;
use app\common\redis\CommomRedis;
use app\common\service\AgentService;
use app\common\service\MemberService;
use app\common\service\MessageService;
use app\common\service\WalletService;
use app\common\validate\SignValidate;
use Carbon\Carbon;
use think\facade\Db;
use think\facade\Lang;

class SignFactory {

    //获取签到活动
    public function getSignActivity($uid){
        //获取默认币种
        $currency = Util::i18n_currency_no_amount();
        //先获取满足条件的签到活动
        $visible = ['id', 'total_amount', 'total_integral', 'day_list', 'surplus_sign_count', 'introduce', 'currency', 'new_date', 'current_day', 'sign_day', 'min_bet', 'min_recharge', 'current_recharge', 'current_bet','reward_total_amount'];
        $signDetail = $this->getNowSignActivity($uid);
        if( !$signDetail ){
            return ['code' => 1,'msg' => 'success', 'data' => []];
        }
        $signDetail->surplus_sign_count = $this->getWeekAddSignCount($signDetail, $uid);
        $signDetail->currency = $currency;
        //当天充值
        $condition = [];
        $condition[] = ['status', 'in', '3,4'];
        $condition[] = ['add_time', 'between', [strtotime('today'), time()]];
        $condition[] = ['uid', '=', $uid];
        $signDetail->current_recharge = round(UserOfflineModel::where($condition)->sum('arrival_price'), 2);
        //当天下注
        $signDetail->current_bet = MemberService::getUserFinishWater($uid, [['su_dt', '=', date('Ymd')]]);
        $signDetail = $signDetail->visible($visible);
        //处理金额和积分两位小数
        $signDetail->min_bet = round($signDetail->min_bet, 2);
        $signDetail->min_recharge = round($signDetail->min_recharge, 2);
        //累计签到获得金额
        $signDetail->reward_total_amount = SignRecordModel::where(['uid' => $uid])->sum('reward_amount');
        return ['code' => 1,'msg' =>'success', 'data' => $signDetail];
    }

    //用户补签
    public function addSign($uid){
        $params = request()->param();
        //获取签到数据
        $signDetail = SignModel::where('id', '=', $params['sign_id'])->find();
        if( !$signDetail ){
            ajax_return('签到活动不存在');
        }
        //查看签到对应天数数据
        $condition = [];
        $condition[] = ['sign_id', '=', $signDetail->id];
        $condition[] = ['day', '=', $params['day']];
        $signDayDetail = SignDayModel::where($condition)->find();
        if( !$signDayDetail ){
            ajax_return('签到数据不存在');
        }
        //验证数据
        $result = $this->_validate($params, $uid, $signDetail, $signDayDetail);
        $lastDate = $result['date'];
        $date = $this->dateAddDay($lastDate);
        $amount = $result['amount'];
        //查看当天状态
        $_result = $this->getSignDayStatus($uid, $signDetail, $signDayDetail, $lastDate);
        if( $_result['status'] != 0 ){
            ajax_return('该天已签到，无需补签');
        }
        //获取可补签次数
        if( $this->getWeekAddSignCount($signDetail, $uid) < 1 ){
            ajax_return('本周补签次数已用完');
        }
        //执行补签
        return $this->handleSign($uid, $signDetail, $signDayDetail, $amount, $date, false);
    }

    //用户签到
    public function userSign($uid, $params = [], $isJudge = false){
        $params = $params ?: request()->param();
        //获取签到数据
        $signDetail = SignModel::where('id', '=', $params['sign_id'])->find();
        if( !$signDetail ){
            ajax_return('签到活动不存在');
        }
        //查看签到对应天数数据
        $condition = [];
        $condition[] = ['sign_id', '=', $signDetail->id];
        $condition[] = ['day', '=', $params['day']];
        $signDayDetail = SignDayModel::where($condition)->find();
        if( !$signDayDetail ){
            ajax_return('签到数据不存在');
        }
        //验证数据
        //验证实名认证
        if( !MemberService::judgeUserIsCanReceiveDiscount($uid) ){
            ajax_return('请先实名后再参与');
        }
        $result = $this->_validate($params, $uid, $signDetail, $signDayDetail);
        $lastDate = $result['date'];
        $date = $this->dateAddDay($lastDate);
        $amount = $result['amount'];
        //查看当天状态
        $_result = $this->getSignDayStatus($uid, $signDetail, $signDayDetail, $lastDate);
        if( $_result['status'] != 0 ){
            ajax_return('今天已签到，无需签到');
        }
        if( $date != date('Ymd') ){
            ajax_return('未到签到日期，请明天再来');
        }
        if( $isJudge ){
            return true;
        }
        //执行签到
        return $this->handleSign($uid, $signDetail, $signDayDetail, $amount, $date);
    }

    //获取更多奖励
    public function getMoreReward($uid){
        $lang = Lang::getLangSet();
        #1签到活动列表
        $condition = [];
        $condition[] = ['lang', 'in', [$lang,'']];
        $condition[] = ['status', '=', 1];
        $signList = SignModel::where($condition)->field('id,introduce')->order('sign_condition asc')->select();
        //判断是否满足条件
        foreach ($signList as &$signDetail){
            //获取签到活动对应天数
            $dayData =  $this->getDayBySign($uid, $signDetail);
            $signDetail->day_list = $dayData['day_list'];
        }
        return Respons::run(1, $signList, 'success');
    }

    //获取指定日期加
    public function dateAddDay($date, $day = 1){
        $date = date('Y-m-d', strtotime($date));#TODO 必须要转成Y-m-d格式，不然部分时间会有错误
        return Carbon::parse($date)->addDays($day)->format('Ymd');
    }

    //处理签到
    public function handleSign($uid, $signDetail, $signDayDetail, $amount, $date, $isSign = true){
        Db::startTrans();
        try {
            //生成记录
            $data = [
                'sign_id' => $signDetail->id,
                'uid' => $uid,
                'day' => $signDayDetail->day,
                'amount' => $amount,
                'reward_amount' => 0,
                'reward_integral' => 0,
                'savings' => $signDayDetail->savings,
                'status' => 0,
                'sign_status' => $isSign ? 1 : 0,
                'date' => $date,
                'create_time' => time(),
                'update_time' => time(),
            ];
            $recordId = (new SignRecordModel())->insertGetId($data);
            //发放奖励
            $result = $this->sendReward($uid, $signDetail, $signDayDetail, $amount, $recordId, $isSign);
            //修改记录的金额，积分，状态
            SignRecordModel::update([
                'reward_amount' => $result['amount'],
                'reward_integral' => $result['integral'],
                'status' => $result['status'],
                'is_receive_reward' => in_array($result['status'], [1, 2]) ? 1 : 0,
            ], ['id' => $recordId]);
            Db::commit();
            //清除用户所有缓存
            $this->clearCache($uid, $signDetail, $signDayDetail, $date);
            return Respons::run(1, [], $isSign ? '签到成功' : '补签成功');
        }catch (\Exception $e){
            Db::rollback();
            ajax_return('网络异常');
        }
    }

    //发放奖励
    private function sendReward($uid, $signDetail, $signDayDetail, $handleAmount, $recordId, $isSign = true){
        $lang = Lang::getLangSet();
        $currency = Util::i18n_currency_no_amount();
        $msgContent = "";
        if( $isSign ){
            //签到
            $title = lang('签到成功');
        }else{
            //补签
            $title = lang('补签成功');
        }
        $amount = 0;
        $integral = 0;
        if( $signDetail->sign_day == $signDayDetail->day ){
            //最后一天
            $status = 2;
            //最后一天查询之前所有未领取的奖励
            $condition = $where = [];
            $condition[] = $where[] = ['status', '=', 0];
            $condition[] = $where[] = ['day', '<', $signDayDetail->day];
            $condition[] = $where[] = ['uid', '=', $uid];
            $condition[] = $where[] = ['is_receive_reward', '=', 0];
            $signRecordList = SignRecordModel::where($condition)->select();
            foreach ($signRecordList as $v){
                //奖励金额和积分
                $result = $this->countAmount($v, $handleAmount);
                $amount += $result['amount'];
                $integral += $result['integral'];
            }
            //加上最后一天的
            $result = $this->countAmount($signDayDetail, $handleAmount);
            $amount += $result['amount'];
            $integral += $result['integral'];
            $title .= '. '.lang('领取所有未领取奖励');
            //最后一天将所有状态更新为过期状态
            SignRecordModel::update([
                'is_receive_reward' => 1,
            ], $where);
        }else{
            //奖励金额和积分
            $result = $this->countAmount($signDayDetail, $handleAmount);
            $amount = $result['amount'];
            $integral = $result['integral'];
            if($signDayDetail->reward_mode == SignModel::REWARD_MODE_PER_DAY){
                //每日领取
                $status = 1;
            }else{
                //累计领取
                $status = 0;
                $title .= '. '.lang('奖励累计中，最后一天发放');
            }
        }
        if( $status != 0 ){
            if( $amount > 0 ){
                //余额奖励
                UserModel::cz_balance($uid, $amount, "+", true, "签到奖励", 14, $signDayDetail->savings, 1, $recordId, true, 101);
                $msgContent = "{:title}. 领取所有未领取奖励. 奖励金额{:price} {:currency}";
                $msgData = ['price'=>$amount,'currency'=>$currency, 'title' => $title];
                UserMsgModel::add_msg($uid, $title, $msgContent, 21, $recordId, $msgData, $lang);
            }
            if( $integral > 0 ){
                //积分奖励
                IntegralMxModel::cz_mx($uid,1,$integral,'签到奖励');
                $msgContent = '{:title}. 签到成功. 领取所有未领取奖励. 奖励积分{:integral}';
                $msgData = ['integral' => $integral, 'title' => $title];
                UserMsgModel::add_msg($uid, $title, $msgContent, 21, $recordId, $msgData, $lang);
            }
        }
        return ['status' => $status, 'integral' => $integral, 'amount' => $amount];
    }

    //验证
    public function _validate($params, $uid, $signDetail, $signDayDetail){
        //验证数据
        try {
            validate(SignValidate::class)->scene('sign')->check($params);
        }catch (\Exception $e){
            ajax_return($e->getError());
        }
        // 判断是否禁止参与活动
        if (MemberService::tagBan($uid, 'is_activity')) {
            ajax_return("禁止参与活动，请联系客服", 0);
        }
        //判断是否达到任务条件
        if( !$this->judgeActivityIsFinish($uid, $signDetail)['is_finish'] ){
            ajax_return('签到条件不满足，无法签到');
        }
        //标签验证
        if (isset($signDetail->tag_id) && $signDetail->tag_id > 0) {
            $user_tags = MemberService::getUserTagId($uid);
            if (!in_array($signDetail->tag_id, $user_tags)) {
                ajax_return("用户标签条件不符合");
            }
        }
        //限制代理活动
        if( isset($signDetail->agent_id) && $signDetail->agent_id > 0){
            $tree = UserModel::where([["tree",'like','%/'.$signDetail->agent_id.'/%']])->column("id");
            if(!in_array($uid,$tree)){
                ajax_return("未达到活动要求,无法参与", 0);
            }
        }
        //获取用户数据
        $userDetail = UserModel::uinfo($uid);
        if(!$userDetail)ajax_return('用户异常，请重新登录');
        //判断VIP等级
        if( $signDetail->level_id != 0 && $userDetail->level_id < $signDetail->level_id ){
            ajax_return('领取会员等级不满足');
        }

        //双钱包判断当前用户是否可以重复领取优惠
        if( !WalletService::judgeUserIsRePeatReceive(0, $userDetail) ){
            ajax_return('你的账户还存在彩金，请使用完后再来领取');
        }
        //获取参与次数
        $finishNum = $this->getFinishCount($uid, $signDetail->id);
        if( $signDetail->join_count != 0 && $finishNum >= $signDetail->join_count && $params['day'] == 1 ){
            ajax_return('参与次数已达上限');
        }
        //判断用户充值是否达标
        if( $signDetail->min_recharge > 0 ){
            $condition = [];
            $condition[] = ['status', 'in', '3,4'];
            $condition[] = ['add_time', 'between', [strtotime('today'), time()]];
            $condition[] = ['uid', '=', $uid];
            $userOfflineAmount = UserOfflineModel::where($condition)->sum('arrival_price');
            if( $userOfflineAmount < $signDetail->min_recharge ){
                ajax_return('今日充值未达标');
            }
        }
        //判断用户下注是否达标
        if( $signDetail->min_bet > 0 ){
            //获取用户下注量
            $betAmount = MemberService::getUserFinishWater($uid, [['su_dt', '=', date('Ymd')]]);
            if( $betAmount < $signDetail->min_bet ){
                ajax_return('今日下注量未达标');
            }
        }
        if( $params['day'] != 1 ){
            //获取前一天的签到日期
            $condition = [];
            $condition[] = ['day', '=', $params['day'] - 1];
            $condition[] = ['sign_id', '=', $signDetail->id];
            $condition[] = ['uid', '=', $uid];
            $lastSignDayDetail = SignRecordModel::where($condition)->order('create_time desc')->find();
            if( !$lastSignDayDetail ){
                ajax_return('前一天未签到，请先进行补签');
            }
            $amount = $lastSignDayDetail->amount;
            $date = $lastSignDayDetail->date;
        }else{
            $date = date('Ymd', strtotime('yesterday'));
            //判断是否完成
            $result = $this->judgeActivityIsFinish($uid, $signDetail);
            $amount = $signDetail->amount = $result['amount'];
        }
        return ['date' => $date, 'amount' => $amount];
    }

    //获取签到对应天数
    public function getDayBySign($uid, $signDetail){
        $apiRedis=new ApiRedis();
        $key = 'get_day_by_sign_view_'.$uid.'_'.$signDetail->id;
        if( $apiRedis->has($key) ){
//            return json_decode($apiRedis->get($key), true);
        }
        $condition = [];
        $condition[] = ['sign_id', '=', $signDetail->id];
        $signDayList = SignDayModel::where($condition)->hidden(['id', 'sign_id', 'create_time', 'update_time'])->order('day asc')->select();
        $startDate = 0;
        $signArr = [];
        $date = date('Ymd');
        $totalAmount = 0;#总奖励金额
        $totalIntegral = 0;#总奖励积分
        $currentDay = 1;#当前天数
        $signDay = 0;#签到天数
        foreach ($signDayList as $k => $signDayDetail){
            //获取签到状态
            if( $signDayDetail->day == 1 || $startDate <= $date ){
                $result = $this->getSignDayStatus($uid, $signDetail, $signDayDetail, $startDate);
                $signDayList[$k]['status'] = $result['status'];#赋值状态
                if( in_array($result['status'], [1, 2]) ){
                    $signDay++;#签到天数
                }else {
                    $signDay = 0;
                }
                $startDate = $result['start_date'];#后一天开始筛选日期
                $signArr[$startDate] = $result['status'];#保存签到日期对应状态，用于后面判断
                if( $startDate == $date ){
                    $currentDay = $signDayDetail->day;
                }
            }else{
                $signDayList[$k]['status'] = 0;
            }
            //计算金额和积分
            $totalAmount += $signDayList[$k]['amount'] = $this->countAmount($signDayDetail, $signDetail->amount)['amount'];
            $totalIntegral += $signDayList[$k]['integral'] = $this->countAmount($signDayDetail, $signDetail->amount)['integral'];
        }
        #判断签到是否能够补签补回来
        $needAddSignNum = 0;#需要补签次数
//        $startWeek = Carbon::now()->startOfWeek()->format('Ymd');
        //开始天调整为改签到从后往前的第一天开始的日期
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['sign_id', '=', $signDetail->id];
        $startWeek = date('Ymd', SignRecordModel::where($condition)->order('create_time desc')->value('create_time'));
        $startWeekTime = Carbon::now()->startOfWeek()->timestamp;
        $endWeekTime = Carbon::now()->endOfWeek()->timestamp;
        $weekAddSignNum = $this->getWeekAddSignCount($signDetail, $uid, $startWeekTime, $endWeekTime);
        foreach ($signArr as $k => $v){
            if( $v == 0  && $k < $date && $k >= $startWeek ){
                $needAddSignNum++;
            }
        }
        $isSign = 1;
        if($signArr){
            //不满足签到
            if( !isset($signArr[$date]) || $needAddSignNum > $weekAddSignNum ){
                //不可以补签，重置天数列表状态都为未签到
                foreach ($signDayList as $k => $signDayDetail){
                    $signDayList[$k]['status'] = 0;
                }
                $isSign = 0;
                $signDay = 0;
            }
        }
        //如果第一天都没有签到，重置$currentDay=1第一天
        if( $signDayList[0]['status'] == 0 ){
            $currentDay = 1;
        }
        $result = ['day_list' => $signDayList, 'is_sign' => $isSign, 'total_amount' => $totalAmount, 'total_integral' => $totalIntegral, 'date' => $date, 'current_day' => $currentDay, 'sign_day' => $signDay];
        //设置缓存为12点距离当前时间的秒数
        $apiRedis->set($key, json_encode($result), 60 * 5);
        return $result;
    }

    //获取本周补卡签到次数
    public function getWeekAddSignCount($signDetail, $uid, $startWeek = 0, $endWeek = 0){
        $apiRedis=new ApiRedis();
        $startWeek = $startWeek ?: Carbon::now()->startOfWeek()->timestamp;
        $endWeek = $endWeek ?: Carbon::now()->endOfWeek()->timestamp;
        $key = 'get_week_surplus_add_sign_count_v2_'.$uid.'_'.$signDetail->id.'_'.$startWeek.'_'.$endWeek;
        //从缓存获取
        if( $apiRedis->has($key) ){
//            return intval($apiRedis->get($key));
        }
        //缓存没有从数据库中获取
        $condition = [];
        $condition[] = ['sign_status', '=', 0];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['sign_id', '=', $signDetail->id];
        $condition[] = ['create_time', 'between', [$startWeek, $endWeek]];
        $count = SignRecordModel::where($condition)->count();
        //剩余补签次数=可补签次数-已补签次数
        $surplusCount = $signDetail->week_sign_count - $count;
        $apiRedis->set($key, $surplusCount, 180);
        return intval($surplusCount);
    }

    //计算指定天数奖励金额
    public function countAmount($signDayDetail, $amount){
        if($signDayDetail->reward_type == SignModel::REWARD_TYPE_FIXATION){
            //固定奖励
            return ['amount' => round($signDayDetail->reward_amount, 2), 'integral' => round($signDayDetail->reward_integral, 2)];
        }else{
            //百分比
            return [
                'amount' => round(($signDayDetail->reward_amount / 100) * $amount, 2),
                'integral' => round(($signDayDetail->reward_integral / 100) * $amount),
            ];
        }
    }

    //获取签到状态（0未签到，1已补签，2已签到）
    public function getSignDayStatus($uid, $signDetail, $signDayDetail, $startDate = 0){
        $apiRedis=new ApiRedis();
        $key = 'get_sign_day_status_by_uid_v1_'.$uid.'_'.$signDetail->id.'_'.$signDayDetail->id.'_'.$startDate;
        if( $apiRedis->has($key) ){
//            return json_decode($apiRedis->get($key), true);
        }

        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['day', '=', $signDayDetail->day];
        $condition[] = ['sign_id', '=', $signDetail->id];
        if( $startDate ){
            $condition[] = ['date', '>', $startDate];
        }
        $signRecordDetail = SignRecordModel::where($condition)->order('create_time desc')->find();
        $status = 0;
        if( $signRecordDetail ){
            $status = 2;
            if( $signRecordDetail->sign_status == 0 ){
                $status = 1;
            }
            $startDate = $signRecordDetail->date;
        }else{
            //判断是否是日期格式
            if( $startDate > 20231001 ){
                $startDate = $this->dateAddDay($startDate);
            }else{
                $startDate++;#1
            }
        }
        $result = ['status' => $status, 'start_date' => $startDate];
        $apiRedis->set($key, json_encode($result), 60 * 5);
        return $result;
    }

    //获取满足条件的签到活动
    public function getNowSignActivity($uid, $isNeedLang = true){
        $lang = Lang::getLangSet();
        #1、获取满足完成签到活动列表
        $condition = [];
        $condition[] = ['status', '=', 1];
        $condition[] = ['sign_condition', '=', SignModel::CONDITION_APPOINT];
        if( $isNeedLang ){
            $condition[] = ['lang', 'in', [$lang,'']];
        }
        //获取域名或用户对应的代理对应代理条件
        $condition1 = AgentService::getAgentWhere();
        $signList = SignModel::where($condition)->where($condition1)->select();
        //判断是否满足条件
        foreach ($signList as $signDetail){
            //判断是否完成
            $result = $this->judgeActivityIsFinish($uid, $signDetail);
            $signDetail->amount = $result['amount'];
            //获取签到活动对应天数
            $dayData =  $this->getDayBySign($uid, $signDetail);
//            if( $result['is_finish'] && $dayData['is_sign'] ){
//            if( $result['is_finish'] ){#注释，不需要达到任务条件都可以显示
                $signDetail->day_list = $dayData['day_list'];
                $signDetail->total_amount = $dayData['total_amount'];
                $signDetail->total_integral = $dayData['total_integral'];
                $signDetail->new_date = $dayData['date'];
                $signDetail->current_day = $dayData['current_day'];
                $signDetail->sign_day = $dayData['sign_day'];
                return $signDetail;
//            }
        }

        #2、获取不需要满足完成签到活动列表
        $condition = [];
        $condition[] = ['sign_condition', '<>', SignModel::CONDITION_APPOINT];
        $condition[] = ['status', '=', 1];
        if( $isNeedLang ){
            $condition[] = ['lang', 'in', [$lang,'']];
        }
        $signList = SignModel::where($condition)->where($condition1)->select();
        //判断是否满足条件
        foreach ($signList as $signDetail){
            //判断是否完成
            $result = $this->judgeActivityIsFinish($uid, $signDetail);
            $signDetail->amount = $result['amount'];
            //获取签到活动对应天数
            $dayData =  $this->getDayBySign($uid, $signDetail);
//            if( $result['is_finish'] && $dayData['is_sign'] ){
//            if( $result['is_finish'] ){#注释，不需要达到任务条件都可以显示
                $signDetail->day_list = $dayData['day_list'];
                $signDetail->total_amount = $dayData['total_amount'];
                $signDetail->total_integral = $dayData['total_integral'];
                $signDetail->new_date = $dayData['date'];
                $signDetail->current_day = $dayData['current_day'];
                $signDetail->sign_day = $dayData['sign_day'];
                $signDetail->introduce = $this->getIntroduce($signDetail,$lang);
                return $signDetail;
//            }
        }
        return [];
    }

    //判断活动是否满足
    public function judgeActivityIsFinish($uid, $signDetail){
        $apiRedis=new ApiRedis();
        $key = 'judge_is_finish_sign_'.$uid.'_'.$signDetail->id;
        if( $apiRedis->has($key) ){
//            return json_decode($apiRedis->get($key), true);
        }
        //获取起止时间
        $timeResult = $this->getTimeByType($signDetail->condition_date_type);
        $startTime = $timeResult['start_time'];
        $endTime = $timeResult['end_time'];
        $isFinish = 0;
        //根据条件判断是否满足参与条件
        switch ($signDetail->sign_condition){
            case SignModel::CONDITION_FIRST:
                //首充金额
                #指定时间内的首充不需要指定first=1,第一笔交易即为首充
                $condition = [];
                $condition[] = ['update_time', 'between', [$startTime, $endTime]];
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['status', 'in', '3,4'];
                $arrivalPrice = UserOfflineModel::where($condition)->order('add_time asc')->value('arrival_price')?:0;
                if( $arrivalPrice >= $signDetail->recharge_amount ){
                    $isFinish = 1;
                }
                break;
            case SignModel::CONDITION_ONE:
                //单笔金额
                $condition = [];
                $condition[] = ['update_time', 'between', [$startTime, $endTime]];
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['arrival_price', '>=', $signDetail->recharge_amount];
                $condition[] = ['status', 'in', '3,4'];
                $arrivalPrice = UserOfflineModel::where($condition)->order('add_time desc')->value('arrival_price')?:0;
                if( $arrivalPrice ){
                    $isFinish = 1;
                }
                break;
            case SignModel::CONDITION_TOTAL:
                //累计金额
                $condition = [];
                $condition[] = ['update_time', 'between', [$startTime, $endTime]];
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['status', 'in', '3,4'];
                $arrivalPrice = UserOfflineModel::where($condition)->sum('arrival_price')?:0;
                if( $arrivalPrice >= $signDetail->recharge_amount ){
                    $isFinish = 1;
                }
                break;
            case SignModel::CONDITION_APPOINT:
                //指定任务
                $signNum = $this->getFinishCount($uid, $signDetail->sign_id, $startTime, $endTime);
                if( $signNum >= $signDetail->sign_day ){
                    $isFinish = 1;
                }
                break;
        }
        $result = ['is_finish' => $isFinish, 'amount' => $arrivalPrice ?? 0];
        $apiRedis->set($key, json_encode($result), 60 * 5);
        return $result;
    }

    //根据时间类型获取时间
    public function getTimeByType($type){
        $time = time();
        //根据条件类型获取时间
        if( $type == SignModel::CONDITION_DATE_LAST_MONTH ){
            //上月
            $startTime = Carbon::now()->subMonth()->startOfMonth()->timestamp;
            $endTime = Carbon::now()->subMonth()->endOfMonth()->timestamp;
        }elseif ( $type == SignModel::CONDITION_DATE_MONTH ){
            //本月
            $startTime = Carbon::now()->startOfMonth()->timestamp;
            $endTime = $time;
        }elseif ( $type == SignModel::CONDITION_DATE_YESTERDAY ){
            //昨天
            $startTime = Carbon::yesterday()->startOfDay()->timestamp;
            $endTime = Carbon::yesterday()->endOfDay()->timestamp;
        }elseif ( $type == SignModel::CONDITION_DATE_TODAY ){
            //今天
            $startTime = Carbon::now()->startOfDay()->timestamp;
            $endTime = $time;
        }elseif ( $type == SignModel::CONDITION_DATE_WEEK ){
            //本周
            $startTime = Carbon::now()->startOfWeek()->timestamp;
            $endTime = $time;
        }elseif ( $type == SignModel::CONDITION_DATE_SEVEN ){
            //七天内
            $startTime = Carbon::now()->addDay(-7)->timestamp;
            $endTime = $time;
        }else{
            //所有
            $startTime = 0;
            $endTime = $time;
        }
        return ['start_time' => $startTime, 'end_time' => $endTime];
    }

    //获取已完成活动次数
    public function getFinishCount($uid, $signId, $startTime = 0, $endTime = 0){
        $apiRedis=new ApiRedis();
        $key = 'get_finish_count_'.$uid.'_'.$signId;
        if( $apiRedis->has($key) && $startTime == 0 ){
//            return $apiRedis->get($key);
        }
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['status', '=', 2];
        $condition[] = ['sign_id', '=', $signId];
        if( $startTime && $endTime ){
            $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        }elseif ($startTime){
            $condition[] = ['create_time', '>=', $startTime];
        }elseif ($endTime){
            $condition[] = ['create_time', '<=', $endTime];
        }
        $signNum = SignRecordModel::where($condition)->count();
        $apiRedis->set($key, $signNum, 2 * 60 * 60);
        return $signNum;
    }

    //清除缓存
    public function clearCache($uid, $signDetail, $signDayDetail, $date){
        $apiRedis=new ApiRedis();
        $key = 'get_day_by_sign_view_'.$uid.'_'.$signDetail->id;
        $apiRedis->delete($key);
        $startWeek = Carbon::now()->startOfWeek()->timestamp;
        $endWeek = Carbon::now()->endOfWeek()->timestamp;
        $key = 'get_week_surplus_add_sign_count_v2_'.$uid.'_'.$signDetail->id.'_'.$startWeek.'_'.$endWeek;
        $apiRedis->delete($key);
        $key = 'get_sign_day_status_by_uid_v1_'.$uid.'_'.$signDetail->id.'_'.$signDayDetail->id.'_'.$date;
        $apiRedis->delete($key);
        $key = 'get_sign_day_status_by_uid_v1_'.$uid.'_'.$signDetail->id.'_'.$signDayDetail->id.'_'.$this->dateAddDay($date, -1);
        $apiRedis->delete($key);
        $key = 'get_sign_day_status_by_uid_v1_'.$uid.'_'.$signDetail->id.'_'.$signDayDetail->id.'_0';
        $apiRedis->delete($key);
        $key = 'judge_is_finish_sign_'.$uid.'_'.$signDetail->id;
        $apiRedis->delete($key);
        $key = 'get_finish_count_'.$uid.'_'.$signDetail->id;
        $apiRedis->delete($key);
        return true;
    }

    /**
     * 获取规则
     * @param $turntableDetail
     * @param $lang
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getIntroduce($turntableDetail, $lang)
    {
        $map = [
            'pid' => $turntableDetail->id,
            'lang' => $lang
        ];
        $temp = SignNewsModel::where($map)->find();
        if (empty($temp)) {
            return $turntableDetail->introduce;
        }
        return $temp['news_content'];
    }

    public function pushMessageToFinishTaskUser($uid = 0){
        $apiRedis=new ApiRedis();
        //获取用户数据
        $userDetail = UserModel::where("id", $uid)->field('tree,level_id')->find();
        if( !$userDetail ){
            return false;
        }
        //获取签到列表
        $signList = $this->getNowSignActivity($uid, false);
        foreach ($signList as $v){
            //两小时内推送过则不再推送
            $pushUserKey = 'push_user_finish_sign_key_'.$uid.'_'.$v['id'];
            if( $apiRedis->has($pushUserKey) ){
                continue;
            }
            //判断代理线
            $userAgentIds = array_filter(explode('/', $userDetail->tree));
            $judgeAgentIdResult = false;
            foreach (explode(',', $v['agent_id']) as $_v){
                if( in_array($_v, $userAgentIds) ){
                    $judgeAgentIdResult = true;
                }
            }
            if( $v['agent_id'] && !$judgeAgentIdResult ){
                continue;
            }
            //判断标签
            $userLevelId = $userDetail->level_id;
            if( $v['vip_id'] != 0 && $v['vip_id'] != $userLevelId){
                continue;
            }
            //判断标签
            $user_tag_id = MemberService::getUserTagId($uid);
            $user_tag_id[] = 0;
            if( !in_array($v['tag_id'], $user_tag_id) ){
                continue;
            }
            //判断用户是否能签到
            $params = [
                'day' => $v['current_day'],
                'sign_id' => $v['id']
            ];
            try {
                if( $this->userSign($uid, $params, true) ){
                    //完成任务且没有领取给用户发送没有领取通知
                    MessageService::sendTaskFinishMessageNotify( $uid, 'sign');
                    //设置缓存两小时内不再推送
                    $apiRedis->set($pushUserKey, 1, 3600 * 2);
                }
            }catch (\Exception $e){
                continue;
            }
        }
        return true;
    }

}