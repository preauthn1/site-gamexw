<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\TokenCheck;
use app\common\lib\Util;
use app\common\model\IntegralMxModel;
use app\common\model\LotteryConfigModel;
use app\common\model\LotteryOnlineTaskModel;
use app\common\model\LotteryOnlineTaskRecordModel;
use app\common\model\SignModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\service\MemberService;
use Carbon\Carbon;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Request;

class LotteryOnlineFactory{

    private $param;//请求参数
    private $uid;//用户id

    private $cacheTime = 86400;#每条在线任务缓存时间

    private $requestSecond = 3;#每次最多增加秒数
    //验证
    public function __construct($uid = 0){
        $this->param=Request::param();
        $this->uid = $uid;
    }

    //获取转盘在线任务数据
    public function getLotteryOnlineTaskData(){
        //如果登录就获取用户uid
        if( !$this->uid ){
            $header = request()->header();
            try {
                $token = TokenCheck::run($header);
                if( $token ){
                    $this->uid = $token->uid;
                }else{
                    return [];
                }
            }catch (\Exception $e){}
        }
        $apiRedis=new ApiRedis();
        $key = $this->getRedisKey($this->uid);
        if( $apiRedis->has($key) ){
            $value = $apiRedis->get($key);
            //重新设置缓存值
            $apiRedis->set($key, $value, $this->cacheTime);
            $redisValue = json_decode($value, true);
            return [
                'time' => $redisValue['time'],
            ];
        }

        $userDetail = UserModel::where('id', '=', $this->uid)->find();
        if(!$userDetail){
            return [];
        }
        $lotteryDetail = $this->getLottery();
        if( !$lotteryDetail ){
            return [];
        }
        //标签限制
        $user_tag_id = MemberService::getUserTagId($this->uid);
        $user_tag_id[] = 0;
        $user_tag_id_str = implode(',', $user_tag_id);
        $condition1 = 'tag_id in ('.$user_tag_id_str.')';
        //等级限制
        $condition1 .= ' and (level_id <= '.$userDetail->level_id.' or level_id = 0)';
        //代理线
        $tree1 = explode("/", $userDetail['tree']);
        $agentIdArr[] = 0;
        $agentIdArr[] = $tree1[1];
        $condition1 .= ' and agent_id in ('.implode(',', $agentIdArr).')';

        //获取任务数据
        $condition = [];
        $lang = Lang::getLangSet();
        $condition[] = ['lang', '=', $lang];
        $list = LotteryOnlineTaskModel::where($condition1)->where($condition)->order('time asc')->select();
        $taskDetail = [];
        foreach ($list as $v){
            //获取参与次数判断
            if( $this->getRecordNum($this->uid, $v->id, $v->type) < $v->join_num ){
                $taskDetail = $v;
                break;
            }
        }
        if( !$taskDetail ){
            return [];
        }
        $value = json_encode([
            'current_time' => time(),
            'time' => $taskDetail['time'] * 60,
            'task_detail' => $taskDetail,
            'lottery' => $lotteryDetail
        ]);
        $apiRedis->set($key, $value, $this->cacheTime);
        return [
            'time' => $taskDetail['time'] * 60,
        ];
    }

    //定时执行时长
    public function online(){
        $apiRedis=new ApiRedis();
        $key = $this->getRedisKey($this->uid);
        //获取缓存数据
        $value = $apiRedis->get($key);
        $newTime = 0;
        if( !$value ){
            $status = -1;
        }else{
            $redisValue = json_decode($value, true);
            $time = time();
            //减少的秒数
            $decTime = min($time - $redisValue['current_time'], $this->requestSecond);
            //最新秒数
            $newTime = $redisValue['time'] - $decTime;
            if( $newTime > 0 ){
                //继续累计时长
                $value = json_encode([
                    'current_time' => time(),
                    'time' => $newTime,
                    'task_detail' => $redisValue['task_detail'],
                    'lottery' => $redisValue['lottery'],
                ]);
                $apiRedis->set($key, $value, $this->cacheTime);
                $status = 0;
            }else{
                //已完成在线时长，发放奖励
                if( $this->sendReward($redisValue['task_detail'], $redisValue['lottery'], $this->uid) ){
                    $newTime = 0;
                    $status = 1;
                    //清除缓存
                    $this->clearCache($this->uid, $redisValue['task_detail']);
                }else{
                    $status = 0;
                }
            }
        }
        return ['code' => 1, 'data' => ['status' => $status, 'time' => $newTime]];
    }

