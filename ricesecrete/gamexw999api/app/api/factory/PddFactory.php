<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\TokenCheck;
use app\common\lib\Util;
use app\common\model\PddInviteRecordModel;
use app\common\model\PddNewsModel;
use app\common\model\PddPhoneModel;
use app\common\model\PddTurntableModel;
use app\common\model\PddTurntableRecordModel;
use app\common\model\PddWithdrawalRecordModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\model\UserOfflineModel;
use app\common\service\AgentService;

use app\common\service\MemberService;
use app\common\service\MessageService;
use app\common\service\WalletService;
use Carbon\Carbon;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Request;

class PddFactory
{
    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        if (!$this->uid) {
            $header = request()->header();
            try {
                $token = TokenCheck::run($header);
                if ($token) {
                    $this->uid = $token->uid;
                }
            } catch (\Exception $e) {
            }
        }
    }

    //获取转盘信息
    public function getTurntable()
    {
        $returnData = [
            'code' => 1,
            'data' => []
        ];
        $lang = Lang::getLangSet();
        //获取已取款次数
        $withdrawalNum = $this->getWithdrawalCount($this->uid);
        //获取转盘数据
        $condition = [];
        $condition[] = ['people_num', '>', $withdrawalNum];
        $condition[] = ['status', '=', 1];
        $condition[] = ['lang', 'in', [$lang, '']];
        $condition1 = AgentService::getAgentWhere();


        //时间条件
        $time = time();
        $condition1 .= " AND ((start_time = 0 || end_time = 0) || (start_time <= {$time} and end_time >= {$time}) || (start_time <= {$time} and end_time = 0))";

        //会员注册后条件
        $userDetail = '';
        if ($this->uid) {
            $userDetail = UserModel::where('id', '=', $this->uid)->find();
            $registerDays = intval(($time - $userDetail->add_time) / 86400);
            $condition1 .= " AND (register_day_show = 0 OR register_day_show >= $registerDays)";
        } else {
            $condition1 .= ' AND register_day_show = 0';
        }

        $turntableDetail = PddTurntableModel::where($condition)->where($condition1)->find();
        if (!$turntableDetail) {
            return $returnData;
        }
        //获取用户已转出的金额
        $amountData = $this->getUidTurntableAmount($this->uid, $turntableDetail);
        $remainTime = $amountData['remain_time'];#剩余时间
        $amount = $amountData['amount'];#已抽奖金额


        //获取已提款的三十名名用户
        $limit = 30;
        $withdrawalUserList = PddWithdrawalRecordModel::field('username,amount')
            ->page(1, $limit)->order('create_time')->select();
        $withdrawalUserList = $withdrawalUserList->toArray();
        //判断是否开启虚假提现记录
        if (env('PDD_IS_OPEN_FALSE_DATA', 0) && ($withdrawalUserCount = count($withdrawalUserList)) < $limit) {
            //生成假数据
            $falseData = $this->createFalseWithdrawalData($turntableDetail->amount, $limit - $withdrawalUserCount);
            $withdrawalUserList = array_merge($withdrawalUserList, $falseData);
        }
        foreach ($withdrawalUserList as $k => $v){
            $withdrawalUserList[$k]['amount'] = round($v['amount'], 2);
        }

        //获取自己的邀请记录
        $userInviteRecord = [];
        if ($this->uid) {
            $condition = [];
            $condition[] = ['uid', '=', $this->uid];
            $condition[] = ['invite_uid', '<>', $this->uid];
            $condition[] = ['status', '=', 1];
            $condition[] = ['expire_time', '>=', time()];
            $condition[] = ['turntable_id', '=', $turntableDetail->id];
            $userInviteRecord = PddInviteRecordModel::where($condition)
                ->field('username,invite_username,amount')
                ->order('create_time desc')->select()->toArray();
            foreach ($userInviteRecord as $k => $v){
                $userInviteRecord[$k]['amount'] = round($v['amount'], 2);
            }
        }
        $remainTurntableNum = $this->getTurntableCount($this->uid, $turntableDetail);#剩余转动次数
        if ($amount == 0) {
            //第一次加一次转动获取转盘首次和转动余额
            $remainTurntableNum = max($turntableDetail->start_give_count, 0);
        }

        //获取当前货币符号
        $currentSymbol = Util::i18n_currency_no_amount();
        $turntableDetail->introduce = $this->getIntroduce($turntableDetail, $lang);
        $_condition = [];
        $_condition[] = ['pdd_id', '=', $turntableDetail->id];
        $_condition[] = ['lang', '=', $lang];
        $pddContentDetail = PddNewsModel::where($_condition)->find();
        $turntableDetail->image = '';
        if( $pddContentDetail ){
            $turntableDetail->introduce = $pddContentDetail->news_content;
            $turntableDetail->title = $pddContentDetail->news_title;
            $turntableDetail->image = $pddContentDetail->news_images;
        }
        $returnData['data'] = [
            'id' => $turntableDetail->id, #id
            'title' => $turntableDetail->title, #标题
            'image' => $turntableDetail->image, #标题
            'introduce' => $turntableDetail->introduce,#介绍
            'amount' => round($turntableDetail->amount, 2),#奖励金额
            'awarded_amount' => round($amount, 2),#已奖励金额
            'time' => $turntableDetail->time,#活动要求时间
            'remain_time' => $remainTime,#剩余时间
            'invite_url' => $userDetail ? getAppUrl() . "/#/?share_code=" . $userDetail->rec_code : getAppUrl(),#邀请链接
            'withdrawal_user_list' => $withdrawalUserList,#提现用户列表
            'user_invite_record' => $userInviteRecord,#用户邀请记录
            'remain_turntable_count' => $remainTurntableNum,#剩余转盘次数
            'phone_list' => $this->getGenerateBrazilianPhoneNumber($this->uid, 24),#随机生成巴西号码
            'current_symbol' => $currentSymbol,
        ];
        return $returnData;
    }

    /**
     * 获取推广链接
     * @param $recCode
     * @return string
     */
    private function getShareurl($recCode)
    {
        //是否关闭邀请链接
        if (env('IS_CLOSE_INVITE_URL', 0) == 1) {
            return '';
        }
        //获取域名
        $domainEnv = env('SHARE_URL', '');
        $domain = getShareDomain();
        if ($domainEnv) {
            $url = $domain . '/register?share_code=' . $recCode;
        } else {
            $url = $domain . "/#/?share_code=" . $recCode . "&modal=LoginRegistration&data=tab-1";
        }
        return $url;
    }

    //转盘抽奖
    public function turntable()
    {
        //获取转盘数据
        $turntableDetail = PddTurntableModel::where('id', '=', $this->param['id'])->find();
        if (!$turntableDetail) {
            ajax_return('该活动已结束');
        }
        //获取用户已转出的金额
        $amountData = $this->getUidTurntableAmount($this->uid, $turntableDetail);
        $remainTime = $amountData['remain_time'];#剩余时间
        $amount = $amountData['amount'];#已抽奖金额
        if ($amount > 0) {
            //判断是否能抽奖
            if ($this->getTurntableCount($this->uid, $turntableDetail) < 1) {
                ajax_return('抽奖次数不足');
            }
            if ($remainTime <= 0) {
                ajax_return('本次活动已结束，请刷新后重试');
            }
        }
        if ($amount >= $turntableDetail->amount) {
            ajax_return('已达到取款金额');
        }

        //验证实名认证
        if( !MemberService::judgeUserIsCanReceiveDiscount($this->uid) ){
            ajax_return('请先实名后再参与');
        }

        //获取用户数据
        $userDetail = UserModel::where('id', '=', $this->uid)->find();

        //双钱包判断当前用户是否可以重复领取优惠
        if( !WalletService::judgeUserIsRePeatReceive(0, $userDetail) ){
            ajax_return('你的账户还存在彩金，请使用完后再来领取');
        }

        $type = PddInviteRecordModel::TYPE_TURNTABLE_BALANCE;#类型1转盘余额，2钱包余额
        $turntableAmount = 0;
        //是否设置赠送钱包余额
        if ($amount != 0 && $turntableDetail->amount_range && $turntableDetail->amount_odds > 0) {
            //钱包余额
            $turntableAmount = $this->getTurntableAmountWallet($turntableDetail->amount_range, $turntableDetail->amount_odds);
            $type = PddInviteRecordModel::TYPE_WALLET_BALANCE;
        }
        if (!$turntableAmount) {
            //钱包金额没有中奖获取转盘金额
            $turntableAmount = $this->getTurntableAmount($this->uid, $amount, $turntableDetail);
            $type = PddInviteRecordModel::TYPE_TURNTABLE_BALANCE;#转盘余额
        }


        $time = time();
        if ($amount == 0) {
            //判断是否设置首次转盘次数
            if ($turntableDetail->start_give_count <= 0) {
                ajax_return('抽奖次数不足');
            }
            //第一次抽奖生成抽奖记录
            $_id = (new PddInviteRecordModel())->insertGetId([
                'uid' => $this->uid,
                'type' => $type,
                'username' => hideStr($userDetail->username),
                'invite_uid' => $this->uid,
                'invite_username' => hideStr($userDetail->username),
                'turntable_id' => $this->param['id'],
                'amount' => $turntableAmount,
                'turntable_num' => $turntableDetail->start_give_count,
                'finish_turntable_num' => 1,
                'status' => 1,
                'expire_time' => $time + ($turntableDetail->time * 3600),
                'create_time' => $time,
                'update_time' => $time,
            ]);
            //生成转盘记录
            PddTurntableRecordModel::create([
                'turntable_id' => $turntableDetail->id,
                'invite_record_id' => $_id,
                'uid' => $this->uid,
                'type' => $type,
                'amount' => $turntableAmount,
                'withdrawal_amount' => 0,
                'is_withdrawal' => 0,
                'create_time' => $time,
                'update_time' => $time,
            ]);
        } else {
            //修改邀请记录
            $condition = [];
            $condition[] = ['uid', '=', $this->uid];
            $condition[] = ['expire_time', '>=', $time];
            $condition[] = ['turntable_id', '=', $turntableDetail->id];
            $_detail = PddInviteRecordModel::where($condition)->having('turntable_num>finish_turntable_num')->order('id asc')->find();

            $data = [
                'status' => 1,
                'type' => $type,
                'finish_turntable_num' => $_detail->finish_turntable_num + 1,
            ];
            //计算取款金额
            if ($type == PddInviteRecordModel::TYPE_WALLET_BALANCE) {
                //钱包余额类型时转出的金额为取款金额
                $withdrawalAmount = $turntableAmount;
            } else {
                $withdrawalAmount = $amount >= $turntableDetail->amount ? $turntableDetail->amount : 0;
                $data['amount'] = $_detail->amount + $turntableAmount;
            }
            $data['withdrawal_amount'] = $_detail->withdrawal_amount + $withdrawalAmount;

            Db::startTrans();
            try {
                if ($_detail->is_withdrawal == 0) {
                    $data['is_withdrawal'] = $type == PddInviteRecordModel::TYPE_WALLET_BALANCE ? 1 : 0;
                }
                $_detail->save($data);
                //生成转盘记录
                PddTurntableRecordModel::create([
                    'turntable_id' => $turntableDetail->id,
                    'invite_record_id' => $_detail->id,
                    'uid' => $this->uid,
                    'type' => $type,
                    'amount' => $turntableAmount,
                    'withdrawal_amount' => $withdrawalAmount,
                    'is_withdrawal' => $type == PddInviteRecordModel::TYPE_WALLET_BALANCE ? 1 : 0,
                    'create_time' => $time,
                    'update_time' => $time,
                ]);
                if ($turntableAmount > 0 && $type == PddInviteRecordModel::TYPE_WALLET_BALANCE) {
                    //增加用户余额
                    $lang = Lang::getLangSet();
                    $currency = Util::i18n_currency_no_amount();
                    //随机赠送流水倍数 调整为可独立设置倍数
                    UserModel::cz_balance($this->uid, $turntableAmount, "+", true, "转盘奖励", 15, $turntableDetail->give_savings, 1, $_detail->id, true, 101);
                    $msgContent = lang('转盘奖励，奖励金额') . '{:price} {:currency}';
                    $msgData = ['price' => $turntableDetail->amount, 'currency' => $currency];
                    UserMsgModel::add_msg($this->uid, '转盘奖励', $msgContent, 21, $_detail->id, $msgData, $lang);
                }
                Db::commit();
            } catch (\Exception $e) {
                Db::rollback();
                ajax_return('网络错误');
            }
        }
        $this->clearCache($this->uid, $turntableDetail);
        return ['code' => 1, 'data' => ['amount' => $turntableAmount, 'type' => $type]];
    }

    //取款
    public function withdrawal()
    {
        //获取转盘数据
        $turntableDetail = PddTurntableModel::where('id', '=', $this->param['id'])->find();
        if (!$turntableDetail) {
            ajax_return('该活动已结束');
        }
        //判断是否能取款
        if (!$this->judgeUserIsWithdrawal($this->uid, $turntableDetail)) {
            ajax_return('未达到取款金额');
        }
        $lang = Lang::getLangSet();
        $currency = Util::i18n_currency_no_amount();
        //获取用户数据
        $userDetail = UserModel::where('id', '=', $this->uid)->find();
        Db::startTrans();
        try {

            //生成提款记录
            $recordId = (new PddWithdrawalRecordModel())->insertGetId([
                'uid' => $this->uid,
                'username' => hideStr($userDetail->username),
                'turntable_id' => $turntableDetail->id,
                'amount' => $turntableDetail->amount,
                'savings' => $turntableDetail->savings,
                'create_time' => time(),
                'update_time' => time(),
            ]);
            //修改取款状态
            $condition = [];
            $condition[] = ['uid', '=', $this->uid];
            $condition[] = ['withdrawal_amount', '>', 0];
            $condition[] = ['is_withdrawal', '=', 0];
            $condition[] = ['turntable_id', '=', $turntableDetail->id];
            $condition[] = ['type', '=', PddInviteRecordModel::TYPE_TURNTABLE_BALANCE];
            PddInviteRecordModel::where($condition)->update([
                'is_withdrawal' => 1,
            ]);
            //将该期间内的邀请的用户过期时间改为过期（当前时间）
            $condition = [];
            $condition[] = ['uid', '=', $this->uid];
            PddInviteRecordModel::where($condition)->update([
                'expire_time' => time(),
            ]);
            //余额奖励
            UserModel::cz_balance($this->uid, $turntableDetail->amount, "+", true, "转盘奖励", 15, $turntableDetail->savings, 1, $recordId, true, 101);
            $msgContent = lang('转盘奖励，奖励金额') . "{:price} {:currency}";
            $msgData = ['price' => $turntableDetail->amount, 'currency' => $currency];
            UserMsgModel::add_msg($this->uid, '转盘奖励', $msgContent, 21, $recordId, $msgData, $lang);
            Db::commit();
            $this->clearCache($this->uid, $turntableDetail);
        } catch (\Exception $e) {
            Db::rollback();
            ajax_return('网络错误');
        }
        return ['code' => 1, 'data' => []];
    }

    //生成假提现数据
    private function createFalseWithdrawalData($amount, $num)
    {
        $arr = [];
        for ($i = 1; $i <= $num; $i++) {
            $_arr = [];
            $_arr['username'] = hideStr(Util::random(10));
            $_arr['amount'] = $amount;
            $arr[] = $_arr;
        }
        return $arr;
    }

    //判断用户是否能取款
    public function judgeUserIsWithdrawal($uid, $turntableDetail)
    {
        $apiRedis = new ApiRedis();
        $key = 'judge_user_is_can_withdrawal_' . $uid . '_' . $turntableDetail->id;
        //从缓存获取
        if ($apiRedis->has($key)) {
            return $apiRedis->get($key);
        }
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['amount', '>', 0];
        $condition[] = ['expire_time', '>=', time()];
        $condition[] = ['turntable_id', '=', $turntableDetail->id];
        $condition[] = ['type', '=', PddInviteRecordModel::TYPE_TURNTABLE_BALANCE];
        $isWithdrawal = 0;
        if (PddInviteRecordModel::where($condition)->sum('amount') >= $turntableDetail->amount) {
            $isWithdrawal = 1;
        }
        $apiRedis->set($key, $isWithdrawal, 60 * 5);
        return $isWithdrawal;
    }

    //获取本次抽奖金额（赠送到余额）
    public function getTurntableAmountWallet($amount, $odds)
    {
        if (!judgeOddsIsTrue($odds)) {
            //没有中奖，返回0
            return 0;
        }
        //中奖计算获得金额
        if (strpos($amount, '-') !== false) {
            //范围，随机获得
            $initAmountArr = explode('-', $amount);
            return returnAmountByRange($initAmountArr[0], $initAmountArr[1]);
        }
        return $amount;
    }

    //获取本次抽奖金额
    private function getTurntableAmount($uid, $amount, $turntableDetail)
    {
        if ($amount == 0) {
            //第一次抽奖，获取初始抽奖金额
            if (strpos($turntableDetail->init_amount, '-') !== false) {
                //范围，随机获得
                $initAmountArr = explode('-', $turntableDetail->init_amount);
                return returnAmountByRange($initAmountArr[0], $initAmountArr[1], 2);
            }
            return $turntableDetail->init_amount;
        }
        //计算获得金额
        return $this->countAmount($uid, $amount, $turntableDetail);
    }

    //计算中奖金额
    public function countAmount($uid, $amount, $turntableDetail)
    {
        //判断是否是除去最后0.01次数的抽奖，不是直接返回0.01
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['status', '=', 1];
        $condition[] = ['amount', '>', 0];
        $condition[] = ['expire_time', '>=', time()];
        $condition[] = ['turntable_id', '=', $turntableDetail->id];
        $currentCount = PddInviteRecordModel::where($condition)->sum('finish_turntable_num');#当前抽奖次数
        if ($currentCount == 2) {
            //如果是第二次抽奖 必中
            if (strpos($turntableDetail->odds_of_winning_range, '-') !== false) {
                //范围，随机获得
                $winningRangeArr = explode('-', $turntableDetail->odds_of_winning_range);
                return rand($winningRangeArr[0] * 100, $winningRangeArr[1] * 100) / 100;
            } elseif ($turntableDetail->odds_of_winning_range > 0) {
                return $turntableDetail->odds_of_winning_range;
            }
        }
        //判断是否中将
        if ($turntableDetail->odds_of_winning < rand(1, 100)) {
            //未中奖
            return 0;
        }
        //计算剩余金额
        $remainAmount = $turntableDetail->amount - $amount;
        //最低次数中奖金额
        $minimumAmount = $turntableDetail->minimum_amount * 0.01;
        if ($turntableDetail->min_invite_people - $turntableDetail->minimum_amount == $currentCount) {
            //如果次数到到最后次数，将抽中除了最低金额所需的金额剩下的金额
            $endAmount = $remainAmount - $minimumAmount;
            return $endAmount < 0 ? 0.01 : $endAmount;
        }

        //到达最后条件时需要邀请充值用户才能中奖
        $lastCount = $turntableDetail->min_invite_people - $turntableDetail->minimum_amount;
        if ($currentCount > $lastCount) {
            //最后邀请用户必须要满足有效充值条件，否则为不中奖
            #获取当前邀请开始的时间
            $condition = [];
            $condition[] = ['uid', '=', $uid];
            $condition[] = ['invite_uid', '=', $uid];
            $condition[] = ['expire_time', '>=', time()];
            $condition[] = ['turntable_id', '=', $turntableDetail->id];
            $time = PddInviteRecordModel::where($condition)->value('create_time') ?: 0;
            #当前邀请的有效充值用户
            $inviteCount = UserFactory::getUserInvitePerson($uid, $time);
            if ($inviteCount < $currentCount - $lastCount) {
                return 0;
            }
            return 0.01;
        }
        //计算随机数最大金额
        $maxAmount = round($remainAmount - $minimumAmount - (0.01 * ($lastCount - $currentCount - 1)), 2);
        $maxAmount = $maxAmount > 0 ? $maxAmount : 0.01;

        $min = 0.01; // 最小值
        $max = $maxAmount; // 最大值
        $decimalPlaces = 2; // 小数点位数
        $returnAmount = mt_rand($min * pow(10, $decimalPlaces), $max * pow(10, $decimalPlaces)) / pow(10, $decimalPlaces);
        return $returnAmount;
    }

    //获取转盘转动次数
    public function getTurntableCount($uid, $turntableDetail)
    {
        $apiRedis = new ApiRedis();
        $key = 'get_turntable_count_v2_' . $uid;
        //TODO 从缓存获取，该方法不能缓存，不然充值后次数不及时
        if ($apiRedis->has($key)) {
//            return $apiRedis->get($key);
        }

        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['expire_time', '>=', time()];
        $condition[] = ['turntable_id', '=', $turntableDetail->id];
        $remainTurntableNum = PddInviteRecordModel::where($condition)->sum(Db::raw('turntable_num-finish_turntable_num'));
        $apiRedis->set($key, $remainTurntableNum);
        return $remainTurntableNum;
    }


    //获取转盘对应的数据
    public function getUidTurntableAmount($uid, $turntableDetail)
    {
        $time = time();
        $condition = [];
        $condition[] = ['expire_time', '>=', $time];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['turntable_id', '=', $turntableDetail->id];
        $amount = PddInviteRecordModel::where($condition)->sum('amount');
        $expireTime = PddInviteRecordModel::where($condition)->order('create_time desc')->value('expire_time');
        return ['amount' => $amount, 'expire_time' => $expireTime, 'remain_time' => $expireTime ? $expireTime - $time : 0];
    }

    //获取用户取款次数
    public function getWithdrawalCount($uid)
    {
        $apiRedis = new ApiRedis();
        $key = 'get_uid_withdrawal_count_' . $uid;
        //从缓存获取
        if ($apiRedis->has($key)) {
            return $apiRedis->get($key);
        }
        //没有从数据库中获取
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $num = PddWithdrawalRecordModel::where($condition)->count();
        $apiRedis->set($key, $num);
        return $num;
    }

    //获取指定数量的巴西手机号
    public function getGenerateBrazilianPhoneNumber($uid = 0, $num = 10)
    {
        //数据库没有则随机生成
        $apiRedis = new ApiRedis();
        $key = 'get_uid_Brazilian_rand_phone_number_' . $uid . '_' . $num;
        //从缓存获取
        if ($apiRedis->has($key)) {
            return json_decode($apiRedis->get($key), true);
        }

        //先从数据库获取
        $databaseArr = (new PddPhoneModel())->orderRaw('RAND()')->limit($num)->column('phone');
        if (count($databaseArr) < $num) {
            $num = $num - count($databaseArr);
            if ($num > 0) {
                $arr = [];
                for ($i = 0; $i < $num; $i++) {
                    $arr[] = $this->generateBrazilianPhoneNumber();
                }
                $databaseArr = array_merge($databaseArr, $arr);
            }
        }
        $apiRedis->set($key, json_encode($databaseArr), 5 * 60);
        return $databaseArr;
    }

    public function generateBrazilianPhoneNumber()
    {
        // 巴西手机号码的格式通常是：(XX) 9XXXX-XXXX，其中X是数字
        $areaCode = $this->generateRandomNumber(2); // 生成区号
        $prefix = '9' . $this->generateRandomNumber(4); // 生成前缀
        $lineNumber = $this->generateRandomNumber(4); // 生成线路号

//        return "($areaCode) $prefix-$lineNumber";
        return "55$areaCode$prefix$lineNumber";
    }

    // 生成指定位数的随机数字
    private function generateRandomNumber($length)
    {
        return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    public function clearCache($uid, $turntableDetail = null)
    {
        $apiRedis = new ApiRedis();
        $key = 'get_uid_withdrawal_count_' . $uid;
        $apiRedis->delete($key);
        $key = 'judge_user_is_can_withdrawal_' . $uid . '_' . $turntableDetail->id;
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
            'pdd_id' => $turntableDetail->id,
            'lang' => $lang
        ];
        $temp = PddNewsModel::where($map)->find();
        if (empty($temp)) {
            return $turntableDetail->introduce;
        }
        return $temp['news_content'];
    }

    //推送活动完成情况给用户
    public function pushMessageToFinishTaskUser($uid = 0){
        $apiRedis=new ApiRedis();
        //获取用户数据
        $userDetail = UserModel::where("id", $uid)->field('tree,level_id')->find();
        if( !$userDetail ){
            return false;
        }
        //获取转盘信息
        $turntableDetail = $this->getTurntable()['data'];
        if( !$turntableDetail ){
            return false;
        }
        //两小时内推送过则不再推送
        $pushUserKey = 'push_user_finish_pdd_key_'.$uid.'_'.$turntableDetail['id'];
        if( $apiRedis->has($pushUserKey) ){
            return false;
        }
        if( $turntableDetail['remain_turntable_count'] >= 1 ){
            //完成任务且没有领取给用户发送没有领取通知
            MessageService::sendTaskFinishMessageNotify( $uid, 'pdd', $turntableDetail['remain_turntable_count']);
            //设置缓存两小时内不再推送
            $apiRedis->set($pushUserKey, 1, 3600 * 2);
        }
        return true;
    }
}