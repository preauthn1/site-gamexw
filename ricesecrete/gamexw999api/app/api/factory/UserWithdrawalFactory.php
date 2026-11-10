<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\model\ActionApplyModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserAwardCfgsModel;
use app\common\model\UserBankModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\redis\CommomRedis;
use app\common\service\BetService;
use app\common\service\MemberService;
use app\common\service\MessageService;
use app\common\service\UserWithdrawalService;
use app\common\service\WalletService;
use think\facade\Db;
use think\facade\Request;

class UserWithdrawalFactory
{
    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->uid = $uid;
    }

    //提现
    public function apply_run()
    {
        $info = $this->param;
        $ispass = 1;//isset($info['ispass'])?$info['ispass']:0;
        $phone = isset($info['phone']) ? $info['phone'] : '';
        $wpass = isset($info['wpass']) ? $info['wpass'] : '';
        $google_code = $info['google_code'] ?? null;

        $ApiRedis = new ApiRedis();
        $CommomRedis = new CommomRedis();
        $sys = $ApiRedis->systemInfo();
        if ($sys['is_withdraw'] == 0) {
            ajax_return("提现系统维护中", 0);
        }
        //判断是否开启提现实名认证验证
        if ($sys['withdraw_user_verify'] == 1) {
            $status = UserVerifyFactory::userVerifyStatus($this->uid);
            if ($status != 2) {
                ajax_return("用户未实名制认证", 0);
            }
        }
        //判断是否开启提现密码验证
        if ($sys['pay_passwd_verify'] == 1) {
            //验证支付密码
            if (!$wpass) {
                ajax_return('请输入6位数字密码');
            }
            if (!is_numeric($wpass) || strlen($wpass) != 6) {
                ajax_return('密码格式为6位数字');
            }
            $user = UserModel::where('id', $this->uid)->field("withdrawal_psd")->find();
            if ($user['withdrawal_psd'] != Util::winMd5Password($wpass)) {
                ajax_return("支付密码错误", 0);
            }
        }
        if ($sys['test_withdraw'] == 0) {
            $user = UserModel::uinfo($this->uid, ",a.lebal");//
            if ($user['lebal'] == 2) {
                ajax_return("测试用户不能提现", 0);
            }
        }
        if (MemberService::tagBanWithdraw($this->uid)) {
            ajax_return("您无法申请提现,请联系客服", 0);
        }

        //锁
//        if (!$ApiRedis->lock("withdraw".$this->uid, 60)) {
//            ajax_return("请勿频繁访问", 0);
//        }

        $sys = $ApiRedis->systemInfo();
        $bk = UserBankModel::where("uid", $this->uid)->with('currencyApi')->where("id", $info['bank_id'])->find();
        if (!isset($bk['id'])) {
            ajax_return("用户银行卡不存在！", 0);
        }
        //判断该银行卡是否提现失败过
        $withdrawalErrRedisKey = 'withdrawal_error_bank_key_' . $this->uid . '_' . $info['bank_id'];
        if ($ApiRedis->has($withdrawalErrRedisKey) && env('withdrawal_err_tip', 0)) {
            //只提示一次，提示完删除
            $ApiRedis->delete($withdrawalErrRedisKey);
            ajax_return('该卡号上次提款时异常,请确保该提款卡号正确,是否继续使用该卡号提款?');
        }


        if (env('withdraw_close_open_not', 0) == 1) {
            $banks = $CommomRedis->getBankNames();
            $isExist = false;
            foreach ($banks as $v) {
                if (strtoupper($bk['bank_name']) == strtoupper($v)) {
                    $isExist = true;
                }
            }
            if (!$isExist) {
                ajax_return("该银行暂不支持提现", 0);
            }
        }


        if (!isset($sys['withdraw_amcount'])) $sys['withdraw_amcount'] = 0;
        if ($info['w_price'] % 10 != 0 && $sys['withdraw_amcount'] == 0) {
            ajax_return("提现金额必须为10的倍数，请重新输入", 0);
        }

        if ($info['w_price'] % 100 != 0 && $sys['withdraw_amcount'] == 1) {
            ajax_return("提现金额必须为100的倍数，请重新输入", 0);
        }

        $add_time = UserWithdrawalModel::where("uid", $this->uid)->order("id desc")->value('add_time');//获取上一次提现时间
        if ($add_time + 10 > time()) {
            ajax_return("请勿频繁访问", 0);
        }
        $bk['pay_type'] = $bk['bank_type'];
        $currency = Util::getPayCurrency($bk, 1);

        $user = UserModel::uinfo($this->uid, ",a.water_balance,b.min_amount,b.max_amount,b.day_limit,b.day_free_limit,a.savings,a.mail,a.phone,a.is_check_phone,a.is_check_phone, b.day_max_amount");//查看当前用户流水和最低提现金额

        //判断是否验证首次提现 0不需要验证 1需要验证
        $data['ver_withdrawal_first'] = 0;
        $withdrawal_count = UserWithdrawalModel::where('uid', $this->uid)->count('id');
        //判断是否验证首次提现
        if ($sys['withdrawal_status'] == 1) {
            if ($withdrawal_count == 0) {
                if ($user['phone'] !== $phone) {
                    ajax_return("号码不正确，请联系客服", 0);
                }
                //验证短信验证码
                $ApiRedis = new ApiRedis();
                $redisCode = $ApiRedis->get($phone . "_code");
                $verify_code = $info['first_withdrawal_code'] ?? '';
                if (empty($verify_code)) {
                    ajax_return("请输入手机验证码", 0);
                }
                if ($verify_code != $redisCode) {
                    ajax_return("手机验证码错误", 0);
                }
            }
        }

//        //用户开启谷歌验证
//        if(isset($sys['user_google_status']) && $sys['user_google_status'] == 1) {
//            $check_type = 'withdrawal_check';
//            if (empty($google_code) && UserGoogleModel::isVerfit($user['id'],$check_type)) {
//                    throw new MessageException(lang('请填写谷歌验证码'), 109);
//            }
//            $google = UserGoogleModel::verification($user['id'],$check_type, $google_code);
//            if($google){
//                throw new MessageException($google, 110);
//            }
//        }


        if (isset($user['day_max_amount']) && $user['day_max_amount'] > 0) {
            $user_day = UserWithdrawalModel::where("uid", $this->uid)->where('status', 'not in', [2, -1])->whereDay('add_time')->sum('w_price');
            $user_day += $info['w_price'];
            if ($user_day > $user['day_max_amount']) {
                $msg = lang("当前会员等级每天最多只能提现");
                ajax_return($msg . ' ' . $user['day_max_amount'], 0);
            }
        }
        if (!($info['w_price'] >= $user['min_amount'] && $info['w_price'] <= $user['max_amount'])) {
            $msg = lang("当前会员等级只能提现金额%s到%s");
            ajax_return(sprintf($msg, $user['min_amount'], $user['max_amount']), 0);
        }
        if (!($user['balance'] >= $info['w_price'])) {
            ajax_return("余额不足", 0);
        }
        // 提现是否必须要求有充值 0 为必须，1为不必须
        $UserOffline = env('WITHDRAW_NOT_REQ_RECHARGE', 0);
        if ($UserOffline == 0) {
            $UserOffline = UserOfflineModel::where([['uid', "=", $this->uid], ['status', "in", "3,4"]])->value("id") ? 1 : 0;
            if ($UserOffline == 0) {
                ajax_return('必须要充值才能提现');
            }
        }
        //获取指定游戏未完成流水
        $appointWater = UserAwardCfgsModel::where('uac_uid', '=', $this->uid)->sum(Db::raw('uac_water-uac_waterdone'));
        $totalSavings = $user['savings'] + $appointWater;


        if ($sys['withdrawal_rules'] == 1) {
            //单流水
            //判断是否流水为零才能提现
            if (isset($sys['saving_zero_withdrawal']) && $sys['saving_zero_withdrawal'] == 1 && $totalSavings > 0) {
                ajax_return(sprintf(lang('您还需：%s流水才能提现'), $totalSavings));
            }
            $remainWater = $totalSavings - ($user['balance'] - $info['w_price']);
            if ($UserOffline == 0 || ($totalSavings > 0 && $remainWater > 0)) {
                ajax_return(sprintf(lang('您还需：%s流水才能提现'), $totalSavings));
//                ajax_return("提现流水倍数不满足", 0);
            }
        } else if ($sys['withdrawal_rules'] == 2) {
            //TODO  没用了
            $rechargeM = UserOfflineModel::where([['uid', "=", $this->uid], ['status', "in", "3,4"]])->sum('arrival_price');  //充值金额
            $ArtificialM = ArtificialBalanceModel::alias("a")->join("dq_user_balance_mx b", "a.mx_id=b.id")->where([["a.a_type", 'in', '1,3']])->where(['b.uid' => $this->uid])->sum("b.price*water");//赠送彩金
            $action_apply_price = ActionApplyModel::where('ps_status', '=', '1')->where('uid', '=', $this->uid)->sum('water');
            $tables = BetService::getThirdStatsTable('');
            $field = "SUM(gb_validwater) as gb_validwater_sum";
            $temp = Db::table($tables['stats_global'])->field($field)->where("gb_winlose <> 0 and gb_uid=" . $this->uid)->find();
            if (!empty($temp)) {
                $game_zhudan = $temp['gb_validwater_sum'];
            } else {
                $game_zhudan = 0;
            }
//            $game_zhudan = GameBets::operationUserTotalBetAmount("gb_winlose <> 0 and gb_uid=" . $this->uid);
            $commissionM = 0;
            if ($sys['commission_water'] > 0) {
                $commissionM = AgentRakeBackModel::where([['uid', '=', $this->uid], ['status', 'in', '3,4']])->sum('savings');//代理返佣的流水
            }
            if ($game_zhudan < round($sys['w_water'] * $rechargeM, 2) + $ArtificialM + $commissionM + $action_apply_price && $UserOffline > 0) {
                ajax_return("提现流水倍数不满足", 0);
            }
        }

        $date = [];
        $date['start_time'] = strtotime(date("Y-m-d") . " 00:00:00");
        $date['end_time'] = strtotime(date("Y-m-d") . " 23:59:59");
        $now_w = UserWithdrawalModel::where("uid", $this->uid)->where('status', 'not in', [2, -1])
            ->where("add_time>=" . $date['start_time'] . " and add_time<=" . $date['end_time'])
            ->count();//查看今日提现次数

        if ($now_w >= $user['day_limit']) {
            ajax_return("当天提现次数已达上限", 0);
        }
        if (isset($sys['user_google_status']) && $sys['user_google_status'] == 1) {
            $verify_code = $info['verify_code'] ?? null;
            MemberService::checkCode($user, $verify_code, 'withdrawal_check');
        }
        //判断提现数量
        if (isset($sys['allow_not_withdrawal']) && $sys['allow_not_withdrawal'] > 0) {
            //获取未审核提现订单数量
            $condition = [];
            $condition[] = ['uid', '=', $this->uid];
            $condition[] = ['status', '=', 1];
            $noCheckNum = UserWithdrawalModel::where($condition)->count();
            if ($noCheckNum >= $sys['allow_not_withdrawal']) {
                ajax_return('你当前有未审核的订单，请耐心等待审核');
            }
        }

        $wit_num = 1;
        if ($bk['bank_type'] == 1 && $sys['free_poundage'] > 0) {
            $withdrawal_num = UserWithdrawalModel::where(['uid' => $this->uid, 'channel' => 1])->where('status', 'not in', [2, -1])
                ->where("add_time>=" . $date['start_time'] . " and add_time<=" . $date['end_time'])
                ->count();//查看今日提现次数
            if ($withdrawal_num < $sys['free_poundage']) { //当提现次数 小于后台设置的 提现免手续次数时 手续费设置为0
                $wit_num = 0;
            }
        }
        if ($now_w < $user['day_free_limit']) {
            //如果提现次数小于当前VIP等级限制的免手续费次数 手续费设置为0
            $wit_num = 0;
        }
        Db::startTrans();
        try {
            $userlock = UserModel::where("id", $this->uid)->lock(true)->field("balance")->find();
            if ($userlock['balance'] < $info['w_price']) {
                throw new \Exception("余额不足", 0);
            }

            $fee = $sys['service_charge'];
            $is_usdt_fee = env('is_usdt_fee', 0);
            if ($bk['bank_type'] == 1 && $is_usdt_fee == 1) {
                $fee = $sys['usdt_charge'];
            }
            $add['add_time'] = time();
            $add['uid'] = $this->uid;
            $add['channel'] = $bk['bank_type'];
            $add['bank_id'] = $info['bank_id'];
            $add['w_price'] = $info['w_price'];
            $add['arrival_price'] = round(($info['w_price'] - ($wit_num ? round($info['w_price'] * ($fee / 100), 2) : 0 / 100)) * $currency['exchange_rate'], 2);
            $add['arrival_prices'] = round($info['w_price'] - ($wit_num ? round($info['w_price'] * ($fee / 100), 2) : 0 / 100), 2);
            $add['current_currency'] = $currency['current_currency'];
            $add['arrival_currency'] = $currency['arrival_currency'];
            $add['exchange_rate'] = $currency['exchange_rate'];
            $add['w_service'] = $wit_num ? round($info['w_price'] * ($fee / 100), 2) : 0;
            $add['lebal'] = $user['lebal'];
            $id = UserWithdrawalModel::insertGetId($add);
            $upd['id'] = $add['id'] = $id;
            $upd['order_sn'] = date("YmdHi") . $id;


            //双钱包开启提现扣除彩金流水时扣除彩金及彩金流水
            $walletSystemData = WalletService::getWalletData();
            if (
                isset($walletSystemData['wallet_type']) &&
                $walletSystemData['wallet_type'] == 1 &&
                isset($walletSystemData['withdrawal_is_clear_cjbalance']) &&
                $walletSystemData['withdrawal_is_clear_cjbalance'] == 1
            ) {
                UserModel::cz_cjbalance($this->uid, $user['cjbalance'], '-', '余额提现', 1, $id, 101, $user['cjsavings']);
                //记录到提现表中，提现失败后处理退回
                $upd['dec_cjbalance'] = $user['cjbalance'];
                $upd['dec_cjsavings'] = $user['cjsavings'];
            }

            UserWithdrawalModel::update($upd);
            UserModel::cz_balance($this->uid, $add['w_price'], "-", true, "余额提现");
            \app\common\model\UserAdminLogModel::add_log($this->uid, '会员提现|IP:' . Util::get_ip() . "|订单号：" . $upd['order_sn'] . "|提现金额：" . $add['w_price'] . "|收款地址：" . $bk['bank_number'], "会员提现");

            //自动审核
            UserWithdrawalService::autoCheck($this->uid, $add);

            (new BotFactory())->AutonMessage(3, $id);  //发送机器人消息
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        if ($rsps['code'] == 1) {
//            MessageService::sendAdminGroupMsg('有新的提现申请,请及时处理');
            MessageService::sendAdminGroupMsg('有新的提现申请,请及时处理', 'toast', '0', '通知', 0, 3);
        }
        return $rsps;
    }

    //有效交易量
    public function limit_run()
    {
        $info = $this->param;

        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();

        $user = UserModel::uinfo($this->uid, ",a.savings,a.water_balance,a.withdrawal_psd,b.min_amount,b.max_amount,b.day_limit");//查看当前用户流水和最低提现金额
        $date = [];
        $date['start_time'] = strtotime(date("Y-m-d") . " 00:00:00");
        $date['end_time'] = strtotime(date("Y-m-d") . " 23:59:59");
        $now_w = UserWithdrawalModel::where("uid", $this->uid)
            ->where("add_time>=" . $date['start_time'] . " and add_time<=" . $date['end_time'])
            ->count();//查看今日提现次数

        $data['now_water_balance'] = $user['water_balance'];
        $data['old_min_amount'] = $user['min_amount'];
        $data['old_max_amount'] = $user['max_amount'];
        $data['min_amount'] = Util::i18n_number_format($user['min_amount']);
        $data['max_amount'] = Util::i18n_number_format($user['max_amount']);
        $data['day_limit'] = $user['day_limit'] - $now_w;
        $is_usdt_fee = env('is_usdt_fee', 0);
        if ($is_usdt_fee == 1) {
            $data['usdt_charge'] = $sys['usdt_charge'];
        } else {
            $data['usdt_charge'] = $sys['service_charge'];
        }
        $data['service_charge'] = $sys['service_charge'];

        //判断是否验证首次提现 0不需要验证 1需要验证
        $data['ver_withdrawal_first'] = 0;
        $withdrawal_count = UserWithdrawalModel::where('uid', $this->uid)->count();
        //判断是否验证首次提现
        if ($sys['withdrawal_status'] == 1) {
            if ($withdrawal_count == 0) {
                //一次提现记录都没有 需要验证手机号码
                $data['ver_withdrawal_first'] = 1;
            }
        }
        //支付密码
        $withdrawal_psd = $user['withdrawal_psd'];
        unset($user['withdrawal_psd']);
        //是否设置了支付密码 0未设置 1已设置
        $data['is_withdrawal_psd'] = empty($withdrawal_psd) ? 0 : 1;;
        //是否需要验证支付密码
        if ($sys['pay_passwd_verify'] == 1) {
            $data['pay_passwd_verify'] = 1;
        } else {
            $data['pay_passwd_verify'] = 0;
        }
        //获取提现规则和可提现余额（根据提现那边修改）
        $data['withdrawal_rules'] = $sys['withdrawal_rules'];
        // 提现是否必须要求有充值 0 为必须，1为不必须
        $UserOffline = env('WITHDRAW_NOT_REQ_RECHARGE', 0);
        if ($UserOffline == 0) {
            $UserOffline = UserOfflineModel::where([['uid', "=", $this->uid], ['status', "in", "3,4"]])->value("id") ? 1 : 0;
        }
        $userWithdrawalAmount = $UserOffline > 0 ? $user['balance'] : 0;
        if ($user['savings'] > 0 && $UserOffline > 0 && $data['withdrawal_rules'] == 1) {
            $userWithdrawalAmount = $user['balance'] - $user['savings'];
            //判断提现0流水限制
            if (isset($sys['saving_zero_withdrawal']) && $sys['saving_zero_withdrawal'] == 1) {
                $userWithdrawalAmount = 0;
            }
        }

        $data['user_withdrawal_amount'] = max([$userWithdrawalAmount, 0]); // 小于0时显示0
        $data['user_withdrawal_amount_l'] = [$userWithdrawalAmount, $user['savings'], $user['balance'], $UserOffline];

        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //提现记录
    public function log_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $start_time = $this->param['start_time'] ?? '';
        $end_time = $this->param['end_time'] ?? '';
        $where = [];
        $where[] = ["a.uid", '=', $this->uid];
        if (!empty($start_time)) {
            $where[] = ['a.add_time', ">=", strtotime($start_time . " 00:00:00")];
        }

        if (!empty($end_time)) {
            $where[] = ['a.add_time', "<=", strtotime($end_time . " 23:59:59")];
        }

        $data = UserWithdrawalModel::alias("a")
            ->join("dq_user_bank b", "a.bank_id=b.id")
            ->where($where)
            ->limit((($pagenow - 1) * $limit), $limit)
            ->field("a.id,a.method_id,a.channel,a.arrival_price,a.arrival_prices,a.add_time,a.order_sn,a.update_time,a.w_price,a.w_service,a.w_remarks,a.user_remarks,a.status,b.bank_number,b.bank_name")
            ->order("a.id desc")
            ->select();

        foreach ($data as $key => $value) {
            $data[$key]['add_time'] = Util::i18n_datetime2str($value['add_time']);
            $data[$key]['update_time'] = $value['update_time'] == 0 ? '' : Util::i18n_datetime2str($value['update_time']);
            $data[$key]['bankcrad'] = Util::bank_number_sub($value['bank_number']);
            $data[$key]['w_price'] = Util::i18n_currency($value['w_price']);
            $data[$key]['w_remarks'] = $data[$key]['remarks'] = $value['user_remarks'] ?? '';
        }

        $rsps['data'] = $data;
        $rsps['code'] = 1;
        $rsps['total'] = UserWithdrawalModel::alias("a")
            ->join("dq_user_bank b", "a.bank_id=b.id")
            ->where($where)->count();
        return $rsps;
    }
}