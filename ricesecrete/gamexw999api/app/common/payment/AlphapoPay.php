<?php

namespace app\common\payment;

use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\model\PaySetModel;
use app\common\model\UserBankModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use think\facade\Db;
use think\facade\Log;
use think\facade\Request;

class AlphapoPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $currency =  $this->pay_set['currency'];
        $info = Db::name('user_address')->where(['uid' => $pay_arr['user_id'], 'pay_id' => $pay_arr['pay_id']])->find();
        if ($info) {
            $address = $info['address'];
            $qr_code = $info['qr_code'];
            $href = "bitcoin:{$address}";
        } else {
            $params = [
                'foreign_id' => $pay_arr['username'],//用户名
                'currency' => $currency,//幣種編號
            ];
            $sign = $this->generateSign($params, $this->pay_set['sign_key']);
            $header = array(
                "Content-Type:application/json",
                "X-Processing-Key:" . $this->pay_set['public_key'],
                "X-Processing-Signature:" . $sign,
            );
            $params = json_encode($params);
            $url = $this->pay_set['url'];
            Log::write(['Submit_create', "Alphapo", $url, $params, $header], "payment");
            $res = PayRequest::curlS($this->pay_set['url'], $params, $header);
            $res = json_decode($res, true);
            Log::write(['Return_create', "Alphapo", $res], "payment");
            if (empty($res) || isset($res['errors'])) {
                throw new \Exception('支付信息错误', 0);
            }
            if (!isset($res['data']['address'])) {
                throw new \Exception('支付信息错误', 0);
            }
            $address = $res['data']['address'];
            //生成二维码
            $qr_code = "https://api.qrserver.com/v1/create-qr-code/?data={$address}&size=200x200";
            if($res['data']['currency'] == 'BCH'){
                $address = ltrim($address, 'bitcoincash:');
            }
            $href = "bitcoin:{$address}";
            //a标签 href
            //href="bitcoin:3AFaCnqriLNxj15kqtp5Pxn8puHfuqbX7W"

            Db::name('user_address')->insert(['uid' => $pay_arr['user_id'], 'pay_id' => $pay_arr['pay_id'], 'address' => $address, 'qr_code' => $qr_code, 'time' => time()]);
        }
