<?php

namespace app\api\factory_v2;

use app\common\model\ArtificialBalanceModel;
use app\common\model\ActionApplyModel;
use app\common\model\PayAgentModel;
use app\common\model\UserAwardCfgsModel;
use app\common\model\UserOfflineModel;
use app\common\model\BankSkModel;
use app\common\model\UserBankModel;
use app\common\model\BankSkSetModel;
use app\common\model\UserModel;
use app\common\model\LangModel;
use app\api\redis\ApiRedis;
use app\common\model\PaySetModel;
use app\common\model\UserWaterModel;
use app\common\payment\Entry;
use app\common\redis\CommomRedis;
use app\common\service\MemberService;
use app\common\service\MessageService;
use think\facade\Request;
use app\common\lib\Util;
use think\facade\Db;
use think\facade\Config;

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
        if ($sys['test_withdraw'] == 0) {
            $user = UserModel::uinfo($this->uid, ",a.lebal");//
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
        $info['apply_price'] = round($info['apply_price'], 2);
        if ($type == 0 || $type == 3) { // 线下充值
            $res = $this->hang_card_pay($info);
        } elseif ($type == 1 || $type == 5 || $type == 7) { // 银行卡渠道充值
            $res = $this->online_pay($info);
        } elseif ($type == 2) { // usdt 渠道充值
            $res = $this->usdt_pay($info);
        } else if ($type == 4 || $type == 6) { // 电子钱包 PIX
            $res = $this->hang_card_pay($info);
        }
        $isOnSystemMessage = env('IS_ON_SYSTEM_MESSAGE', 0);
        if (isset($res['code']) && $res['code'] == 1 && $isOnSystemMessage == 1) {
            $t = $type == 0 || $type == 3 ? 1 : 2;
            MessageService::sendAdminGroupMsg('有新的充值申请,请及时处理', 'toast', '0', '通知', 0, $t);
        }
        return $res;
    }

    public function usdt_pay($info)
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
            if ($pay_type['pay_min_amout'] > $info['apply_price']) {
                $msg = lang("单笔最低充值%s");
                ajax_return(sprintf($msg, $pay_type['pay_min_amout']), 0);
            }
            if ($pay_type['pay_max_amout'] < $info['apply_price']) {
                $msg = lang("单笔最高充值%s");
                ajax_return(sprintf($msg, $pay_type['pay_max_amout']), 0);
            }
            $currency = Util::getPayCurrency($pay_type);
            $user = [];
            if ($this->uid) {
                $user = UserModel::where("id", $this->uid)->field("id as user_id,username,phone")->find()->toArray();
            }
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
            $add['not_auto_action'] = $info['not_auto_action']??0; // 是否自动参与活动 0参与 1不参与 其他指定活动参与
            $add['lebal'] = UserModel::where('id', $this->uid)->value('lebal');
            $pay_id = UserOfflineModel::insertGetId($add);
            if ($pay_id) {
                $upd = [];
                $upd['order_sn'] = date("YmdHi") . $pay_id;//支付订单号
                $pay_res = UserOfflineModel::where("id", $pay_id)->update($upd);
                $other = [];
                foreach ($user as $key => $value) {
                    $other[$key] = $value;
                }
                $other['order_sn'] = $upd['order_sn'];
                $other['apply_price'] = $add['apply_price'];
                $other['phone'] = $user['phone'];
                $other['pay_ip'] = $add['pay_ip'];
                $other['add_time'] = date("Y-m-d H:i:s", $add['add_time']);
                $other['call_domain'] = getAppUrl();
                $other['notify_url'] = $sys['domain'] . '/notify/' . $pay_type['id']; // 异步通知地址

                $Entry = new Entry($pay_type['config'], $sys['domain']);//支付包
                $pay_info = $Entry->pay($pay_type['pay_sys_sign'], $other);

                if (isset($pay_info['pay_url'])) {
                    $pay_info['handle_type'] = "navto";
                } else {
                    $pay_info['handle_type'] = 'form';
                }
                $pay_info['is_qrcode'] = isset($pay_info['is_qrcode']) ? 1 : 0;
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

    public function online_pay($info)
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

        if ($pay_type['pay_min_amout'] > $info['apply_price']) {
            $msg = lang("单笔最低充值%s");
            ajax_return(sprintf($msg, $pay_type['pay_min_amout']), 0);
        }
        if ($pay_type['pay_max_amout'] < $info['apply_price']) {
            $msg = lang("单笔最高充值%s");
            ajax_return(sprintf($msg, $pay_type['pay_max_amout']), 0);
        }

        $rsps = [];
        $sys = $ApiRedis->systemInfo();
        Db::startTrans();
        try {
            $currency = Util::getPayCurrency($pay_type);
            $user = [];
            if ($this->uid) {
                $user = UserModel::where("id", $this->uid)->field("id as user_id,username,phone,qh")->find()->toArray();
            }
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
            $add['not_auto_action'] = $info['not_auto_action']??0; // 是否自动参与活动 0参与 1不参与 其他指定活动参与
            $add['lebal'] = UserModel::where('id', $this->uid)->value('lebal');

            $pay_id = UserOfflineModel::insertGetId($add);
            $upd['order_sn'] = date("YmdHi") . $pay_id;//支付订单号
            UserOfflineModel::where("id", $pay_id)->update($upd);
            $pay = UserOfflineModel::where("id", $pay_id)->find();
            $other = [];
            foreach ($user as $key => $value) {
                $other[$key] = $value;
            }
            $other['order_sn'] = $pay['order_sn'];
            $other['apply_price'] = $pay['apply_price'];
            $other['phone'] = $user['phone'];
            $other['pay_ip'] = $pay['pay_ip'];
            $other['add_time'] = date("Y-m-d H:i:s", $pay['add_time']);
            $domain = env('CHARGE_WITHDRAWAL_DOMAIN', '');
            if (!$domain) {
                $domain = $sys['domain'];
            }
            $other['call_domain'] = getAppUrl() . "/#/paysucc/" . floatval($pay['apply_price']) . "/{$currency['arrival_currency']}";
            $other['notify_url'] = $domain . '/notify/' . $pay_type['id']; // 异步通知地址

            $Entry = new Entry($pay_type['config'], $sys['domain']);//支付包
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
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
            $rsps['data'] = $pay_info;
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

    public function hang_card_pay($info)
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
            $add['apply_price'] = $info['apply_price'];
            $add['pay_currency'] = $currency['pay_currency'];
            $add['arrival_currency'] = $currency['arrival_currency'];
            $add['exchange_rate'] = $currency['exchange_rate'];
            $add['remarks'] = $info['remarks'] ?? "";
            $add['set_content'] = isset($info['set_content']) ? $info['set_content'] : '';
            $add['not_auto_action'] = $info['not_auto_action']??0; // 是否自动参与活动 0参与 1不参与 其他指定活动参与
            $add['pay_ip'] = Util::get_ip();
            $add['lebal'] = UserModel::where('id', $this->uid)->value('lebal');
            $id = UserOfflineModel::insertGetId($add);
            $upd['id'] = $id;
            $upd['order_sn'] = date("YmdHi") . $id;
            UserOfflineModel::update($upd);
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

    /**
     * 获取充值配置数据
     * @return array
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function recharge_config_run()
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
                $offline[$k]['currencyApi']['currency_symbol'] = Util::i18n_currency_by_code(isset($v['currencyApi']['country_code']) ? $v['currencyApi']['country_code'] : '');
                $offline[$k]['currencyApi']['currency_code'] = Util::i18n_currency_code(isset($v['currencyApi']['country_code']) ? $v['currencyApi']['country_code'] : '');
            }
        }
        $data['offline'] = $offline;
        $online = PaySetModel::where($on_wh)
            ->where($where)
            ->with('currencyApi')
            ->where('isdel = 0')
            ->order('sort desc')
            ->field("id,usdt_type,pay_type as type,pay_sys_sign,pay_name,pay_min_amout,pay_max_amout,currency_id,is_all,icon,is_hot")
            ->filter(function ($data) {
                if ($data['icon'] == '/static/admin/img/add.png') $data['icon'] = "";
            })
            ->select();
        foreach ($online as $k => $v) {
            if (isset($v['currencyApi'])) {
                $online[$k]['currencyApi']['currency_symbol'] = Util::i18n_currency_by_code(isset($v['currencyApi']['country_code']) ? $v['currencyApi']['country_code'] : '');
                $online[$k]['currencyApi']['currency_code'] = Util::i18n_currency_code(isset($v['currencyApi']['country_code']) ? $v['currencyApi']['country_code'] : '');
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
        $data['currency_code'] = Util::i18n_currency_code();
        $data['currency_symbol'] = Util::i18n_currency_no_amount();
        $data['pay_agent'] = [];
        // 获取银商列表
        if (isset($channel[20])) { //开启银商
            $data['pay_agent'] = PayAgentModel::where([
                'status' => 1,
                'isopen' => 1
            ])->field("id,nickname,avatar,telegram,whats_app")->order("sort", 'ASC')->select();
        }
        //获取系统充值金额配置
        $rechargeAmountSitting = [
            'bank_recharge_list' => explode(',', $sys['quick_recharge']),
            'hot_bank_recharge_list' => explode(',', $sys['hot_quick_recharge']),
            'usdt_recharge_list' => explode(',', $sys['usdt_quick_recharge']),
        ];
        $data['recharge_amount_sitting'] = $rechargeAmountSitting;
        //充值优惠
        $uinfo['id'] = $this->uid;
        $rechargeActionRegJsonList = ActionFactory::getRechargeDiscountActionDetail($uinfo);
        $data['action_list'] = $rechargeActionRegJsonList['action_list'];
        $rechargeActionRegJsonList = $rechargeActionRegJsonList['reg_json'];
//        $data['action_is_force_join'] = env('action.action_is_force_join', 0);
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
//        $data['recharge_amount_discount'] = $rechargeDiscountList;
//        $data['recharge_action_reg_json'] = $rechargeActionRegJsonList;
        $rsps = [];
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

}