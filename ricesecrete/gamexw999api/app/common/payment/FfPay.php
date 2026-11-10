<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class FfPay
{
    private $merchant_id = "";
    private $sign_key = "";
    private $bank_code = '';
    private $gateway = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->bank_code = $pay_set['bank_code'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchant_id'] = $this->merchant_id;//商户号
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['order_no'] = $pay_arr['order_sn']; // 订单号
        $params['submit_time'] = date('Y-m-d H:i:s',time());//时间
        $params['bank_code'] = $this->bank_code; // 支付通道
        $params['notify_url'] = $this->domain . '/notify/FfPay';// 异步通知地址
        $params['return_url'] = $pay_arr['call_domain']; // 重定向
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "FfPay", $params], "payment");
        $url = $this->gateway . '/pay/index';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_create', "FfPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != 1) {
            throw new \Exception($res['msg'], 0);
        }
        $pay_info['pay_url'] = $res['payurl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['mchid'] = $this->merchant_id;//商户号
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['money'] = $pay_arr['amount']; // 订单金额
        $params['bankname'] = $pay_arr['bankName'];//银行名称
        $params['accountname'] = $pay_arr['account_name'];// 收款人姓名
        $params['cardnumber'] = $pay_arr['bank_number'];// 银行账号
        $params['notifyurl'] = $this->domain . '/withdrawal/notify/FfPay';// 异步通知地址
        $url = $this->gateway . '/dfpay/add';
        $params['pay_md5sign'] = $this->generateSign($params, $this->sign_key,1);
        Log::write(['Submit_withdrawal', "FfPay", $params], "withdrawal");
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal', "FfPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != 'success') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['transaction_id'];
    }

    private function generateSign($data, $signKey,$is_withdrawal = 0)
    {
        ksort($data);
        $arr = [];
        foreach ($data as $key => $val) {
            if ($is_withdrawal == 0 && 'sign' != $key && '' != $val) {
                $arr[] = $key . "=" . $val;
            }else if ($is_withdrawal == 1 && 'sign' != $key && '' != $val && $key != 'notifyurl') {
                $arr[] = $key . "=" . $val;
            }
        }
        $md5str = implode("&", $arr);
        return md5(md5($signKey . $md5str .  $signKey));
    }

    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['return_code'] != '00') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_no'];
        $rsps['trade_sn'] = $data['transaction_id'];
        $rsps['result'] = "OK";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        $rsps['status'] = 1;
        if ($data['status'] != 'ok') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }
}