//        $ApiRedis = new ApiRedis();
//        $key = $pay_arr['user_id'] . $address;
//        $ApiRedis->set($key, $address, 3600);
//        $sys = $ApiRedis->systemInfo();
        $pay_info['pay_url'] = $href; //点击唤起交易所app
        $pay_info['is_qrcode'] = 1;
        $pay_info['qrcode_url'] = $qr_code;
        $pay_info['address'] = $address;
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
//        $info = Db::name('user_address')->where(['uid' => $pay_arr['user_id'], 'pay_id' => $pay_arr['pay_id']])->find();
        $currency =  $this->pay_set['currency'];
        if($currency != $pay_arr['bank_code']){
            throw new \Exception('提现货币与出款货币不符，请检查', 0);
        }
        $rate = $this->get_rate();
        $pay_rate = 1;
        if(isset($rate[$currency])){
            $pay_rate = $rate[$currency];
        }
        $amount = $this->truncateFloat(($pay_arr['amount'] * $pay_rate), 6);
        if($this->pay_set['fees'] > 0){
            $amount = $this->truncateFloat(($amount * ((100 - $this->pay_set['fees']) / 100) ), 6);
        }
        $params = [
            'foreign_id' => $pay_arr['order_sn'],//订单号
            'amount' => $amount,//提现金额
            'currency' => $currency,//幣種編號
            'address' => $pay_arr['bank_number'],//收款地址
        ];
        $sign = $this->generateSign($params, $this->pay_set['sign_key']);
        $header = array(
            "Content-Type:application/json",
            "X-Processing-Key:" . $this->pay_set['public_key'],
            "X-Processing-Signature:" . $sign,
        );
        $params = json_encode($params);
        $url = $this->pay_set['with_url'];
        Log::write(['Submit_withdrawal', "Alphapo", $url, $params,$pay_rate, $header], "withdrawal");
        $res = PayRequest::curlS($url, $params, $header);
        $res = json_decode($res, true);
        Log::write(['Return_withdrawal', "Alphapo", $res], "withdrawal");
        if (empty($res) || isset($res['errors'])) {
            foreach ($res['errors'] as $k => $error) {
                $msg = $res['errors'][$k];
            }
            throw new \Exception("渠道返回:" . $msg, 0);
        }
        $w = [];
        $w[] = ['order_sn', '=', $pay_arr['order_sn']];
        UserWithdrawalModel::where($w)->update(['submit_third_price' => $amount]);
        return '';
    }

    private function generateSign($params, $signKey)
    {
//        ksort($params);
        $string_to_hash = json_encode($params);
        return hash_hmac('sha512', $string_to_hash, $signKey);
    }


    //异步回调支付
    public function notify($data)
    {
        if ($data['status'] != 'confirmed') {
//        if ($data['status'] == 'failed' || $data['status'] == 'cancelled' || $data['status'] == 'cancelled') {
            return "status failed";
        }
        $user_name = $data['crypto_address']['foreign_id'] ?? '';
        $currency_code = $data['crypto_address']['currency'] ?? '';
        $address = $data['crypto_address']['address'] ?? '';
        $amount = $data['currency_received']['amount'] ?? '';

        if(!isset($user_name) || !isset($address)){
            return "user does not exist";
        }
        $uid = Db::name('user_address')->where(['address' => $address])->value('uid');
        $user_id = UserModel::where(['username' => $user_name])->value('id');
        if (!$uid || !$user_id || ($user_id != $uid)) {
            return "user does not exist";
        }
//        $tx_id = $data['id'] ?? '';
        $tx_id = $data['transactions'][0]['txid'] ?? '';
        if(!$tx_id){
            return "txid failed";
        }
        $order = UserOfflineModel::where(['othr_number' => $tx_id])->value('id');
        if ($order) {
            return "Order duplication";
        }
        $channel = Request::param('channel');
        $currency_arr = [
            'BCH' => 'BCH',
            'BTC' => 'BTC',
            'TRX' => 'TRX',
            'LTC' => 'LTC',
            'ETH' => 'ETH',
            'DOGE' => 'DOGE',
            'USDTE' => 'USDT-ERC20',
//            'USDT-ERC20' => 'USDTE',
            'USDTT' => 'USDT-TRC20',
//            'USDT-TRC20' => 'USDTT',
        ];
        $pay_currency = $currency_arr[$currency_code] ?? 'USDT-TRC20';
        $pay_type = PaySetModel::where(['pay_name' => $pay_currency])->find();
//        if(!isset($pay_type['id'])){
//            $pay_type = PaySetModel::get_pay(['id' => $channel]);
//        }
        $currency = Util::getPayCurrency($pay_type);

        $rate = $this->get_rate();
        $pay_rate = 1;
        if(isset($rate[$currency_code])){
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
            $pay_rate = $rate[$currency_code];
            if(isset($rate_arr[$currency_code]) && $rate_arr[$currency_code] > 0){
                $pay_rate = $pay_rate + ($pay_rate * $rate_arr[$currency_code]);
            }
        }
        $add['add_time'] = time();
        $add['type'] = 1;
        $add['channel'] = 2;
        $add['uid'] = $uid;
        $add['apply_price'] = $amount;
        $add['pay_type'] = $pay_type['pay_sys_sign'];
        $add['pay_id'] = $pay_type['id'];
        $add['pay_currency'] = $currency_code; //充值币种
        $add['arrival_currency'] = $currency['arrival_currency']; //到账币种
        $add['exchange_rate'] = 1 / $pay_rate; //汇率
        $add['pay_ip'] = Util::get_ip();
        $add['lebal'] = UserModel::where('id', $uid)->value('lebal');
        $add['order_sn'] = $uid . date("YmdHi");
        $res = UserOfflineModel::insertGetId($add);
        if (!$res) {
            return "system error";
        }
        $rsps['order_sn'] = $add['order_sn'];
        $rsps['trade_sn'] = $tx_id;
        $rsps['result'] = "ok";
        $rsps['money'] = $amount;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if ($data['status'] == 'failed' || $data['status'] == 'cancelled') {
            $rsps['status'] = 2;
//            return "status failed";
        }
        $tx_id = $data['transactions'][0]['txid'] ?? '';
        if(!$tx_id){
            $rsps['status'] = 2;
//            return "txid failed";
        }
        $order = UserWithdrawalModel::where(['pay_order_sn' => $tx_id])->value('id');
        if ($order) {
            $rsps['status'] = 2;
//            return "Order duplication";
        }

        $order_sn = $data['foreign_id'] ?? '';
        if(!$order_sn){
            $rsps['status'] = 2;
//            return "foreign failed";
        }
        $order = UserWithdrawalModel::where(['order_sn' => $order_sn])->value('id');
        if(!$order){
            $rsps['status'] = 2;
//            return "foreign failed";
        }
        $rsps['order_sn'] = $order_sn;
        $rsps['othr_number'] = $tx_id;
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }

    public  function  get_rate($type = 1){
        $ApiRedis = new ApiRedis();
        $key = "AlphapoPayRate";
        $rate = $ApiRedis->get($key);
        if(empty($rate)){
            $params = [
                'currency_from' => 'USDTE',
            ];
            $sign = $this->generateSign($params, $this->pay_set['sign_key']);
            $header = array(
                "Content-Type:application/json",
                "X-Processing-Key:" . $this->pay_set['public_key'],
                "X-Processing-Signature:" . $sign,
            );
            $params = json_encode($params);
            $url = "https://app.alphapo.net/api/v2/currencies/rates";
//            Log::write(['get_rate', "Alphapo", $url, $params, $header], "payment");
            $res = PayRequest::curlS($url, $params, $header);
            $res = json_decode($res, true);
            Log::write(['get_rate', "Alphapo", $res], "payment");
            $rate = [];
            if(isset($res['data']) && count($res['data']) > 0){
                foreach ($res['data'] as $k => $v){
                    $rate_to = $v['rate_to'];
                    $rate[$v['currency_to']['currency']] = sprintf('%.8f', $rate_to);
                }
            }else{
                if($type == 1){
                    throw new \Exception('支付信息错误', 0);
                }
                return 'fail';
            }
            Log::write(['return_rate', "Alphapo", $rate], "payment");
            $ApiRedis->set($key, $rate, 600);
        }
        return $rate;
    }

    function truncateFloat($number, $decimals = 6) {
        // 将数字转换为字符串
        $numberStr = (string) $number;

        // 查找小数点的位置
        $decimalPos = strpos($numberStr, '.');

        // 如果小数点存在
        if ($decimalPos !== false) {
            // 获取小数点后的部分
            $decimalPart = substr($numberStr, $decimalPos + 1);

            // 保留指定的小数位数
            $truncatedDecimalPart = substr($decimalPart, 0, $decimals);

            // 重新组装结果
            $result = substr($numberStr, 0, $decimalPos + 1) . $truncatedDecimalPart;

            // 返回处理后的结果
            return (float) $result;
        }
        // 如果没有小数点，直接返回原数值
        return $number;
    }
}