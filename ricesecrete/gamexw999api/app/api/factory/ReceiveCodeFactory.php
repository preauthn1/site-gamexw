<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\ReceiveCodeModel;
use app\common\model\ReceiveCodeRecordModel;
use app\common\model\UserAdminLogModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserTagModel;
use app\common\redis\CommomRedis;
use app\common\service\MemberService;
use app\common\service\WalletService;
use Carbon\Carbon;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Request;

class ReceiveCodeFactory
{

    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0, $param = [])
    {
        $this->param = $param ?: Request::param();
        $this->uid = $uid;
    }

    public function receive($isLang = true, $isLock = true)
    {
        $code = $this->param['code'] ?? '';
        //获取领取码对应数据
        $condition = [];
        $condition[] = ['code', '=', $code];
        if ($isLang) {
            $lang = Lang::getLangSet();
            $condition[] = ['lang', 'in', [$lang, '']];
        }
        $receiveCodeDetail = ReceiveCodeModel::where($condition)->find();
        //锁
        $ApiRedis = new ApiRedis();
        if (!$ApiRedis->lock($this->uid, 3) && $isLock) {
            ajax_return("请勿频繁访问", 0);
        }
        if (!$receiveCodeDetail) {
            ajax_return('领取码错误，请核对后重新输入');
        }
        //验证实名认证
        if (!MemberService::judgeUserIsCanReceiveDiscount($this->uid)) {
            ajax_return('请先实名后再参与');
        }
        //判断领取码领取时间
        $time = time();
        if ($time < strtotime($receiveCodeDetail->start_time)) {
            ajax_return('未到领取时间，请确认好时间后领取');
        }
        if ($receiveCodeDetail->end_time && $time > strtotime($receiveCodeDetail->end_time)) {
            ajax_return('该领取码已过期，请更换领取码后重试');
        }
        //获取用户数据
        $userDetail = UserModel::uinfo($this->uid);

        //双钱包判断当前用户是否可以重复领取优惠
        if (!WalletService::judgeUserIsRePeatReceive(0, $userDetail)) {
            ajax_return('你的账户还存在彩金，请使用完后再来领取');
        }
        //判断用户等级
        if ($receiveCodeDetail->level_id > 0 && $receiveCodeDetail->level_id != $userDetail->level_id) {
            ajax_return('您当前的VIP等级不足，无法领取该奖励');
        }
        //判断流水倍数
        if ($receiveCodeDetail->receive_savings > 0 && $receiveCodeDetail->receive_savings > MemberService::getUserFinishWater($this->uid)) {
            ajax_return('您当前的流水不足，无法领取该奖励');
        }
        //判断标签
        if ($receiveCodeDetail->tag_id > 0) {
            $userTags = MemberService::getUserTagId($this->uid);
            if (!in_array($receiveCodeDetail->tag_id, $userTags)) {
                ajax_return('当前账号标签不满足领取条件');
            }
        }
        //判断代理线
        if ($receiveCodeDetail->agent_id > 0) {
            $tree = explode("/", $userDetail->tree);
            if (!isset($tree[1]) || $tree[1] != $receiveCodeDetail->agent_id) {
                ajax_return('当前账号代理线条件不满足领取条件');
            }
        }
        //判断是否完成
        if (!$this->judgeReceiveCodeIsFinish($this->uid, $receiveCodeDetail)) {
            ajax_return('未满足领取码充值条件');
        }
        //判断当前ip是否能领取
        $ip = Util::get_ip();
        if ($receiveCodeDetail->receive_ip_num > 0 && $this->getIpReceiveNum($receiveCodeDetail, $ip) >= $receiveCodeDetail->receive_ip_num) {
            ajax_return('当前ip领取数量已达限制');
        }
        //判断当前用户领取次数
        $num = $this->getCodeReceiveNum($receiveCodeDetail->id, $this->uid);
        if ($num > $receiveCodeDetail->everyone_receive_num) {
            ajax_return('您已领取完该奖励，请勿重复领取');
        }
        //判断用户每天领取次数
        if ($receiveCodeDetail->per_person_per_day_receive_num > 0) {
            if ($this->getCodeReceiveNum($receiveCodeDetail->id, $this->uid, false, true, true) > $receiveCodeDetail->per_person_per_day_receive_num) {
                ajax_return('您今天已领取完该奖励，请勿重复领取');
            }
        }
        //判断领取码总次数
        if ($this->getCodeReceiveNum($receiveCodeDetail->id) > $receiveCodeDetail->receive_num) {
            ajax_return('该领取码已被使用，请更换领取码重试');
        }
        //执行领取
        $result = $this->receiveCodeReward($receiveCodeDetail, $this->uid);
        $ApiRedis->unlock($this->uid);
        return $result;
    }

    //领取领取码对应奖励
    public function receiveCodeReward($receiveCodeDetail, $uid)
    {
        $lang = Lang::getLangSet();
        //获取默认币种
        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();
        $ip = Util::get_ip();
        $currency = $currency['defa_data']['currency_code'];
        $returnData = [
            'code' => 0,
            'data' => [],
            'msg' => '系统错误'
        ];
        if ($receiveCodeDetail->amount_type == ReceiveCodeModel::AMOUNT_TYPE_NORMAL) {
            //普通的为一样的金额
            $amount = $this->handleAmount($receiveCodeDetail->reward_amount);
        } elseif ($receiveCodeDetail->amount_type == ReceiveCodeModel::AMOUNT_TYPE_TOTAL) {
            //累计为所有人领取的加起来等于总金额
            $amount = $this->getTotalTypeAmount($receiveCodeDetail);
        } else {
            $this->clear_cache($receiveCodeDetail, $uid, $ip);
            ajax_return('金额类型错误');
        }
        Db::startTrans();
        try {
            //生成领取记录
            $result = ReceiveCodeRecordModel::create([
                'uid' => $uid,
                'code' => $receiveCodeDetail->code,
                'receive_code_id' => $receiveCodeDetail->id,
                'receive_code_data' => json_encode($receiveCodeDetail),
                'amount_type' => $receiveCodeDetail->amount_type,
                'reward_amount' => $amount,
                'ip' => $ip,
                'savings' => $receiveCodeDetail->savings,
                'tag_list' => $receiveCodeDetail->tag_list,
            ]);
            if (!$result) {
                Db::rollback();
                $this->clear_cache($receiveCodeDetail, $uid, $ip);
                return $returnData;
            }
            if (isset($receiveCodeDetail['tag_list'])) {
                $tag_list = explode(',', $receiveCodeDetail['tag_list']);
                if (count($tag_list) > 0) {
                    $user_tag = [];
                    foreach ($tag_list as $kk => $vv) {
                        $user_tag[$kk]['uid'] = $uid;
                        $user_tag[$kk]['tag_id'] = $vv;
                        $user_tag[$kk]['create_time'] = time();
                        $user_tag[$kk]['update_time'] = time();
                    }
                    (new UserTagModel())->saveAll($user_tag);
                    MemberService::clearCacheTagByUid($uid);
                }
            }
//            halt($user_tag);
            $msgTitle = '领取码奖励';
            //余额奖励
            UserModel::cz_balance($uid, $amount, "+", true, "领取码奖励", 13, $receiveCodeDetail->savings, 1, $result->id, true, 101);
            $msgContent = lang('领取码奖励已到账，奖励金额') . '{:price} {:currency}';
            $msgData = ['title' => $receiveCodeDetail->title, 'price' => $amount, 'currency' => $currency];
            UserMsgModel::add_msg($uid, $msgTitle, $msgContent, 19, $result->id, $msgData, $lang);
            Db::commit();
            $returnData['data'] = ['amount' => $amount, 'savings' => $receiveCodeDetail->savings * $amount];
            $returnData['msg'] = '领取成功';
            $returnData['code'] = 1;
        } catch (\Exception $e) {
            Db::rollback();
        }
        //生成当前用户领取数量缓存
        $this->clear_cache($receiveCodeDetail, $uid, $ip);
        return Respons::run($returnData);
    }

    //获取总计模式随机的金额
    public function getTotalTypeAmount($receiveCodeDetail)
    {
        #1、获取已领取金额和人数
        $result = $this->getReceiveNumAndPeople($receiveCodeDetail);
        $num = $result['num'];
        $_amount = $result['amount'];
        $remainNum = $receiveCodeDetail->receive_num - $num;#剩余次数
        #2、剩余金额
        $remainAmount = round($receiveCodeDetail->reward_amount - $_amount, 2);
        //如果只剩最后一个人，则领取剩余金额的全部金额
        if ($remainNum <= 1) {
            return $remainAmount;
        }
        #3、计算最大金额和最小金额
        $min = 0.01;
        $max = round($remainAmount - (0.01 * $remainNum), 2);//最大金额为剩余金额减去剩余的人数每次领取0.01
        $decimalPlaces = 2; // 小数点位数
        return mt_rand($min * pow(10, $decimalPlaces), $max * pow(10, $decimalPlaces)) / pow(10, $decimalPlaces);
    }

    //获取已领取的金额和人数
    public function getReceiveNumAndPeople($receiveCodeDetail)
    {
        $condition = [];
        $condition[] = ['receive_code_id', '=', $receiveCodeDetail->id];
        $detail = ReceiveCodeRecordModel::where($condition)->field('sum(reward_amount) as amount,count(id) as num')->find();
        $num = 0;
        $amount = 0;
        if ($detail) {
            $num = $detail->num;
            $amount = $detail->amount;
        }
        return ['num' => $num, 'amount' => $amount];
    }

    //处理金额
    public function handleAmount($amount)
    {
        if (strpos($amount, '-') !== false) {
            //随机金额
            list($minAmount, $maxAmount) = explode('-', $amount);
            $decimalPlaces = 2; // 小数点位数
            $amount = mt_rand($minAmount * pow(10, $decimalPlaces), $maxAmount * pow(10, $decimalPlaces)) / pow(10, $decimalPlaces);
        } else {
            $amount = (float)$amount;
        }
        return $amount;
    }

    //获取IP领取次数
    public function getIpReceiveNum($receiveCodeDetail, $ip, $isDatabase = false)
    {
        $key = 'get_receive_code_ip_num_' . $receiveCodeDetail->id . '_' . $ip;
        $apiRedis = new ApiRedis();
        //先从缓存判断是否存在
        if (!$isDatabase && $apiRedis->has($key)) {
            return $apiRedis->get($key);
        }
        $condition = [];
        $condition[] = ['ip', '=', $ip];
        $condition[] = ['receive_code_id', '=', $receiveCodeDetail->id];
        $num = ReceiveCodeRecordModel::where($condition)->count();
        $apiRedis->set($key, $num, 3600);
        return $num;
    }

    //获取领取码领取次数 TODO 开启缓存后该方法一定要放到验证的最后面验证，否则其他验证不通过后会有问题
    public function getCodeReceiveNum($receiveCodeId, $uid = 0, $isDatabase = false, $isInc = true, $isToday = false)
    {
        $key = 'get_receive_code_receive_num_' . $receiveCodeId . '_' . $uid;
        $apiRedis = new ApiRedis();
        //先从缓存判断是否存在
//        if( !$isDatabase && $apiRedis->has($key) ){
//            $num = $apiRedis->get($key);
//        }else{
        //没有从数据库取出判断
        $condition = [];
        $condition[] = ['receive_code_id', '=', $receiveCodeId];
        if ($uid) {
            $condition[] = ['uid', '=', $uid];
        }
        if ($isToday) {
            $startTime = Carbon::now()->startOfDay()->timestamp;
            $endTime = Carbon::now()->endOfDay()->timestamp;
            $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        }
        $num = ReceiveCodeRecordModel::where($condition)->count();
//        }
        #取完将缓存加1，后面执行成功就不需要再从数据库生成缓存
        if ($isInc) {
            $num++;
        }
        $apiRedis->set($key, $num, 600);
        return $num;
    }


    //判断领取码是否满足
    public function judgeReceiveCodeIsFinish($uid, $receiveCodeDetail)
    {
        //获取起止时间
        $timeResult = $this->getTimeByType($receiveCodeDetail->condition_date_type);
        $startTime = $timeResult['start_time'];
        $endTime = $timeResult['end_time'];
        $isFinish = 0;
        //根据条件判断是否满足参与条件
        switch ($receiveCodeDetail->receive_condition) {
            case ReceiveCodeModel::CONDITION_FIRST:
                //首充金额
                #指定时间内的首充不需要指定first=1,第一笔交易即为首充
                $condition = [];
                $condition[] = ['add_time', 'between', [$startTime, $endTime]];
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['status', 'in', '3,4'];
                $arrivalPrice = UserOfflineModel::where($condition)->order('add_time asc')->value('arrival_price');
                if ($arrivalPrice >= $receiveCodeDetail->recharge_amount) {
                    $isFinish = 1;
                }
                break;
            case ReceiveCodeModel::CONDITION_ONE:
                //单笔金额
                $condition = [];
                $condition[] = ['add_time', 'between', [$startTime, $endTime]];
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['arrival_price', '>=', $receiveCodeDetail->recharge_amount];
                $condition[] = ['status', 'in', '3,4'];
                $arrivalPrice = UserOfflineModel::where($condition)->order('add_time desc')->value('arrival_price');
                if ($arrivalPrice) {
                    $isFinish = 1;
                }
                break;
            case ReceiveCodeModel::CONDITION_TOTAL:
                //累计金额
                $condition = [];
                $condition[] = ['add_time', 'between', [$startTime, $endTime]];
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['status', 'in', '3,4'];
                $arrivalPrice = UserOfflineModel::where($condition)->sum('arrival_price');
                if ($arrivalPrice >= $receiveCodeDetail->recharge_amount) {
                    $isFinish = 1;
                }
                break;
        }
        return $isFinish;
    }

    //根据时间类型获取时间
    public function getTimeByType($type)
    {
        $time = time();
        //根据条件类型获取时间
        if ($type == ReceiveCodeModel::CONDITION_DATE_LAST_MONTH) {
            //上月
            $startTime = Carbon::now()->subMonth()->startOfMonth()->timestamp;
            $endTime = Carbon::now()->subMonth()->endOfMonth()->timestamp;
        } elseif ($type == ReceiveCodeModel::CONDITION_DATE_MONTH) {
            //本月
            $startTime = Carbon::now()->startOfMonth()->timestamp;
            $endTime = $time;
        } elseif ($type == ReceiveCodeModel::CONDITION_DATE_YESTERDAY) {
            //昨天
            $startTime = Carbon::yesterday()->startOfDay()->timestamp;
            $endTime = Carbon::yesterday()->endOfDay()->timestamp;
        } elseif ($type == ReceiveCodeModel::CONDITION_DATE_TODAY) {
            //今天
            $startTime = Carbon::now()->startOfDay()->timestamp;
            $endTime = $time;
        } elseif ($type == ReceiveCodeModel::CONDITION_DATE_WEEK) {
            //本周
            $startTime = Carbon::now()->startOfWeek()->timestamp;
            $endTime = $time;
        } elseif ($type == ReceiveCodeModel::CONDITION_DATE_SEVEN) {
            //七天内
            $startTime = Carbon::now()->addDay(-7)->timestamp;
            $endTime = $time;
        } else {
            //所有
            $startTime = 0;
            $endTime = $time;
        }
        return ['start_time' => $startTime, 'end_time' => $endTime];
    }

    //指定用户领取领取码
    public function userReceiveCode($uid)
    {
        //获取开启自动领取的领取码
        $condition = [];
        $condition[] = ['is_auto', '=', 1];
        $condition[] = ['status', '=', 1];
        $codeList = ReceiveCodeModel::where($condition)->select();
        foreach ($codeList as $v) {
            try {
                //组合领取码条件
                $this->uid = $uid;
                $this->param = [
                    'code' => $v->code,
                ];
                //不能用语言，后台执行的语言为中文
                $result = $this->receive(false, false);
                if ($result['code'] == 1) {
                    //添加用户日志
                    UserAdminLogModel::add_log($uid, "自动领取领取码[{$v->code}],领取金额: {$result['data']['amount']},增加流水: {$result['data']['savings']}", '领取码领取');
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return true;
    }

    private function clear_cache($receiveCodeDetail, $uid, $ip)
    {
        $this->getCodeReceiveNum($receiveCodeDetail->id, $uid, true, false);
        $this->getCodeReceiveNum($receiveCodeDetail->id, 0, true, false);
        $this->getIpReceiveNum($receiveCodeDetail, $ip, true);
    }
}