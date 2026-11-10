<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class FluPay
{
    private $merchant_id = "";
    private $sign_key = "";
    private $pay_channel_code = '';
    private $gateway = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->pay_channel_code = $pay_set['pay_channel_code'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['pay_memberid'] = $this->merchant_id;//商户号
        $params['pay_orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['pay_applydate'] = date('Y-m-d H:i:s',time());//提交时间
        $params['pay_channel_code'] = $this->pay_channel_code;//支付通道
        $params['pay_notifyurl'] = $this->domain . '/notify/FluPay';// 异步通知地址
        $params['pay_callbackurl'] = $pay_arr['call_domain']; // 重定向
        $params['pay_email'] = $pay_arr['username'] . "@0s.com";// 邮箱
        $params['pay_user_name'] = $pay_arr['username'];// 姓名
        $params['pay_user_phone'] = isset($pay_arr['phone']) ? $pay_arr['phone'] : '15698456131'; // 手机号
        $params['pay_amount'] = $pay_arr['apply_price']; // 订单金额
        $params['pay_md5sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "FluPay", $params], "payment");
        $url = $this->gateway . '/Pay_Flutterwave_Index.html';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_create', "FluPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != 'success') {
            throw new \Exception($res['msg'], 0);
        }
        $pay_info['pay_url'] = $res['data'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['pay_memberid'] = $this->merchant_id;//商户号
        $params['pay_orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['pay_amount'] = $pay_arr['amount']; // 订单金额
        $params['pay_notifyurl'] = $this->domain . '/withdrawal/notify/FluPay';// 异步通知地址
        $params['pay_account_bank'] = $pay_arr['bank_code'];// 收款银行编码
        $params['pay_account_number'] = $pay_arr['bank_number'];// 银行账号
        $params['pay_md5sign'] = $this->generateSign($params, $this->sign_key);
        $url = $this->gateway . '/Payment_Flutterwave_add.html';
        Log::write(['Submit_withdrawal', "FluPay", $params], "withdrawal");
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal', "FluPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != 'success') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value && 'attach' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= 'key='.$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['returncode'] != '00') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderid'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if(isset($data['msg'])){unset($data['msg']);}
        $rsps['status'] = 1;
        if ($data['returncode'] != '00') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderid'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }
}
