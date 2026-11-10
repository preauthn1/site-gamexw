<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class ShPay
{
    private $merchant_id = "";
    private $appId = "";
    private $sign_key = "";
    private $gateway = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->appId = $pay_set['appId'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mchtId'] = $this->merchant_id;//商户号
        $params['appId'] = $this->appId;//产品号
        $params['requestTime'] = date('Y-m-d H:i:s',time());//提交时间
        $params['notifyUrl'] = $this->domain . '/notify/ShPay';// 异步通知地址
        $params['signType'] = 'MD5';
        $params['transAmt'] = $pay_arr['apply_price']; // 订单金额
        $params['name'] = $pay_arr['username'];// 付款人姓名
        $params['mobile'] = $pay_arr['phone'] ?? '15698456131'; // 手机号
        $params['email'] = $pay_arr['username'] . "@0s.com";// 邮箱
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['subject'] = 'this is a order'; // 描述
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "ShPay", $params], "payment");
        $data = json_encode($params);
        $header = array(
            'Content-Type: application/json'
        );
        $url = $this->gateway . '/trans/payIn';
        $res = PayRequest::curlS($url, $data,$header);
        Log::write(['Return_create', "ShPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !$res['success']) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['result']['link'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['result']['link'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['mchtId'] = $this->merchant_id;//商户号
        $params['appId'] = $this->appId;//产品号
        $params['requestTime'] = date('Y-m-d H:i:s',time());//提交时间
        $params['notifyUrl'] = $this->domain . '/withdrawal/notify/ShPay';// 异步通知地址
        $params['signType'] = 'MD5';
        $params['transAmt'] = $pay_arr['amount']; // 订单金额
        $params['accountName'] = $pay_arr['account_name'];// 收款人姓名
        $params['accountNo'] = $pay_arr['bank_number'];// 银行账号
        $params['bankCode'] = $pay_arr['bank_code'];// 银行账号
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['subject'] = 'this is a withdrawal_order'; // 描述
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        $url = $this->gateway . '/trans/payOut';
        Log::write(['Submit_withdrawal', "ShPay", $params], "withdrawal");
        $data = json_encode($params);
        $header = array(
            'Content-Type: application/json'
        );
        $res = PayRequest::curlS($url, $data,$header);
        Log::write(['Return_withdrawal', "ShPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !$res['success']) {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['result']['transNo'];
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
        $str = trim($str,'&');
        $str .= $signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['transStatus'] != 'SUCCESS') {
            return "OK";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "OK";
        }
        $rsps['order_sn'] = $data['outTradeNo'];
        $rsps['trade_sn'] = $data['transNo'];
        $rsps['result'] = "OK";
        $rsps['money'] = $data['transAmt'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        $rsps['status'] = 1;
        if ($data['transStatus'] != 'SUCCESS') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['outTradeNo'];
        $rsps['othr_number'] = $data['transNo'];
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }
}
