<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\model\ActionApplyModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\BankSkModel;
use app\common\model\BankSkSetModel;
use app\common\model\LangModel;
use app\common\model\PayAgentModel;
use app\common\model\PaySetModel;
use app\common\model\PayTypeModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWaterModel;
use app\common\model\UserWithdrawalModel;
use app\common\payment\Entry;
use app\common\redis\CommomRedis;
use app\common\service\MemberService;
use app\common\service\MessageService;
use think\facade\Config;
use think\facade\Db;
use think\facade\Request;


class UserOfflineFactory
{
    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->uid = $uid;
    }

    //充值申请
    public function apply_run()
    {
        $info = $this->param;
        $type = $info['type'] ?? 0;

        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        if ($sys['is_recharge'] == 0) {
            ajax_return("充值系统维护中", 0);
        }
        //获取会员数据
        $user = UserModel::uinfo($this->uid, ',a.phone,a.mail,a.rec_code,a.lebal,a.id as user_id')->toArray();
        if ($sys['test_withdraw'] == 0) {
            if ($user['lebal'] == 2) {
                ajax_return("测试用户不能充值", 0);
            }
        }
        $tag_recharge = MemberService::tagBanRecharge($this->uid);
        if ($tag_recharge < 0) {
            ajax_return("您无法申请充值,请联系客服", 0);
        }

        if (strlen($info['apply_price']) > 18) {
            ajax_return("金额位数最多为18位", 0);
        }
        if (!is_numeric($info['apply_price'])) {
            ajax_return("充值金额只能为数字", 0);
        }
        //判断充值数量
        if (isset($sys['allow_not_recharge']) && $sys['allow_not_recharge'] > 0) {
            //获取未审核充值订单数量
            $condition = [];
            $condition[] = ['uid', '=', $this->uid];
            $condition[] = ['status', 'in', [-1, 1]];
            $noCheckNum = UserOfflineModel::where($condition)->count();
            if ($noCheckNum >= $sys['allow_not_recharge']) {
                ajax_return('你当前有未审核的订单，请耐心等待审核');
            }
        }
        if (env('digital_currency_open', 0) == 1 && $type == 2) {
            //开启数字货币的时候 并且 选择usdt充值  就是数字货币钱包  保留6位小数 进行充值
            $info['apply_price'] = $info['apply_price'];
        } else {
            $info['apply_price'] = round($info['apply_price'], 2);

        }
//        if($type != 2 && env('digital_currency_open', 0)){
//            $info['apply_price'] = round($info['apply_price'],2);
//        }
        if ($type == 0 || $type == 3) { // 线下充值
            $res = $this->hang_card_pay($info, $user);
        } elseif ($type == 1 || $type == 5 || $type == 7) { // 银行卡渠道充值
            $res = $this->online_pay($info, $user);
        } elseif ($type == 2) { // usdt 渠道充值
            $res = $this->usdt_pay($info, $user);
        } else if ($type == 4 || $type == 6) { // 电子钱包 PIX
            $res = $this->hang_card_pay($info, $user);
        }
        $isOnSystemMessage = env('IS_ON_SYSTEM_MESSAGE', 0);
        if (isset($res['code']) && $res['code'] == 1 && $isOnSystemMessage == 1) {
            //
            $t = ($type == 0 || $type == 3) ? 1 : 2;
            MessageService::sendAdminGroupMsg('有新的充值申请,请及时处理', 'toast', '0', '通知', 0, $t);
        }
        return $res;
    }

    public function usdt_pay($info, $user)
    {
//        $usdt_type = Config::get('custom.usdt_type');
        $usdt_type = Util::getUsdtType();
        $payment_method_type = Config::get('custom.payment_method_type');
        if (!isset($info['usdt_type']) || !in_array($info['usdt_type'], $usdt_type)) {
            ajax_return("usdt链接名称不正确", 0);
        }
        $ApiRedis = new ApiRedis();
        if (!$ApiRedis->lock("applyRecharge" . $this->uid, 5)) {
            ajax_return("请勿频繁访问", 0);
        }
        $sys = $ApiRedis->systemInfo();
        Db::startTrans();
        try {
            $map['pay_type'] = 1;
            $map['usdt_type'] = $info['usdt_type'];
            if (isset($info['pay_set_id'])) {
                $map['id'] = $info['pay_set_id'];
                $pay_type = PaySetModel::get_pay($map); //指定渠道
            } else {
                $pay_type = PaySetModel::get_rand_pay($info['apply_price'], $map);//随机渠道
            }
            if (
                ($pay_type['start_level_id'] > 0 && $pay_type['start_level_id'] > $user['level_id']) ||
                ($pay_type['end_level_id'] > 0 && $pay_type['end_level_id'] < $user['level_id'])
            ) {
                ajax_return("当前等级不满足该收款账户,请联系客服！");
            }
            //判断是否已经提现过
            $withdrawal_count = UserWithdrawalModel::where('uid', $this->uid)->where('status', 3)->count('id');
            //判断系统配置了最小充值限制
            if ((!empty($sys['recharge_limit'])) && $withdrawal_count >= 1 && env('digital_currency_open', 0) == 0) {
                if ($sys['recharge_limit'] > $info['apply_price']) {
                    $msg = lang("单笔最低充值%s");
                    ajax_return(sprintf($msg, $sys['recharge_limit']), 0);
                }
            }
            if ($pay_type['pay_min_amout'] > $info['apply_price']) {
                $msg = lang("单笔最低充值%s");
                ajax_return(sprintf($msg, $pay_type['pay_min_amout']), 0);
            }
            if ($pay_type['pay_max_amout'] < $info['apply_price']) {
                $msg = lang("单笔最高充值%s");
                ajax_return(sprintf($msg, $pay_type['pay_max_amout']), 0);
            }
            $currency = Util::getPayCurrency($pay_type);
            $add['add_time'] = time();
            $add['type'] = 1;
            $add['channel'] = 2;
            $add['uid'] = $this->uid;
            $add['apply_price'] = $info['apply_price'];
            $add['pay_type'] = $pay_type['pay_sys_sign'];
            $add['pay_id'] = $pay_type['id'];
            $add['pay_currency'] = $currency['pay_currency'];
            $add['arrival_currency'] = $currency['arrival_currency'];
            $add['exchange_rate'] = $currency['exchange_rate'];
            $add['pay_ip'] = Util::get_ip();
            $add['set_content'] = isset($info['set_content']) ? $info['set_content'] : '';
            $add['not_auto_action'] = $info['not_auto_action'] ?? 0; // 是否自动参与活动 0参与 1不参与 其他指定活动参与
            $add['lebal'] = $user['lebal'];

            $pay_id = true;
            if (!in_array($pay_type['pay_sys_sign'], ['UdunPay', 'AlphapoPay'])) {
                $pay_id = UserOfflineModel::insertGetId($add);
            }
            if ($pay_id) {
                $upd = [];
                $upd['order_sn'] = date("YmdHi") . $pay_id;//支付订单号
                if (!in_array($pay_type['pay_sys_sign'], ['UdunPay', 'AlphapoPay'])) {
                    $pay_res = UserOfflineModel::where("id", $pay_id)->update($upd);
                }
                $other = [];
                foreach ($user as $key => $value) {
                    $other[$key] = $value;
                }
                $other['order_sn'] = $upd['order_sn'];
                $other['apply_price'] = sprintf('%.2f', $add['apply_price']);#入口改为两位小数，统一改所有渠道
                $other['phone'] = $user['phone'];
                $other['pay_ip'] = $add['pay_ip'];
                $other['add_time'] = date("Y-m-d H:i:s", $add['add_time']);
                $other['call_domain'] = getAppUrl();
                $other['notify_url'] = $sys['domain'] . '/notify/' . $pay_type['id']; // 异步通知地址
                $other['pay_id'] = $pay_type['id'];

                $Entry = new Entry($pay_type['config'], $sys['domain']);//支付包
                $pay_info = $Entry->pay($pay_type['pay_sys_sign'], $other);

                if (isset($pay_info['pay_url'])) {
                    $pay_info['handle_type'] = "navto";
                } else {
                    $pay_info['handle_type'] = 'form';
                }
                $pay_info['is_qrcode'] = isset($pay_info['is_qrcode']) ? 1 : 0;
                if ($pay_id > 0) {
                    (new BotFactory())->AutonMessage(2, $pay_id);  //发送机器人消息
                }
                // 提交事务
                Db::commit();
                $rsps['code'] = 1;
                $rsps['data'] = $pay_info;
            } else {
                ajax_return("充值失败，请重试", 0);
            }
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        return $rsps;
    }

    public function online_pay($info, $user)
    {
        $ApiRedis = new ApiRedis();
        if (!$ApiRedis->lock("applyRecharge" . $this->uid, 5)) {
            ajax_return("请勿频繁访问", 0);
        }
        $map = [];
        if (!isset($info['pay_set_id']) && isset($info['bank_sk_id'])) {
            $info['pay_set_id'] = $info['bank_sk_id'];
        }
        //$map['pay_type'] = 0;
        if (isset($info['pay_set_id'])) {
            $map['id'] = $info['pay_set_id'];
            $pay_type = PaySetModel::get_pay($map);
        } else {
            $pay_type = PaySetModel::get_rand_pay($info['apply_price'], $map);//随机渠道或者指定渠道
        }
        if (
            ($pay_type['start_level_id'] > 0 && $pay_type['start_level_id'] > $user['level_id']) ||
            ($pay_type['end_level_id'] > 0 && $pay_type['end_level_id'] < $user['level_id'])
        ) {
            ajax_return("当前等级不满足该收款账户,请联系客服！");
        }
        //获取系统配置数据
        $sys = $ApiRedis->systemInfo();
        //判断是否已经提现过
        $withdrawal_count = UserWithdrawalModel::where('uid', $this->uid)->where('status', 3)->count('id');
        //判断系统配置了最小充值限制
        if ((!empty($sys['recharge_limit'])) && $withdrawal_count >= 1) {
            if ($sys['recharge_limit'] > $info['apply_price']) {
                $msg = lang("单笔最低充值%s");
                ajax_return(sprintf($msg, $sys['recharge_limit']), 0);
            }
        }
        if ($pay_type['pay_min_amout'] > $info['apply_price']) {
            $msg = lang("单笔最低充值%s");
            ajax_return(sprintf($msg, $pay_type['pay_min_amout']), 0);
        }
        if ($pay_type['pay_max_amout'] < $info['apply_price']) {
            $msg = lang("单笔最高充值%s");
            ajax_return(sprintf($msg, $pay_type['pay_max_amout']), 0);
        }

        $rsps = [];

        Db::startTrans();
        try {
            $currency = Util::getPayCurrency($pay_type);

//            //Alphapo 数字货币钱包 digital_currency_open
//            if(env('digital_currency_open', 0)){
//                $sys = (new ApiRedis())->systemInfo();
//                $p_config = [];
//                $Entry = new Entry($pay_type['config'], $sys['domain']);
//                $result = $Entry->get_rate($pay_type['pay_sys_sign']);//回调结果
//                if($result['code'] == 1){
//                    $rate = $result['data'];
//                }
//
//                $currency['pay_currency'] = $pay_type['config']['currency'] ?? ''; //支付币种
////                $currency['arrival_currency'] = ; //到账币种
//                $currency['exchange_rate'] = $rate[$pay_type['config']['currency']] ?? 1;; //汇率
//            }

            $add['add_time'] = time();
            $add['type'] = 1;
            $add['channel'] = $info['type'];
            $add['uid'] = $this->uid;
            $add['apply_price'] = $info['apply_price'];
            $add['pay_id'] = $pay_type['id'];
            $add['pay_currency'] = $currency['pay_currency'];
            $add['arrival_currency'] = $currency['arrival_currency'];
            $add['exchange_rate'] = $currency['exchange_rate'];
            $add['pay_type'] = $pay_type['pay_sys_sign'];
            $add['status'] = -1;
            $add['pay_ip'] = Util::get_ip();
            $add['set_content'] = isset($info['set_content']) ? $info['set_content'] : '';
            $add['not_auto_action'] = $info['not_auto_action'] ?? 0; // 是否自动参与活动 0参与 1不参与 其他指定活动参与
            $add['lebal'] = $user['lebal'];

            //数字钱包货币的时候  先不写入订单
            $pay_id = true;
            if (!in_array($pay_type['pay_sys_sign'], ['UdunPay', 'AlphapoPay'])) {
                $pay_id = UserOfflineModel::insertGetId($add);
            }

            if ($pay_id) {
                $rec_code = UserModel::where(['id' => $this->uid])->field('rec_code')->find();
//                $pay_id = UserOfflineModel::insertGetId($add);
                $upd['order_sn'] = date("YmdHi") . $pay_id;//支付订单号
                if (!in_array($pay_type['pay_sys_sign'], ['UdunPay', 'AlphapoPay'])) {
                    UserOfflineModel::where("id", $pay_id)->update($upd);
                }
                $pay = UserOfflineModel::where("id", $pay_id)->find();
                $other = [];
                foreach ($user as $key => $value) {
                    $other[$key] = $value;
                }
                $other['order_sn'] = $upd['order_sn'];
                $other['apply_price'] = sprintf('%.2f', $add['apply_price']);#入口改为两位小数，统一改所有渠道
                $other['phone'] = $user['phone'];
                $other['pay_ip'] = $add['pay_ip'];
                $other['add_time'] = date("Y-m-d H:i:s", $add['add_time']);
                $domain = env('CHARGE_WITHDRAWAL_DOMAIN', '');
                if (!$domain) {
                    $domain = $sys['domain'];
                }
                $other['call_domain'] = getAppUrl() . "/#/paysucc/" . floatval($add['apply_price']) . "/{$currency['arrival_currency']}";
                $other['notify_url'] = $domain . '/notify/' . $pay_type['id']; // 异步通知地址

                $Entry = new Entry($pay_type['config'], $sys['domain']);//支付包
                $other['bank_id'] = $info['bank_id'] ?? 0;
                $other['pay_data'] = $pay_type->toArray();
                $other['pay_id'] = $pay_type['id'];
                $other['uid'] = $this->uid;
                $other['rec_code'] = $rec_code['rec_code'] ?? '';

                $pay_info = $Entry->pay($pay_type['pay_sys_sign'], $other);

                if (isset($pay_info['pay_url'])) {
                    $pay_info['handle_type'] = "navto";
                } else {
                    $pay_info['handle_type'] = 'form';
                }
                if (isset($pay_info['othr_number'])) {
                    UserOfflineModel::where('id', $pay_id)->update(['othr_number' => $pay_info['othr_number']]);
                }
                $pay_info['is_qrcode'] = isset($pay_info['is_qrcode']) ? 1 : 0;
//            (new BotFactory())->AutonMessage(2,$pay_id);  //发送机器人消息
                // 提交事务
                Db::commit();
                $rsps['code'] = 1;
                $rsps['data'] = $pay_info;
            } else {
                ajax_return("充值失败，请重试", 0);
            }

        } catch (\Exception $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
            \think\facade\Log::write($e->getMessage(), 'error');
            \think\facade\Log::write($e->getTraceAsString(), 'error');
//            throw new \Exception($e->getMessage());
        }
        return $rsps;
    }

    public function hang_card_pay($info, $user)
    {

        $sk = BankSkModel::where("bank_status", 1)->with('currencyApi')->where("id", $info['bank_sk_id'])->find();
        if ($info['type'] == 3) {
            if (!isset($sk['id'])) {
                ajax_return("收币地址不存在！", 0);
            }
        } else if ($info['type'] == 4) {
            if (!isset($sk['id'])) {
                ajax_return("钱包地址不存在！", 0);
            }
        } else {
            if (!isset($sk['id'])) {
                ajax_return("收款账户不存在！", 0);
            }
        }
        if (
            ($sk['start_level_id'] > 0 && $sk['start_level_id'] > $user['level_id']) ||
            ($sk['end_level_id'] > 0 && $sk['end_level_id'] < $user['level_id'])
        ) {
            ajax_return("当前等级不满足该收款账户,请联系客服！");
        }
        $time_ = time();
        if ($sk['open_start_time'] > 0 && $sk['open_end_time'] > 0) {
            $ymd = date('Y-m-d');
            $st_time = strtotime(date($ymd . ' H:i:s', $sk['open_start_time']));
            $en_time = strtotime(date($ymd . ' H:i:s', $sk['open_end_time']));
            if (!(($time_ > $st_time) && ($time_ < $en_time))) {
                $msg = lang("当前渠道可用时间为%s-%s");
                ajax_return(sprintf($msg, date('H:i', $sk['open_start_time']), date('H:i', $sk['open_end_time'])), 0);
            }
        }

        //获取系统配置数据
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        //判断是否已经提现过
        $withdrawal_count = UserWithdrawalModel::where('uid', $this->uid)->where('status', 3)->count('id');
        //判断系统配置了最小充值限制
        if ((!empty($sys['recharge_limit'])) && $withdrawal_count >= 1) {
            if ($sys['recharge_limit'] > $info['apply_price']) {
                $msg = lang("单笔最低充值%s");
                ajax_return(sprintf($msg, $sys['recharge_limit']), 0);
            }
        }
        if ($sk['min_amcount'] > $info['apply_price'] && $sk['min_amcount'] > 0) {
            $msg = lang("单笔最低充值%s");
            ajax_return(sprintf($msg, $sk['min_amcount']), 0);
        }
        if ($sk['max_amcount'] < $info['apply_price'] && $sk['max_amcount'] > 0) {
            $msg = lang("单笔最高充值%s");
            ajax_return(sprintf($msg, $sk['max_amcount']), 0);
        }

        $sk['pay_type'] = $sk['type'];
        Db::startTrans();
        try {
            $currency = Util::getPayCurrency($sk);
            $add['add_time'] = time();
            $add['uid'] = $this->uid;
            $add['bank_id'] = 0;
            $add['type'] = 0; //银行卡 电子钱包  PIX钱包 都是0
            $add['channel'] = $info['type'];
            $add['bankname'] = "";
            $add['bankcrad'] = isset($info['bankcrad']) ? $info['bankcrad'] : '';
            $add['realname'] = "";
            $add['bank_sk_id'] = $info['bank_sk_id'];
            $add['credentials'] = isset($info['credentials']) ? $info['credentials'] : '';
            $add['apply_price'] = sprintf('%.2f', $info['apply_price']);#入口改为两位小数，统一改所有渠道
            $add['pay_currency'] = $currency['pay_currency'];
            $add['arrival_currency'] = $currency['arrival_currency'];
            $add['exchange_rate'] = $currency['exchange_rate'];
            $add['remarks'] = $info['remarks'] ?? "";
            $add['set_content'] = isset($info['set_content']) ? $info['set_content'] : '';
            $add['not_auto_action'] = $info['not_auto_action'] ?? 0; // 是否自动参与活动 0参与 1不参与 其他指定活动参与
            $add['pay_ip'] = Util::get_ip();
            $add['lebal'] = $user['lebal'];
            $id = UserOfflineModel::insertGetId($add);
            $upd['id'] = $id;
            $upd['order_sn'] = date("YmdHi") . $id;
            UserOfflineModel::update($upd);
            (new BotFactory())->AutonMessage(2, $id); //发送机器人消息
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        return $rsps;
    }

    public function wallet_pay($info)
    {

    }

    //线下收款账户
    public function sk_bank_run()
    {
        $info = $this->param;
        $type = $info['type'] ?? 0;
        $where = ['type' => $type];

        $tree = UserModel::where("id", $this->uid)->value("tree");
        $tree1 = explode("/", $tree);
        $where_str = "";
        if (isset($tree1[1])) {
            $where_str .= "(agent_id=0 or agent_id=" . $tree1[1] . ")";
        }

        //增加all的处理
        if (isset($where) && $where['type'] == 'all') {
            $where = [];
        }

        $data = BankSkModel::where("bank_status", 1)->where($where)->where($where_str)->order("bank_sort desc,id desc")->field("id,type,bank_name,bank_number,bank_khm,bank_open,bank_qrcode,min_amcount,max_amcount")->select();
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //充值记录
    public function log_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $start_time = $this->param['start_time'] ?? '';
        $end_time = $this->param['end_time'] ?? '';
        $where = [];
        $where[] = ["uid", '=', $this->uid];
        if (!empty($start_time)) {
            $where[] = ['add_time', ">=", strtotime($start_time . " 00:00:00")];
        }
        if (!empty($end_time)) {
            $where[] = ['add_time', "<=", strtotime($end_time . " 23:59:59")];
        }

        $data = UserOfflineModel::where($where)
            ->field("type,channel,add_time,apply_price,arrival_price,order_sn,update_time,status,user_remarks as remarks")
            ->limit((($pagenow - 1) * $limit), $limit)
            ->order("id desc")
            ->select();

        foreach ($data as $key => $value) {
            $data[$key]['add_time'] = Util::i18n_datetime2str($value['add_time']);
            $data[$key]['update_time'] = empty($value['update_time']) ? '' : Util::i18n_datetime2str($value['update_time']);
            $data[$key]['apply_price'] = Util::i18n_currency($value['apply_price']);
            $data[$key]['arrival_price'] = Util::i18n_currency($value['arrival_price']);
            $data[$key]['remarks'] = empty($value['remarks']) ? "" : lang($value['remarks']);
        }

        $rsps['data'] = $data;
        $rsps['code'] = 1;
        $rsps['total'] = UserOfflineModel::where($where)->count();
        return $rsps;
    }

    public function parame_run()
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $is_online_pay = env('is_online_pay', 0);
        $online_bank_status = env('is_online_pay_bank', 0); //银行渠道
        $online_usdt_status = env('is_online_pay_usdt', 0); // usdt渠道
        $online_wallet_status = env('is_online_pay_wallet', 0); // 电子钱包线上渠道
        $online_pix_status = env('is_online_pay_pix', 0); // pix钱包线上渠道
        $is_offline_pay = env('is_offline_pay', 0);
        $offline_bank_status = env('is_offline_pay_bank', 0); //银行线下
        $offline_usdt_status = env('is_offline_pay_usdt', 0); // usdt线下
        $offline_wallet_status = env('is_offline_pay_wallet', 0); // 电子钱包线下渠道
        $offline_pix_status = env('is_offline_pay_pix', 0); // pix钱包线下渠道
        $hide_rate = env('setting.hide_rate', 0); // 隐藏汇率提示
        $pay_type_first = env('setting.pay_type_first', 0); // 充提方式排序
        $is_bank_open = env('is_bank_open', 0); //是否需要开户行

        if ($is_online_pay == 0) {
            $online_bank_status = $online_usdt_status = $online_wallet_status = $online_pix_status = 0;
        }
        if ($is_offline_pay == 0) {
            $offline_usdt_status = $offline_bank_status = $offline_wallet_status = $offline_pix_status = 0;
        }

        //查找用户的线下银行卡和三方渠道的代理线
        $where_str = "";
        if ($online_usdt_status == 1 || $offline_usdt_status == 1) {
            $tree = UserModel::where("id", $this->uid)->value("tree");
            $tree1 = explode("/", $tree);
            if (isset($tree1[1])) {
                $where_str .= "(agent_id=0 or agent_id=" . $tree1[1] . ")";
            }
        }
        $usdt_type = [];
        if ($online_usdt_status == 1) {
            $usdt = PaySetModel::where(['pay_type' => 1, 'isdel' => 0, 'pay_status' => 1])
                ->where($where_str)
                ->select();
            foreach ($usdt as $key => $val) {
                if (!in_array($val['usdt_type'], $usdt_type)) {
                    $usdt_type[] = $val['usdt_type'];
                }
            }
        }
        $offline_usdt_type = [];
        if ($offline_usdt_status == 1) {
            $offline_usdt = BankSkModel::where(['type' => 1, 'bank_status' => 1, 'delete_time' => 0])
                ->where($where_str)
                ->select();
            foreach ($offline_usdt as $key => $val) {
                if (!in_array($val['bank_name'], $offline_usdt_type)) {
                    $offline_usdt_type[] = $val['bank_name'];
                }
            }
        }
        $financial_status = 0;
        $financial_name = '';
        $financial = Config::get('custom.financial');
        $defa_lang = env('default_country', '');
        if (empty($defa_lang)) {
            $defa_lang = LangModel::where(['is_default' => 1])->value('lang');
        }
        if (isset($financial[$defa_lang])) {
            $financial_status = 1;
            $financial_name = lang($financial[$defa_lang]);
        }

        $online_bank_min = 0;
        $online_bank_max = 0;

        $online_usdt_min = 0;
        $online_usdt_max = 0;

        $online_wallet_min = 0;
        $online_wallet_max = 0;

        $online_pix_min = 0;
        $online_pix_max = 0;

        $offline_bank_min = 0;
        $offline_bank_max = 0;

        $offline_usdt_min = 0;
        $offline_usdt_max = 0;

        $offline_wallet_min = 0;
        $offline_wallet_max = 0;

        $offline_pix_min = 0;
        $offline_pix_max = 0;

        if ($online_bank_status == 1) { //银行卡开启线上充值的时候
            $online_bank_min = PaySetModel::where(['pay_type' => 0])->min('pay_min_amout');
            $online_bank_max = PaySetModel::where(['pay_type' => 0])->max('pay_max_amout');
        }

        if ($online_usdt_status == 1) { //USDT开启线上充值的时候
            $online_usdt_min = PaySetModel::where(['pay_type' => 1])->min('pay_min_amout');
            $online_usdt_max = PaySetModel::where(['pay_type' => 1])->max('pay_max_amout');
        }

        if ($offline_bank_status == 1) { //银行卡开启线下充值的时候
            $offline_bank_min = BankSkModel::where(['type' => 0])->min('min_amcount');
            $offline_bank_max = BankSkModel::where(['type' => 0])->max('max_amcount');
        }

        if ($offline_usdt_status == 1) { //USDT开启线下充值的时候
            $offline_usdt_min = BankSkModel::where(['type' => 1])->min('min_amcount');
            $offline_usdt_max = BankSkModel::where(['type' => 1])->max('max_amcount');
        }

        if ($offline_wallet_status == 1) { //电子钱包开启线下充值的时候
            $offline_wallet_min = BankSkModel::where(['type' => 2])->min('min_amcount');
            $offline_wallet_max = BankSkModel::where(['type' => 2])->max('max_amcount');
        }

        if ($offline_wallet_status == 1) { //pix钱包开启线下充值的时候
            $offline_pix_min = BankSkModel::where(['type' => 3])->min('min_amcount');
            $offline_pix_max = BankSkModel::where(['type' => 3])->max('max_amcount');
        }

        $data = [];
        $data['bank'] = [
            "online" => [
                'status' => $online_bank_status,
                'min' => $online_bank_min,
                'max' => $online_bank_max,
            ],
            "offline" => [
                'status' => $offline_bank_status,
                'min' => $offline_bank_min,
                'max' => $offline_bank_max,
            ],
            'financial_status' => $financial_status,
            'financial_name' => $financial_name,
        ];

        $data['usdt'] = [
            "online" => [
                'status' => $online_usdt_status,
                'usdt_type' => $usdt_type,
                'min' => $online_usdt_min,
                'max' => $online_usdt_max,
            ],
            "offline" => [
                'status' => $offline_usdt_status,
                'usdt_type' => $offline_usdt_type,
                'min' => $offline_usdt_min,
                'max' => $offline_usdt_max,
            ]
        ];
        $data['wallet'] = [
            'online' => [
                'status' => $online_wallet_status,
                'min' => $online_wallet_min,
                'max' => $online_wallet_max,
            ],
            'offline' => [
                'status' => $offline_wallet_status,
                'min' => $offline_wallet_min,
                'max' => $offline_wallet_max,
            ],

        ];
        $data['pix'] = [
            'online' => [
                'status' => $online_pix_status,
                'min' => $online_pix_min,
                'max' => $online_pix_max,
            ],
            'offline' => [
                'status' => $offline_pix_status,
                'min' => $offline_pix_min,
                'max' => $offline_pix_max,
            ],

        ];
        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();

        $user = UserModel::uinfo($this->uid, ",a.savings");
        $data['savings'] = $user['savings'];
        $data['balance'] = $user['balance'];
        $data['currency_type'] = $currency['defa_data']['type']['val'] ?? 2; //默认货币 默认为法币
        $data['recharge_rate'] = $currency['defa_data']['recharge_rate'];
        $data['withdrawal_rate'] = $currency['defa_data']['withdrawal_rate'];

//        $data['recharge_rate'] = $sys['recharge_rate'];
//        $data['withdrawal_rate'] = $sys['withdrawal_rate'];
//        $data['currency_type'] = env('CURRENCY_TYPE', 0);
        $data['quick_recharge'] = isset($sys['quick_recharge']) ? explode(",", $sys['quick_recharge']) : [500, 1000, 2000, 5000, 10000, 20000, 50000];
        $data['usdt_quick_recharge'] = isset($sys['usdt_quick_recharge']) ? explode(",", $sys['usdt_quick_recharge']) : [10, 50, 100, 500, 1000, 2000, 5000];
        $data['quick_withdrawal'] = isset($sys['quick_withdrawal']) ? explode(",", $sys['quick_withdrawal']) : [500, 1000, 2000, 5000, 10000, 20000, 50000];
        $data['usdt_quick_withdrawal'] = isset($sys['usdt_quick_withdrawal']) ? explode(",", $sys['usdt_quick_withdrawal']) : [10, 50, 100, 500, 1000, 2000, 5000];
        $data['currency_code'] = Util::i18n_currency_code();
        $data['hide_rate'] = $hide_rate;
        $data['pay_type_first'] = $pay_type_first;
        $data['is_bank_open'] = $is_bank_open;
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function mosaic_gold_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $start_time = $this->param['start_time'] ?? '';
        $end_time = $this->param['end_time'] ?? '';
        $type = $this->param['type'] ?? 1;
        $where = $data = [];
        if ($type == 1) {
            $where[] = ['a.a_type', "=", 3];
            $where[] = ['a.uid', "=", $this->uid];
            if (!empty($start_time) || !empty($end_time)) {
                if (!empty($start_time)) $where[] = ['a.add_time', ">=", strtotime($start_time . " 00:00:00")];
                if (!empty($end_time)) $where[] = ['a.add_time', "<=", strtotime($end_time . " 23:59:59")];
            }


            $data = ArtificialBalanceModel::alias("a")
                ->where($where)
                ->field("a.price,a.add_time,a.a_type,a.msg")
                ->limit((($pagenow - 1) * $limit), $limit)
                ->order('a.id desc')
                ->select();
            foreach ($data as &$val) {
                $val['add_time'] = Util::i18n_datetime2str($val['add_time']);
                $val['price'] = Util::i18n_number_format($val['price']);
                $val['msg'] = empty($val['msg']) ? '' : lang($val['msg']);
            }
        } elseif ($type == 2) {
            $where[] = ['a.uid', "=", $this->uid];
            if (!empty($start_time) || !empty($end_time)) {
                if (!empty($start_time)) $where[] = ['a.add_time', ">=", strtotime($start_time . " 00:00:00")];
                if (!empty($end_time)) $where[] = ['a.add_time', "<=", strtotime($end_time . " 23:59:59")];
            }


            $list = ActionApplyModel::alias("a")
                ->join("dq_action c", "a.action_id=c.id")
                ->where($where)
                ->field("a.ck_hl_amount,a.gd_amount,c.base_type,a.add_time")
                ->limit((($pagenow - 1) * $limit), $limit)
                ->order('a.id desc')
                ->select();

            foreach ($list as $key => $val) {
                $data[$key]['add_time'] = Util::i18n_datetime2str($val['add_time']);
                if ($val['base_type'] == 1 || $val['base_type'] == 2) {
                    $data[$key]['price'] = Util::i18n_number_format($val['ck_hl_amount']);
                } else {
                    $data[$key]['price'] = Util::i18n_number_format($val['gd_amount']);
                }
                $data[$key]['a_type'] = $val['base_type'];
                if ($val['base_type'] == 1) {
                    $data[$key]['msg'] = lang('注册首存');
                }
                if ($val['base_type'] == 2) {
                    $data[$key]['msg'] = lang('再存优惠');
                }
                if ($val['base_type'] == 3) {
                    $data[$key]['msg'] = lang('一般优惠');
                }
                if ($val['base_type'] == 4) {
                    $data[$key]['msg'] = lang('体验金');
                }
            }
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }


    public function all_collection_run()
    {
        $pay_type = [
            0 => '线下支付',
            1 => '渠道支付',
            2 => 'USDT渠道支付',
            3 => 'USDT挂码',
            4 => '线下电子钱包',
            5 => '线上电子钱包',
            6 => '线下PIX钱包',
            7 => '线上PIX钱包',
        ];
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $channel = Util::getOpenChannel();

        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();
        $defa_currency = $currency['defa_data'];


        $off_wh[] = ['bank_status', '=', 1];
        $on_wh[] = ['pay_status', '=', 1];
        $on_wh[] = ['isdel', '=', 0];
        $off_type = [];
        $on_type = [];
        if (isset($channel[0])) { //开启线下银行卡
            array_push($off_type, 0); //type
        }
        if (isset($channel[1])) { //开启线上银行卡
            array_push($on_type, 0); //pay_type
        }
        if (isset($channel[2])) { //开启线上USDT
            array_push($on_type, 1); //pay_type
        }
        if (isset($channel[3])) { //开启线下USDT
            array_push($off_type, 1); //type
        }
        if (isset($channel[4])) { //开启线下电子钱包
            array_push($off_type, 2); //type
        }
        if (isset($channel[5])) { //开启线上电子钱包
            array_push($on_type, 2); //type
        }
        if (isset($channel[6])) { //开启线下PIX
            array_push($off_type, 3); //type
        }
        if (isset($channel[7])) { //开启线上PIX
            array_push($on_type, 3); //type
        }
        $where = "1=1";
        $tag_recharge = MemberService::tagBanRecharge($this->uid);
        if ($tag_recharge < 0) {
            ajax_return("您无法申请充值,请联系客服", 0);
        } elseif ($tag_recharge > 0) {
            $where .= " AND id = '{$tag_recharge}'";
        }
        $uinfo = UserModel::where("id", $this->uid)->field("tree,level_id,savings")->find();
        if (isset($uinfo['tree'])) {
            $tree1 = explode("/", $uinfo['tree']);
            if (isset($tree1[1])) {
                $where .= " and (agent_id=0 or agent_id=" . $tree1[1] . ")";
            }

            $level_id = $uinfo['level_id'];
            $where .= " and ((start_level_id=0 or start_level_id <= " . $level_id . ") and (end_level_id=0 or end_level_id>=" . $level_id . "))";
        }
        $money = UserOfflineModel::where([["uid", "=", $this->uid], ["status", "in", "3,4"]])->sum("apply_price");
        if ($money * 1 > 0) {
            $where .= " and (new_user=0 or new_user=2)";
        } else {
            $where .= " and (new_user=0 or new_user=1)";
        }
        $off_wh[] = ['type', 'in', $off_type];
        $on_wh[] = ['pay_type', 'in', $on_type];

//        $pay_set_bank = [];
        $rate = [];
        //Alphapo 数字货币钱包 digital_currency_open
        if (env('digital_currency_open', 0)) {
            $pay_sign = 'AlphapoPay';
            $p_w = [];
            $p_w[] = ['pay_status', '=', 1]; //开启代付的时候
            $p_w[] = ['pay_sys_sign', '=', $pay_sign]; //开启代付的时候
            $pay_config = PaySetModel::where($p_w)->where('pay_bank', '<>', '[]')->find();
//            $pay_bank = json_decode($pay_config['pay_bank'], true);
//            foreach($pay_bank as $key => $val){
//                $pay_set_bank[] = $val['bank_name'];
//            }
//            $pay_set_bank = array_unique($pay_set_bank);
            if (!empty($pay_config)) {
                $sys = (new ApiRedis())->systemInfo();
                $p_config = [];
                $pay_param = $pay_config['pay_param'] ? json_decode($pay_config['pay_param'], true) : [];
                foreach ($pay_param as $key => $value) {
                    $p_config[$value['key_name']] = $value['key_value'];
                }
                $Entry = new Entry($p_config, $sys['domain']);
                $result = $Entry->get_rate($pay_config['pay_sys_sign']);//回调结果
                if ($result['code'] == 1) {
                    $rate_arr = [
                        'BTC' => 0.02,
                        'LTC' => 0.02,
                        'BCH' => 0.02,
                        'ETH' => 0.02,
                        'EUR' => 0.02,
                        'USD' => 0.02,
                        'JPY' => 0.02,
                        'DOGE' => 0.02,
                        'TRX' => 0.02,
                        'USDTT' => 0,
                        'USDTB' => 0,
                    ];
                    $rate = $result['data'];
                    foreach ($rate as $kk => &$vv) {
                        if (isset($rate_arr[$kk]) && $rate_arr[$kk] > 0) {
                            $vv = $vv + ($vv * $rate_arr[$kk]);
                        }
                    }
                }
            }
        }


        $offline = BankSkModel::where($off_wh)
            ->where($where)
            ->with('currencyApi')
            ->where('delete_time=0')
            ->order('bank_sort desc')
            ->order("bank_sort desc,id desc")
            ->field("id,type,bank_name,bank_number,icon,bank_khm,bank_open,bank_qrcode,min_amcount,max_amcount,financial,currency_id,is_all,is_hot")
            ->select();
        foreach ($offline as $k => $v) {
            if (isset($v['currencyApi'])) {
                $offline[$k]['currencyApi']['currency_symbol'] = $v['currencyApi']['symbol'];//Util::i18n_currency_by_code(isset($v['currencyApi']['country_code']) ? $v['currencyApi']['symbol'] : '');
                $offline[$k]['currencyApi']['currency_code'] = Util::i18n_currency_code(isset($v['currencyApi']['country_code']) ? $v['currencyApi']['country_code'] : '');
            }
        }
        $data['offline'] = $offline;
        $online = PaySetModel::where($on_wh)
            ->where($where)
            ->with('currencyApi')
            ->where('isdel = 0')
            ->order('sort desc')
            ->field("id,usdt_type,pay_type as type,pay_sys_sign,pay_name,pay_min_amout,pay_max_amout,currency_id,is_all,icon,is_hot, pay_param, pay_off_field")
            ->filter(function ($data) use ($rate) {
                $data['pay_off_field'] = json_decode($data['pay_off_field'], true);
                if ($data['icon'] == '/static/admin/img/add.png') $data['icon'] = "";
                if (env('digital_currency_open', 0) && $data['pay_sys_sign'] == 'AlphapoPay') {
                    $pay_param = $data['pay_param'] ? json_decode($data['pay_param'], true) : [];
                    foreach ($pay_param as $key => $value) {
                        if ($value['key_name'] == 'currency') {
                            $data['pay_currency'] = $value['key_value'];
                            $pay_currency_rate = 1.000000;
                            if (isset($rate[$value['key_value']])) {
                                $data['pay_currency_rate'] = 1 / $rate[$value['key_value']];
                            } else {
                                $data['pay_currency_rate'] = $pay_currency_rate;
                            }

                        }
                    }
                }
                //特定金流 不进行前端金额转换
                $p_s_s = env('pay_set_not_conversion', '');
                if (!empty($p_s_s)) {
                    $p_s_s_arr = explode(',', $p_s_s);
                    $data['rate_conversion'] = 2;
                    if (in_array($data['pay_sys_sign'], $p_s_s_arr)) {
                        $data['rate_conversion'] = 1; //1进行转换 从默认货币转成渠道货币
                    }
                }
                unset($data['pay_param']);
            })
            ->select();
        foreach ($online as $k => $v) {
            if (isset($v['currencyApi'])) {
                $online[$k]['currencyApi']['currency_symbol'] = $v['currencyApi']['symbol'];//Util::i18n_currency_by_code(isset($v['currencyApi']['country_code']) ? $v['currencyApi']['country_code'] : '');
                $online[$k]['currencyApi']['currency_code'] = $v['currencyApi']['currency_code'];//Util::i18n_currency_code(isset($v['currencyApi']['country_code']) ? $v['currencyApi']['country_code'] : '');
            }
        }
        $data['online'] = $online;

        $this->assemblyData($data, 'offline', $currency);
        $this->assemblyData($data, 'online', $currency);
        //重新索引数组
        $data['offline'] = array_values($data['offline'] ? $data['offline']->toArray() : []);
        $data['online'] = array_values($data['online'] ? $data['online']->toArray() : []);

        $data['hide_rate'] = env('setting.hide_rate', 0); // 隐藏汇率提示
        //改为获取货币管理中的默认货币
        $data['currency_defa'] = $defa_currency['type']['val'] ?? 2; //默认货币 默认为法币
        $data['recharge_rate'] = $sys['recharge_rate'] ?? 0;
        $data['withdrawal_rate'] = $sys['withdrawal_rate'] ?? 0;
        $data['currency_type'] = env('CURRENCY_TYPE', 0);
        $data['currency_code'] = Util::getCurrencyDefaultSymbol('code');
        $data['currency_symbol'] = Util::getCurrencyDefaultSymbol('symbol');
        $data['pay_agent'] = [];
        // 获取银商列表
        if (isset($channel[20])) { //开启银商
            $data['pay_agent'] = PayAgentModel::where([
                'status' => 1,
                'isopen' => 1
            ])->field("id,nickname,avatar,telegram,whats_app")->order("sort", 'ASC')->select();
        }
        //获取银行支付体系列表
        $data['bank_payment_system'] = Config::get('custom.bank_payment_system');
        //获取系统充值金额配置
        $rechargeAmountSitting = [
            'bank_recharge_list' => array_filter(explode(',', $sys['quick_recharge'])),
            'hot_bank_recharge_list' => array_filter(explode(',', $sys['hot_quick_recharge'])),
            'usdt_recharge_list' => array_filter(explode(',', $sys['usdt_quick_recharge'])),
        ];
        $data['recharge_amount_sitting'] = $rechargeAmountSitting;
        //充值优惠
        $uinfo['id'] = $this->uid;
        $rechargeActionRegJsonList = ActionFactory::getRechargeDiscountActionDetail($uinfo);
        $data['action_list'] = $rechargeActionRegJsonList['action_list'];
        $rechargeActionRegJsonList = $rechargeActionRegJsonList['reg_json'];
        $data['action_is_force_join'] = env('action.action_is_force_join', 0);
        $rechargeDiscountList = [];
        //循环充值金额
        foreach ($rechargeAmountSitting['bank_recharge_list'] as &$v) {
            //循环活动
            foreach ($rechargeActionRegJsonList as &$_v) {
                //判断充值金额是否满足活动条件
                if ($v >= $_v['min_reg'] && $v <= $_v['max_reg']) {
                    $rechargeDiscountList[] = [
                        'type' => $_v['type'],
                        'amount' => $v,
                        'bl_or_amount' => $_v['bl_or_amount']
                    ];
                    break;
                }
            }
        }
        $data['recharge_amount_discount'] = $rechargeDiscountList;
        $data['recharge_action_reg_json'] = $rechargeActionRegJsonList;

        //总流水
        $data['water'] = UserWaterModel::where('water', '>', 0)->where('uid', '=', $this->uid)->sum('water'); // 总游戏流水
//        $data['finish_water'] = MemberService::getUserFinishWater($this->uid); // 游戏流水
        $data['finish_water'] = $data['water'] - $uinfo['savings']; // 已完成的流水

        //获取用户指定游戏流水数据
        $data['user_appoint_savings'] = MemberService::getUserAppointSavings($this->uid);

        //完成总流水加上指定游戏流水
        if ($data['user_appoint_savings']['finish_water']) {
            $data['finish_water'] += $data['user_appoint_savings']['finish_water'];
        }
//        if( $data['user_appoint_savings']['water'] ){
//            $data['finish_water'] -= $data['user_appoint_savings']['water'];
//        }
        $data['finish_water'] = round($data['finish_water'], 2);
        //支付类型
        $pay_types = PayTypeModel::where("status=1")->order('sort ASC')->column("id,name,icon,is_hot");
        foreach ($pay_types as $value) {
            $pay_temp = [];
            $pay_channel = PaySetModel::where("pay_types", $value['id'])
                ->where($on_wh)
                ->where($where)
                ->with('currencyApi')
                ->order('sort ASC')
                ->field("id,pay_type as type,usdt_type,pay_name,pay_min_amout,pay_max_amout,currency_id,icon,is_hot, pay_param, pay_sys_sign, pay_off_field")
                ->filter(function ($data) use ($rate) {
                    $data['pay_off_field'] = json_decode($data['pay_off_field'], true);
                    if (env('digital_currency_open', 0) && $data['pay_sys_sign'] == 'AlphapoPay') {
                        $pay_param = json_decode($data['pay_param'], true);
                        foreach ($pay_param as $key => $value) {
                            if ($value['key_name'] == 'currency') {
                                $data['pay_currency'] = $value['key_value'];
                                $pay_currency_rate = 1.00000000;
                                if (isset($rate[$value['key_value']])) {
                                    $data['pay_currency_rate'] = sprintf('%.8f', 1 / $rate[$value['key_value']]);
                                } else {
                                    $data['pay_currency_rate'] = $pay_currency_rate;
                                }
//                                $data['pay_currency_rate'] = $rate[$value['key_value']] ?? 1.000000;
                            }
                        }
                    }
                    //特定金流 不进行前端金额转换
                    $p_s_s = env('pay_set_not_conversion', '');
                    if (!empty($p_s_s)) {
                        $p_s_s_arr = explode(',', $p_s_s);
                        $data['rate_conversion'] = 2;
                        if (in_array($data['pay_sys_sign'], $p_s_s_arr)) {
                            $data['rate_conversion'] = 1; //1进行转换 从默认货币转成渠道货币
                        }
                    }
                    unset($data['pay_param']);
                })
                ->select();
            foreach ($pay_channel as $val) {
                $val['is_online'] = 1;//线上平台 1是 0否
                if (isset($val['currencyApi'])) {
                    $val['currencyApi']['currency_symbol'] = Util::i18n_currency_by_code(isset($val['currencyApi']['country_code']) ? $val['currencyApi']['country_code'] : '');
                    $val['currencyApi']['currency_code'] = Util::i18n_currency_code(isset($val['currencyApi']['country_code']) ? $val['currencyApi']['country_code'] : '');
                }
                $pay_temp[] = $val;
            }
            $this->assemblyDataItem($pay_temp, 'online', $currency);


            $pay_channel_offline = BankSkModel::where("pay_types", $value['id'])
                ->where($off_wh)->where($where)
                ->with('currencyApi')->where("delete_time=0")->order('bank_sort ASC')->field("id,type,bank_name as name,bank_number,icon,bank_khm,bank_open,bank_qrcode,min_amcount as pay_min_amout,max_amcount as pay_max_amout,currency_id,icon,is_hot")->select();
            $pay_temp_ = [];
            foreach ($pay_channel_offline as $val) {
                $val['usdt_type'] = '';
                $val['is_online'] = 0;//线上平台 1是 0否
                if (isset($val['currencyApi'])) {
                    $val['currencyApi']['currency_symbol'] = Util::i18n_currency_by_code(isset($val['currencyApi']['country_code']) ? $val['currencyApi']['country_code'] : '');
                    $val['currencyApi']['currency_code'] = Util::i18n_currency_code(isset($val['currencyApi']['country_code']) ? $val['currencyApi']['country_code'] : '');
                }
                $pay_temp_[] = $val;
            }
            $this->assemblyDataItem($pay_temp_, 'offline', $currency);
            $pay_temp = array_merge($pay_temp, $pay_temp_);
            if ($pay_temp) {
                //下面有渠道才会显示这个分类
                $data['pay_types'][] = [
                    'id' => $value['id'],
                    'name' => $value['name'],
                    'icon' => $value['icon'],
                    'is_hot' => $value['is_hot'],
                    'pay_channel' => $pay_temp
                ];
            }

        }
//        if( isset($data['user_appoint_savings']['finish_water']) ){
//            $data['user_appoint_savings']['finish_water'] = Util::i18n_number_format($data['user_appoint_savings']['finish_water']);
//        }
//        if( isset($data['user_appoint_savings']['water']) ){
//            $data['user_appoint_savings']['water'] = Util::i18n_number_format($data['user_appoint_savings']['water']);
//        }
//        $data['water'] = Util::i18n_number_format($data['water']);
//        $data['finish_water'] = Util::i18n_number_format($data['finish_water']);

        $rsps = [];
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function get_bank_set_content_run()
    {
        $pay_type = $this->param['pay_type'];
        $BankSkSetModel = new BankSkSetModel();
        $res = $BankSkSetModel->where("pay_type", $pay_type)->find();
        $data['set_content'] = empty($res['set_content']) ? [] : unserialize($res['set_content']);
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function assemblyData(&$data, $type, $currency)
    {
        $lang = Request::header('lang') ?? ''; //空的时候 获取默认币种
        $defa_lang = LangModel::where(['is_default' => 1])->value('lang');
        foreach ($data[$type] as $k => $v) {
            if ($type == 'online') {
                switch ($v['type']) {
                    case 0 :
                        $v['pay_type'] = 1;
                        break;
                    case 1 :
                        $v['pay_type'] = 2;
                        break;
                    case 2 :
                        $v['pay_type'] = 5;
                        break;
                    case 3 :
                        $v['pay_type'] = 7;
                        break;
                }
            }
            if ($type == 'offline') {
                switch ($v['type']) {
                    case 0 :
                        $v['pay_type'] = 0;
                        break;
                    case 1 :
                        $v['pay_type'] = 3;
                        break;
                    case 2 :
                        $v['pay_type'] = 4;
                        break;
                    case 3 :
                        $v['pay_type'] = 6;
                        break;
                }

                if ($v['type'] != 0) {
                    unset($v['financial']);
                } else {
                    $financial = Config::get('custom.financial');
                    if (empty($financial[$defa_lang])) {
                        unset($v['financial']);
                    }
                }
            }

            //当货币id 等于0 的时候 没有绑定货币的时候
            // 当是银行卡的的时候 默认货币是普通货币就用默认货币的参数
            // 如果是usdt 就直接获取 usdt货币的参数 除非usdt货币 被关闭了。
            if ($v['currency_id'] == 0) {
                if ($v['type'] == 1) { //支付渠道是usdt的时候
                    //当usdt的货币正常状态的时候
                    if (isset($currency['usdt_data'][0])) {
                        $v['currencyApi'] = $currency['usdt_data'][0];
                    } else {
                        //当usdt的货币已经关闭的时候 删除该渠道
                        unset($data[$type][$k]);
                    }
                } else { //支付渠道是银行卡 电子钱包 pix钱包的时候
                    //默认币种是 银行卡 获取默认币种的数据
                    if (isset($currency['defa_data']) && $currency['defa_data']['type']['val'] == 2) {
                        $v['currencyApi'] = $currency['defa_data'];
                    } else {
                        //默认货币是USDT的时候 不显示这个渠道
                        unset($data[$type][$k]);
                    }
                }
            }

            //已经绑定货币的渠道 币种关闭的渠道不显示
            if (isset($v['currencyApi']) && $v['currencyApi']['state'] == 2) {
                unset($data[$type][$k]);
            }


            //切换的语言选择充值渠道 & 通用渠道 1是通用渠道 USDT不存在语种问题
            if ((isset($v['is_all']) && $v['is_all'] == 2 && $v['type'] != 1) && (isset($v['currencyApi']) && $v['currencyApi']['country_code'] != $lang)) {
                unset($data[$type][$k]);
            }

            //默认货币以id为准 如果渠道中的货币id 跟默认货币id一致就 隐藏汇率
            if ((isset($v['currencyApi']) && isset($currency['defa_data']) && $v['currencyApi']['id'] == $currency['defa_data']['id'])) {
                $v['hide_rate'] = 1;
            } else {
                $v['hide_rate'] = 0;
            }
        }
    }

    public function assemblyDataItem(&$data, $type, $currency)
    {
        $lang = Request::header('lang') ?? ''; //空的时候 获取默认币种
        $defa_lang = LangModel::where(['is_default' => 1])->value('lang');
        foreach ($data as $k => $v) {
            if ($type == 'online') {
                switch ($v['type']) {
                    case 0 :
                        $v['pay_type'] = 1;
                        break;
                    case 1 :
                        $v['pay_type'] = 2;
                        break;
                    case 2 :
                        $v['pay_type'] = 5;
                        break;
                    case 3 :
                        $v['pay_type'] = 7;
                        break;
                }
            }
            if ($type == 'offline') {
                switch ($v['type']) {
                    case 0 :
                        $v['pay_type'] = 0;
                        break;
                    case 1 :
                        $v['pay_type'] = 3;
                        break;
                    case 2 :
                        $v['pay_type'] = 4;
                        break;
                    case 3 :
                        $v['pay_type'] = 6;
                        break;
                }

                if ($v['type'] != 0) {
                    unset($v['financial']);
                } else {
                    $financial = Config::get('custom.financial');
                    if (empty($financial[$defa_lang])) {
                        unset($v['financial']);
                    }
                }
            }

            //当货币id 等于0 的时候 没有绑定货币的时候
            // 当是银行卡的的时候 默认货币是普通货币就用默认货币的参数
            // 如果是usdt 就直接获取 usdt货币的参数 除非usdt货币 被关闭了。
            if ($v['currency_id'] == 0) {
                if ($v['type'] == 1) { //支付渠道是usdt的时候
                    //当usdt的货币正常状态的时候
                    if (isset($currency['usdt_data'][0])) {
                        $v['currencyApi'] = $currency['usdt_data'][0];
                    } else {
                        //当usdt的货币已经关闭的时候 删除该渠道
                        unset($data[$type][$k]);
                    }
                } else { //支付渠道是银行卡 电子钱包 pix钱包的时候
                    //默认币种是 银行卡 获取默认币种的数据
                    if (isset($currency['defa_data']) && $currency['defa_data']['type']['val'] == 2) {
                        $v['currencyApi'] = $currency['defa_data'];
                    } else {
                        //默认货币是USDT的时候 不显示这个渠道
                        unset($data[$type][$k]);
                    }
                }
            }

            //已经绑定货币的渠道 币种关闭的渠道不显示
            if (isset($v['currencyApi']) && $v['currencyApi']['state'] == 2) {
                unset($data[$type][$k]);
            }


            //切换的语言选择充值渠道 & 通用渠道 1是通用渠道 USDT不存在语种问题
            if ((isset($v['is_all']) && $v['is_all'] == 2 && $v['type'] != 1) && (isset($v['currencyApi']) && $v['currencyApi']['country_code'] != $lang)) {
                unset($data[$type][$k]);
            }

            //默认货币以id为准 如果渠道中的货币id 跟默认货币id一致就 隐藏汇率
            if ((isset($v['currencyApi']) && isset($currency['defa_data']) && $v['currencyApi']['id'] == $currency['defa_data']['id'])) {
                $v['hide_rate'] = 1;
            } else {
                $v['hide_rate'] = 0;
            }
        }
    }

}