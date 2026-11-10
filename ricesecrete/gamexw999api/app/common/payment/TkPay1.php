<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class TkPay1
{
    private $merchant_id = "";
    private $sign_key = "";
    private $pay_type = '';
    private $gateway = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->pay_type = $pay_set['pay_type'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchant_id'] = $this->merchant_id;//商户号
        $params['pay_type'] = $this->pay_type;//支付通道
        $params['order_id'] = $pay_arr['order_sn']; // 商户订单号:
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['redirect_url'] = $pay_arr['call_domain']; // 重定向
        $params['notify_url'] = $this->domain . '/notify/TkPay1';// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "TkPay1", $params], "payment");
        $url = $this->gateway . '/mexico/recharge';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_create', "TkPay1", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != '0') {
            throw new \Exception($res['message'], 0);
        }
        $pay_info['pay_url'] = $res['data']['pay_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchant_id'] = $this->merchant_id;//商户号
        $params['order_id'] = $pay_arr['order_sn']; // 商户订单号:
        $params['amount'] = $pay_arr['amount']; // 订单金额:
        $params['bank_code'] = $pay_arr['bank_code'];//银行code
        $params['receive_name'] = $pay_arr['account_name'];// 收款人姓名
        $params['receive_account'] = $pay_arr['bank_number'];// 银行账号
        $params['notify_url'] = $this->domain . '/withdrawal/notify/TkPay1';// 异步通知地址
        $url = $this->gateway . '/api/mexico/withdrawal';
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal', "TkPay1", $params], "withdrawal");
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal', "TkPay1", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != '0') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "key=" . $signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['order_status'] != '1') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_id'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['data']['sign']) ? $data['data']['sign'] : '';
        unset($data['data']['sign']);
        $rsps['status'] = 1;
        if ($data['status'] != '0') {
            $rsps['status'] = 2;
        }
        if(!isset($data['data']) || $data['data']['transfer_status'] == '3'){
            $rsps['status'] = 2;
        }
        $sign_str = $this->generateSign($data['data'], $this->sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['data']['order_id'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}
