<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class BetcatPay
{
    private $appId = "";
    private $withdrawal_appId = "";
    private $currency = "";
    private $sign_key = "";
    private $withdrawal_sign_key = "";
    private $gateway = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->appId = $pay_set['appId'];
        $this->withdrawal_appId = $pay_set['withdrawal_appId'];
        $this->currency = $pay_set['currency'];
        $this->sign_key = $pay_set['sign_key'];
        $this->withdrawal_sign_key = $pay_set['withdrawal_sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['appId'] = $this->appId;//商户号
        $params['merOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['currency'] = $this->currency;//金额币种
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['returnUrl'] = $pay_arr['call_domain']; // 重定向
        $params['notifyUrl'] = $this->domain . '/notify/BetcatPay';// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        $url = $this->gateway . '/api/v1/payment/order/create';
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        Log::write(['Submit_create', "BetcatPay", $req], "payment");
        $res = PayRequest::curlS($url,$req,$header);
        Log::write(['Return_create', "BetcatPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['params']['url'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['params']['url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['appId'] = $this->withdrawal_appId;//商户号
        $params['merOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['currency'] = $this->currency;//金额币种
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['notifyUrl'] = $this->domain . '/withdrawal/notify/BetcatPay';// 异步通知地址
        if ($pay_arr['bankName'] == 'Phone'){
            $pixKey = $pay_arr['bank_number'];
            if (str_starts_with($pixKey, "+")) {
                $pixKey = substr($pixKey,1);
            }
            if (strlen($pixKey)>11 && str_starts_with($pixKey,"55")){
                $pixKey = substr($pixKey,2);
            }
            $pay_arr['bank_number'] = "+55".$pixKey;
        }
        $params['extra'] = json_encode([
            'bankCode'=>strtoupper($pay_arr['bankName']),
            'accountNo'=>$pay_arr['bank_number'],
            'accountName'=>$pay_arr['account_name'],
        ]);
        $params['sign'] = $this->generateSign($params, $this->withdrawal_sign_key);
        $url = $this->gateway . '/api/v1/payout/order/create';
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        Log::write(['Submit_withdrawal', "BetcatPay", $req], "withdrawal");
        $res = PayRequest::curlS($url,$req,$header);
        Log::write(['Return_withdrawal', "BetcatPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != '0') {
            throw new \Exception("渠道返回:" . $res['error'], 0);
        }
        return $res['data']['orderNo'];
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
        $str .= 'key='.$signKey;
        return hash('sha256', $str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['orderStatus'] != '1') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['merOrderNo'];
        $rsps['trade_sn'] = $data['orderNo'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        $rsps['status'] = 1;
        if ($data['orderStatus'] != '2') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->withdrawal_sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['merOrderNo'];
        $rsps['othr_number'] = $data['orderNo'];
        $rsps['msg'] = $data['message'] ??'';
        $rsps['result'] = "success";
        return $rsps;
    }
}
