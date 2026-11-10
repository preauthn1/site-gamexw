<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class Okopay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mchtId'] = $this->pay_set['merchant_id'];//商户号
        $params['appId'] = $this->pay_set['appId'];//产品号
        $params['requestTime'] = date('Y-m-d H:i:s',time());//提交时间
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['signType'] = 'MD5';
        $params['transAmt'] = $pay_arr['apply_price']; // 订单金额
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['subject'] = 'this is a order'; // 描述
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "Okopay", $params], "payment");
        $data = json_encode($params);
        $header = array(
            'Content-Type: application/json'
        );
        $url = $this->pay_set['url'] . '/trans/payIn';
        $res = PayRequest::curlS($url, $data,$header);
        Log::write(['Return_create', "Okopay", $res], "payment");
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
        $params['mchtId'] = $this->pay_set['merchant_id'];//商户号
        $params['appId'] = $this->pay_set['appId'];//产品号
        $params['requestTime'] = date('Y-m-d H:i:s',time());//提交时间
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['signType'] = 'MD5';
        $params['transAmt'] = $pay_arr['amount']; // 订单金额
        $params['bankCode'] = $pay_arr['bank_code']; // 银行编码
        $params['accountName'] = $pay_arr['account_name'];// 收款人姓名
        $params['accountNo'] = $pay_arr['bank_number'];// 银行账号
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['subject'] = 'this is a withdrawal_order'; // 描述
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        $url = $this->pay_set['url'] . '/trans/payOut';
        Log::write(['Submit_withdrawal', "Okopay", $params], "withdrawal");
        $data = json_encode($params);
        $header = array(
            'Content-Type: application/json'
        );
        $res = PayRequest::curlS($url, $data,$header);
        Log::write(['Return_withdrawal', "Okopay", $res], "withdrawal");
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

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
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

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
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
