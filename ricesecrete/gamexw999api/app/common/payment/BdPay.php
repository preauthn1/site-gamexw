<?php

namespace app\common\payment;

use think\facade\Log;
use think\facade\Request;

class BdPay
{
    private $domain = "";
    private $pay_set = [];

    /**
     *
     * UniPay constructor.
     * @param $pay_set
     * @param $domain
     */
    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params = array(
            "transaction_type" => "income", //income(收入)/expense(支出
            "amount" => $pay_arr['apply_price'], // 订单金额,
            "currency" => $this->pay_set['currency'],//cny(人民币)/usd(美元)/thb(泰铢)/vnd(越南盾)/php(菲律宾比索)/sgd(新加坡元)/idr(印尼盾)/bdt(孟加拉国塔卡)
            "payment" => $this->pay_set['payment'],//支付类型 gcash USDT-TR
            "merchant_order_id" => $pay_arr['order_sn'],//订单编号
            "merchant_uid" => $this->pay_set['merchant_id'],//商户号
            "redirect_url" => $pay_arr['call_domain'],
            "notify_url" => $pay_arr['notify_url'],
            "response_type" => "json"
        );
        if (isset($this->pay_set['bank_name'])) {
            $params["to_bank_name"] = $this->pay_set['bank_name'];//银行名称 bkash或是nagad
        }
        ksort($params);
        $header = [
            'OC-Key: ' . $this->pay_set['private_key'],
            'Content-Type: application/json',
            'Oc-Signature: ' . $this->generateSign($params, $this->pay_set['private_secret']),
        ];
        Log::write(['Submit_create', "UniPay", $params, $header], "payment");
        //网页
        $res = PayRequest::curlS($this->pay_set['url'], json_encode($params), $header);
        Log::write(['Return_create', "UniPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['result']) || $res['result'] != 'success' || !isset($res['data']['payment_url'])) {
            throw new \Exception($res['info'] ?? '支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['payment_url'];
        $pay_info['othr_number'] = $res['data']['payment']['payment_uid'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params = array(
            "transaction_type" => "expense", //income(收入)/expense(支出
            "amount" => $pay_arr['amount'], // 订单金额,
            "currency" => $this->pay_set['currency'],//cny(人民币)/usd(美元)/thb(泰铢)/vnd(越南盾)/php(菲律宾比索)/sgd(新加坡元)/idr(印尼盾)/bdt(孟加拉国塔卡)
            "payment" => $this->pay_set['payment'],//支付类型 gcash USDT-TR
            "merchant_order_id" => $pay_arr['order_sn'],//订单编号
            "merchant_uid" => $this->pay_set['merchant_id'],//商户号
            "to_bank_name" => $pay_arr['bank_code'],//收款银行编号
            "to_name" => $pay_arr['username'],//收款人名称
            "to" => $pay_arr['bank_number'],//收款人账号
            "notify_url" => $pay_arr['notify_url'],
            "response_type" => "json"
        );
        $header = [
            'OC-Key: ' . $this->pay_set['private_key'],
            'Content-Type: application/json',
            'Oc-Signature: ' . $this->generateSign($params, $this->pay_set['private_secret']),
        ];

        Log::write(['Submit_withdrawal', "UniPay", $params, $header], "payment");
        //网页
        $res = PayRequest::curlS($this->pay_set['url'], json_encode($params), $header);
        Log::write(['Return_withdrawal', "UniPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['result']) || $res['result'] != 'success' || !isset($res['data']['payment'])) {
            throw new \Exception("渠道返回:" . $res['data'] ?? '代付信息错误', 0);
        }
        return $res['data']['payment']['payment_uid'];
    }

    private function generateSign($params, $signKey)
    {
        // 对参数按键名排序
        ksort($params);

        // 手动构建未编码的查询字符串
        $data_string = '';
        foreach ($params as $key => $value) { // 修正了这里，从 $data 更改为 $params
            $data_string .= $key . '=' . $value . '&';
        }
        // 移除最后一个多余的&
        $data_string = rtrim($data_string, '&');

        // 使用密钥进行HMAC-SHA256加密，返回十六进制字符串
        return hash_hmac('sha256', $data_string, $signKey);
    }


    //异步回调支付
    public function notify($data)
    {
        $headers = Request::header();
        Log::write(['Submit_header', "UniPay", [], $headers], "payment");
        if (!isset($headers['oc-signature'])) {
            return "faile";
        }
        $signature = $headers['oc-signature'];
        if ($data['payment_status'] != 'completed') {
            return "faile";
        }
        $sign = isset($signature) ? $signature : '';
        $sign_str = $this->generateSign($data, $this->pay_set['private_secret']);
        if ($sign_str != $sign) {
            return "sign error";
        }

        $rsps['order_sn'] = $data['merchant_order_id'];
        $rsps['trade_sn'] = $data['order_uid'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ?? 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $headers = Request::header();
        if (!isset($headers['oc-signature'])) {
            return "faile";
        }
        $signature = $headers['oc-signature'];
        $sign = isset($signature) ? $signature : '';
        $sign_str = $this->generateSign($data, $this->pay_set['private_secret']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['status'] = 1;
        //不是支付成功、支付失败、支付拒绝状态是则返回错误
        if ( !in_array($data['payment_status'], ['completed', 'failed', 'reject']) ) {
            return "faile";
        }
        if($data['payment_status'] != 'completed'){
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $data['merchant_order_id'];
        $rsps['othr_number'] = $data['order_uid'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}