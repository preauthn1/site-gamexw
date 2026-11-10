<?php

namespace app\api\factory_v2;

use app\common\exception\MessageException;
use app\common\model\AgentRakeBackModel;
use app\common\model\BankModel;
use app\common\model\PaymentTypeModel;
use app\common\model\UserAwardCfgsModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\UserBankModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserGoogleModel;
use app\common\model\ActionApplyModel;
use app\common\lib\Util;
use app\api\redis\ApiRedis;
use app\common\redis\CommomRedis;
use app\common\service\BankService;
use app\common\service\BetService;
use app\common\service\MemberService;
use app\common\service\MessageService;
use think\facade\Config;
use think\facade\Log;
use think\facade\Request;
use think\facade\Db;

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

    //添加银行卡
    public function add_bank()
    {
        //bank_type 0 银行卡 1 USDT地址 2电子钱包 3 PIX钱包
        //bank_name 银行名称/usdt链接名/钱包名称/PIX类型
        //bank_number 银行卡号 / usdt收款地址/收款地址/PIX账号
        //bank_hm 开户名/usdt链接名/钱包名称/姓名
        //financial 金融编码(默认语言是印度和巴西需要传递)/税号
        $param = $this->param;
        try {
            validate(\app\common\validate\UserBankValidate::class)->check($param);
        } catch (\think\exception\ValidateException $e) {
            // 验证失败 输出错误信息
            ajax_return($e->getError(), 0);
        }

        $BankModel = new BankModel();
        $UserBankModel = new UserBankModel();
        if (!preg_match("/^([a-zA-Z0-9_-])+@([a-zA-Z0-9_-])+(.[a-zA-Z0-9_-])+/i", $param['bank_number'])) {
            if (!preg_match("/^[0-9A-Za-z]+$/", $param['bank_number'])) { // 限制银行卡卡号格式
                ajax_return("收款账号只能是字母或数字组合", 0);
            }
        }
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();

        if (isset($sys['bind_card_status']) && $sys['bind_card_status'] == 1) {
            $user = UserModel::uinfo($this->uid, ",a.phone,a.mail");
            $verify_code = $param['verify_code'] ?? null;
            MemberService::checkCode($user, $verify_code, 'bankcard_check');
        }
        $currency_id = isset($param['currency_id']) ? $param['currency_id'] : 0;
        $card_number = (isset($sys['card_number']) && $sys['card_number'] > 0) ? $sys['card_number'] : 5;
        if ($param['bank_type'] == 0) { // 银行卡
            $count = $UserBankModel->where(["uid" => $this->uid, 'bank_type' => 0, "currency_id" => $currency_id])->count();
            if ($count >= $card_number) {
                ajax_return("收款账号绑定已超上限", 0);
            }
//            $count = $UserBankModel->where(["uid"=>$this->uid, 'bank_type' => 0])->count();
//            if ($count >= 1) { // 判断是否已绑卡，没绑不限制
//                $bankC = $UserBankModel->where(["uid"=>$this->uid, 'bank_type' => 0, 'bank_hm' => $param['bank_hm']])->count();
//                if($bankC == 0){
//                    ajax_return("开户名与之前绑卡开户名要一致", 0);
//                }
//
//                $bankNC = $UserBankModel->where(["uid"=>$this->uid, 'bank_type' => 0, 'bank_number' => $param['bank_number']])->count();
//                if($bankNC > 0){
//                    ajax_return("该卡已绑定，请勿重复绑定", 0);
//                }
//            }
            $count = $UserBankModel->where(["uid" => $this->uid, 'bank_type' => 0, 'bank_number' => $param['bank_number']])->count();
            if ($count > $sys['card_number']) {
                ajax_return("收款账号绑定已超上限", 0);
            }
            if ($count >= 1) { // 判断是否已绑卡，没绑不限制
                $bankNC = $UserBankModel->where(["uid" => $this->uid, 'bank_type' => 0, 'bank_number' => $param['bank_number']])->order('id DESC')->find();
                if (!empty($bankNC)) {
                    return $bankNC['id'];
                }
            }
            $count = $UserBankModel->where(['bank_type' => 2, 'bank_number' => $param['bank_number']])->count();
            if ($count > 0) {
                ajax_return("该账号已被其他人绑定，请勿重复绑定", 0);
            }
        }

        if ($param['bank_type'] == 1) { // USDT
            $count = $UserBankModel->where(["uid" => $this->uid, 'bank_type' => 1])->count();
            if ($count >= $card_number) {
                ajax_return("收款账号绑定已超上限", 0);
            }
            if ($count >= 1) { // 判断是否已绑卡，没绑不限制
                $bankNC = $UserBankModel->where(["uid" => $this->uid, 'bank_type' => 1, 'bank_number' => $param['bank_number']])->order('id DESC')->find();
                if (!empty($bankNC)) {
                    return $bankNC['id'];
                }
            }
            $bankNC = $UserBankModel->where(['bank_type' => 1, 'bank_number' => $param['bank_number']])->count();
            if ($bankNC > 0) {
                ajax_return("该账号已被其他人绑定，请勿重复绑定", 0);
            }
        }

        if ($param['bank_type'] == 2) { // 电子钱包
            $count = $UserBankModel->where(["uid" => $this->uid, 'bank_type' => 2])->count();
            if ($count >= $card_number) {
                ajax_return("收款账号绑定已超上限", 0);
            }
            if ($count >= 1) { // 判断是否已绑卡，没绑不限制
                $bankNC = $UserBankModel->where(["uid" => $this->uid, 'bank_type' => 2, 'bank_number' => $param['bank_number']])->order('id DESC')->find();
                if (!empty($bankNC)) {
                    return $bankNC['id'];
                }
            }
            $bankNC = $UserBankModel->where(['bank_type' => 2, 'bank_number' => $param['bank_number']])->count();
            if ($bankNC > 0) {
                ajax_return("该账号已被其他人绑定，请勿重复绑定", 0);
            }
        }

        if ($param['bank_type'] == 3) { // PIX钱包
            $count = $UserBankModel->where(["uid" => $this->uid, 'bank_type' => 3])->count();
            if ($count >= 1) { // 判断是否已绑卡，没绑不限制
                $bankNC = $UserBankModel->where(["uid" => $this->uid, 'bank_type' => 3, 'bank_number' => $param['bank_number']])->order('id DESC')->find();
                if (!empty($bankNC)) {
                    return $bankNC['id'];
                }
            }
            $bankNC = $UserBankModel->where(['bank_type' => 3, 'bank_number' => $param['bank_number']])->count();
            if ($bankNC > 0) {
                ajax_return("该账号已被其他人绑定，请勿重复绑定", 0);
            }
        }

        $add['add_time'] = time();
        $add['uid'] = $this->uid;
        $add['bank_type'] = isset($param['bank_type']) ? $param['bank_type'] : 0;
        $add['bank_name'] = isset($param['bank_name']) ? $param['bank_name'] : "";
        $add['bank_number'] = isset($param['bank_number']) ? $param['bank_number'] : "";
        $add['bank_open'] = isset($param['bank_open']) ? $param['bank_open'] : "";
        $add['bank_hm'] = isset($param['bank_hm']) ? $param['bank_hm'] : "";
        $add['province'] = isset($param['province']) ? $param['province'] : '';
        $add['city'] = isset($param['city']) ? $param['city'] : '';
        $add['remarks'] = isset($param['remarks']) ? $param['remarks'] : '';
        $add['financial'] = isset($param['financial']) ? $param['financial'] : '';
        $add['currency_id'] = $param['currency_id'] ?? '';
        $bank_id = $UserBankModel->insertGetId($add);
        \app\common\model\UserAdminLogModel::add_log($this->uid, '会员绑卡|IP:' . Util::get_ip() . "|卡号：" . $add['bank_number'], "会员绑卡");
        return $bank_id;
    }

    //提现
    public function apply_run()
    {
        $info = $this->param;
        $ispass = 1;//isset($info['ispass'])?$info['ispass']:0;
        $phone = isset($info['phone']) ? $info['phone'] : '';
        $wpass = isset($info['wpass']) ? $info['wpass'] : '';
        //添加提款账号 （如果账号存在直接返还账号id）
        $bank_id = $this->add_bank();
        if (empty($bank_id)) {
            ajax_return("提款账号有误", 0);
        }
        //提款账号id
        $info['bank_id'] = $bank_id;
        $ApiRedis = new ApiRedis();
        $CommomRedis = new CommomRedis();
        $sys = $ApiRedis->systemInfo();
        if ($sys['is_withdraw'] == 0) {
            ajax_return("提现系统维护中", 0);
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
        $banks = $CommomRedis->getBankNames();
//        $isExist = false;
//        foreach ($banks as $v) {
//            if (strtoupper($bk['bank_name']) == strtoupper($v)) {
//                $isExist = true;
//            }
//        }
//        if (!$isExist) {
//            ajax_return("该银行暂不支持提现", 0);
//        }
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

        $user = UserModel::uinfo($this->uid, ",a.water_balance,b.min_amount,b.max_amount,b.day_limit,a.savings,a.mail,a.phone,a.is_check_phone,a.is_check_phone");//查看当前用户流水和最低提现金额

        //判断是否验证首次提现 0不需要验证 1需要验证
        $data['ver_withdrawal_first'] = 0;
        $withdrawal_count = UserWithdrawalModel::where('uid', $this->uid)->count();
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
            //判断是否流水为零才能提现
            if (isset($sys['saving_zero_withdrawal']) && $sys['saving_zero_withdrawal'] == 1 && $totalSavings > 0) {
                ajax_return(sprintf(lang('您还需：%s流水才能提现'), $totalSavings));
            }
            if ($UserOffline == 0 || ($totalSavings > 0 && ($user['balance'] - $info['w_price'] < $totalSavings))) {
                ajax_return("提现流水倍数不满足", 0);
            }
        } else if ($sys['withdrawal_rules'] == 2) {
            $rechargeM = UserOfflineModel::where([['uid', "=", $this->uid], ['status', "in", "3,4"]])->sum('arrival_price');  //充值金额
            $ArtificialM = ArtificialBalanceModel::alias("a")->join("dq_user_balance_mx b", "a.mx_id=b.id")->where([["a.a_type", 'in', '1,3']])->where(['b.uid' => $this->uid])->sum("b.price*water");//赠送彩金
            $action_apply_price = ActionApplyModel::where('ps_status', '=', '1')->where('uid', '=', $this->uid)->sum('water');
            $tables = BetService::getThirdStatsTable('');
            $field = "SUM(gb_validwater) as gb_validwater_sum";
            $temp = Db::table($tables['stats_global'])->field($field)->where("gb_winlose <> 0 and gb_uid=" . $this->uid)->find();
            if(!empty($temp)){
                $game_zhudan = $temp['gb_validwater_sum'];
            }else{
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
        $now_w = UserWithdrawalModel::where("uid", $this->uid)->where('status', '<>', 2)
            ->where("add_time>=" . $date['start_time'] . " and add_time<=" . $date['end_time'])
            ->count();//查看今日提现次数

        if ($now_w >= $user['day_limit']) {
            ajax_return("当天提现次数已达上限", 0);
        }
        if (isset($sys['bind_card_status']) && $sys['bind_card_status'] == 1) {
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
            $withdrawal_num = UserWithdrawalModel::where(['uid' => $this->uid, 'channel' => 1])->count();//查看今日提现次数
            if ($withdrawal_num < $sys['free_poundage']) { //当提现次数 小于后台设置的 提现免手续次数时 手续费设置为0
                $wit_num = 0;
            }
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
            $upd['id'] = $id;
            $upd['order_sn'] = date("YmdHi") . $id;
            UserWithdrawalModel::update($upd);
            UserModel::cz_balance($this->uid, $add['w_price'], "-", true, "余额提现");
            \app\common\model\UserAdminLogModel::add_log($this->uid, '会员提现|IP:' . Util::get_ip() . "|订单号：" . $upd['order_sn'] . "|提现金额：" . $add['w_price'] . "|收款地址：" . $bk['bank_number'], "会员提现");
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        if ($rsps['code'] == 1) {
            MessageService::sendAdminGroupMsg('有新的充值申请,请及时处理', 'toast', '0', '通知', 0, 3);
//            MessageService::sendAdminGroupMsg('有新的提现申请,请及时处理');
        }
        return $rsps;
    }

    //提现需要的参数
    public function withdrawal_config_run()
    {
        $info = $this->param;
        $bank_type = $info['bank_type'];//提现渠道类型 0 银行卡 1 USDT地址 2电子钱包 3 PIX钱包
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $user = UserModel::uinfo($this->uid, ",a.savings,a.water_balance,b.min_amount,b.max_amount,b.day_limit");//查看当前用户流水和最低提现金额
        $date = [];
        $date['start_time'] = strtotime(date("Y-m-d") . " 00:00:00");
        $date['end_time'] = strtotime(date("Y-m-d") . " 23:59:59");
        $now_w = UserWithdrawalModel::where("uid", $this->uid)
            ->where("add_time>=" . $date['start_time'] . " and add_time<=" . $date['end_time'])
            ->count();//查看今日提现次数

        $data['now_water_balance'] = round(floatval($user['water_balance']), 2);
        $data['min_amount'] = round(floatval($user['min_amount']), 2);
        $data['max_amount'] = round(floatval($user['max_amount']), 2);
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
        //获取指定游戏未完成流水
        //需要完成的流水
        $water = UserAwardCfgsModel::where('uac_uid', '=', $this->uid)->sum('uac_water');
        $water = $water + $user['savings'];
        $data['water'] = $water;

        //已完成的流水
        $waterdone = UserAwardCfgsModel::where('uac_uid', '=', $this->uid)->sum('uac_waterdone');
        $data['waterdone'] = $waterdone;
        $data['water_progress'] = 0;
        if (!empty($water)) {
            //流水完成进度百分百
            $data['water_progress'] = round(($waterdone / $water) * 100, 2);
        }

        //允许申请提现最大金额
        $data['user_withdrawal_amount'] = round(max([$userWithdrawalAmount, 0]), 2); // 小于0时显示0
        $UserBankModel = new UserBankModel();
        $bank = $UserBankModel->alias("a")
            ->where("a.uid", $this->uid)
            ->where("a.bank_type", "=", $bank_type)
            ->field('a.id,a.bank_type,a.bank_id,a.bank_number,a.bank_open,a.bank_hm,a.province,a.city,a.remarks,a.bank_name,a.currency_id')
            ->order('a.id DESC')
            ->find();
        if (empty($bank)) {
            $bank = [];
        }
        $usdt_type = Util::getUsdtType();

        $pix_type = Config::get('custom.pix_type');
        //去除pix类型
        $unsetPixType = env('UNSET_PIX_TYPE', '');
        if ($unsetPixType) {
            $unsetPixTypeArr = explode(',', $unsetPixType);
            foreach ($unsetPixTypeArr as $v) {
                if (isset($pix_type[$v])) {
                    unset($pix_type[$v]);
                }
            }
        }
        $usdt_type_arr = [];
        $orderby = 0;
        foreach ($usdt_type as $key => $value) {
            $usdt_type_arr[] = [
                'name' => $value,
                'code' => $key,
                'orderby' => $orderby,
                'field' => ['bank_type', 'bank_name', 'bank_number', 'bank_hm']
            ];
        }
        $data['usdt_type'] = $usdt_type_arr;
        $pix_type_arr = [];
        foreach ($pix_type as $key => $value) {
            $pix_type_arr[] = [
                'name' => $value,
                'code' => $key,
                'orderby' => $orderby,
                'field' => ['bank_type', 'bank_name', 'bank_number', 'bank_hm']
            ];
        }
        $data['pix_type'] = $pix_type_arr;
        $CommomRedis = new CommomRedis();
        $bank_type = $CommomRedis->getBankList();
        $bank_type_arr = [];
        foreach ($bank_type as $value) {
            foreach ($value as $val) {
                $bank_type_arr[] = [
                    'name' => $val['bank_name'],
                    'code' => $val['bank_code'],
                    'orderby' => $val['orderby'],
                    'field' => $val['bank_field'],
                ];
            }
        }
        $data['bank_type'] = $bank_type_arr;
        $data['wallet_type'] = [];
        $data['last_card'] = $bank;//最后一张银行卡信息

        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();
        $defa_currency = $currency['defa_data'];
        //改为获取货币管理中的默认货币
        $data['currency_defa'] = $defa_currency['type']['val'] ?? 2; //默认货币 默认为法币
        $data['currency_code'] = $defa_currency['currency_code'];
        $data['currency_symbol'] = Util::getCurrencyDefaultSymbol('symbol');

        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //提现渠道
    public function withdrawal_channel_run()
    {
        $payment_method_type = Config::get('custom.payment_method_type');
        $is_withdrawal_bank = env('is_withdrawal_bank', 0); //银行渠道
        $is_withdrawal_usdt = env('is_withdrawal_usdt', 0); // usdt渠道
        $is_withdrawal_wallet = env('is_withdrawal_wallet', 0); // 电子钱包
        $is_withdrawal_pix = env('is_withdrawal_pix', 0); // PIX
        if ($is_withdrawal_bank == 0) {
            unset($payment_method_type[0]);
        }
        if ($is_withdrawal_usdt == 0) {
            unset($payment_method_type[1]);
        }
        if ($is_withdrawal_wallet == 0) {
            unset($payment_method_type[2]);
        }
        if ($is_withdrawal_pix == 0) {
            unset($payment_method_type[3]);
        }
        //排序并翻译
        $pay_type = BankService::payMethodTypeSortArr($payment_method_type);
        $pay_type_list = PaymentTypeModel::where(['status' => 1])->select();
        $pay_type_arr = [];
        foreach ($pay_type_list as $value) {
            $pay_type_arr[$value['pay_type']] = $value['icon'];
        }
        $pay_type_data = [];
        foreach ($pay_type as &$value) {
            if(isset($pay_type_arr[intval($value['type'])])){
                $value['icon'] = $pay_type_arr[intval($value['type'])];
                $pay_type_data[] = $value;
            }

        }
        $data['pay_type'] = $pay_type_data;
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }
}