    //发放奖励
    public function sendReward($taskDetail, $lotteryDetail, $uid){
        $lang = Lang::getLangSet();
        $currency = Util::i18n_currency_no_amount();
        $amount = $lotteryDetail['required_balance'];
        $integral = $lotteryDetail['required_integral'];
        $title = '在线时长奖励';
        Db::startTrans();
        try {
            //生成领取记录
            $recordId = (new LotteryOnlineTaskRecordModel)->insertGetId([
                'task_id' => $taskDetail['id'],
                'type' => $taskDetail['type'],
                'uid' => $uid,
                'receive_num' => $taskDetail['lottery_num'],
                'receive_amount' => $amount,
                'receive_integral' => $integral,
                'create_time' => time(),
                'update_time' => time(),
            ]);

            if( $lotteryDetail['required_balance'] ){
                //余额奖励
                UserModel::cz_balance($uid, $amount, "+", true, "在线时长奖励", 16, 0, 1, $recordId, true, 101);
                $msgContent = lang($title) .'，'.lang('在线奖励已到账,奖励').'{:price} {:currency}';
                $msgData = ['price'=>$amount,'currency'=>$currency];
                UserMsgModel::add_msg($uid, $title, $msgContent, 21, $recordId, $msgData, $lang);
            }
            if( $lotteryDetail['required_integral'] ){
                //积分奖励
                IntegralMxModel::cz_mx($uid,1,$integral,'在线时长奖励');
                $msgContent = lang($title).'，'.lang('在线奖励已到账,奖励积分').'{:integral}';
                $msgData = ['integral' => $integral];
                UserMsgModel::add_msg($uid, $title, $msgContent, 21, $recordId, $msgData, $lang);
            }

            Db::commit();
            return true;
        }catch (\Exception $e){
            Db::rollback();
            return false;
        }
    }

    //获取缓存key
    private function getRedisKey($uid){
        return 'lottery_online_'.$uid;
    }

    //获取参与次数
    public function getRecordNum($uid, $taskId, $type){
        $apiRedis=new ApiRedis();
        $key = 'get_lottery_online_task_'.$uid.'_'.$taskId.'_'.$type;
        if( $apiRedis->has($key) ){
            return $apiRedis->get($key);
        }
        $time = $this->getTimeByType($type);
        $startTime = $time['start_time'];
        $endTime = $time['end_time'];
        $condition = [];
        $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        $condition[] = ['task_id', '=', $taskId];
        $condition[] = ['type', '=', $type];
        $num = LotteryOnlineTaskRecordModel::where($condition)->count();
        $apiRedis->set($key, $num, 7200);
        return $num;
    }

    private function getLottery(){
        return LotteryConfigModel::where('id', '>', 0)->find();
    }

    //根据时间类型获取时间
    public function getTimeByType($type){
        $time = time();
        //根据条件类型获取时间
        if( $type == LotteryOnlineTaskModel::DAY ){
            //今天
            $startTime = Carbon::now()->startOfDay()->timestamp;
            $endTime = $time;
        }elseif ( $type == LotteryOnlineTaskModel::WEEK ){
            //本周
            $startTime = Carbon::now()->startOfWeek()->timestamp;
            $endTime = $time;
        }elseif ( $type == LotteryOnlineTaskModel::MONTH ){
            //本月
            $startTime = Carbon::now()->startOfMonth()->timestamp;
            $endTime = $time;
        }else{
            //所有
            $startTime = 0;
            $endTime = $time;
        }
        return ['start_time' => $startTime, 'end_time' => $endTime];
    }

    //清除缓存
    public function clearCache($uid, $taskDetail){
        $apiRedis=new ApiRedis();
        $key = 'get_lottery_online_task_'.$uid.'_'.$taskDetail['id'].'_'.$taskDetail['type'];
        $apiRedis->delete($key);
        $key = $this->getRedisKey($uid);
        $apiRedis->delete($key);
    }



}