<?php

namespace app\common\payment;

use app\common\payment\PayRequest;
use think\facade\Log;

class Paythere
{
    private $merchant = "";
    private $sign_key = "";
    private $method = "";
    private $pay_url = '';
    private $withdrawal_url = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->pay_url = $pay_set['url'];
        $this->withdrawal_url = $pay_set['withdrawal_url'];
        $this->merchant = $pay_set['merchantNo'];
        $this->sign_key = $pay_set['sign_key'];
        $this->method = $pay_set['method'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchantNo'] = $this->merchant;//商户号
        $params['method'] = $this->method;//代收方式 ，目前巴西固定值：BAR01
        $params['merchantOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['payAmount'] = $pay_arr['apply_price']; // 订单金额:
        $params['mobile'] = empty($pay_arr['phone'])?'123456789':$pay_arr['phone'];//付款人手机号
        $params['name'] = $pay_arr['username'];//付款人姓名
        $params['email'] = $pay_arr['username'] . "@test.in";//付款人邮箱
        $params['description'] = "recharge";//
        $params['notifyUrl'] = $pay_arr['notify_url'];// 异步通知地址
        $params['returnUrl'] = $pay_arr['call_domain'];
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        Log::write(['Submit_create', "Paythere", $params], "payment");
        $url = $this->pay_url;
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json; charset=utf-8'
        );
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_create', "Paythere", $res], "payment");
        $res = json_decode($res, true);
        if (!isset($res['data']['paymentInfo'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['paymentInfo'];
        return $pay_info;
    }


    //获取提现信息
    public function create_withdrawal($pay_arr)
    {
        Log::write(['Submit_withdrawal', "Paythere", $pay_arr], "withdrawal");
        $params['merchantNo'] = $this->merchant;//商户号
        $params['merchantOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['description'] = "withdrawal";//
        $params['payAmount'] = $pay_arr['amount']; // 订单金额:
        $params['mobile'] = empty($pay_arr['phone'])?'123456789':$pay_arr['phone'];//付款人手机号
        $params['email'] = $pay_arr['username'] . "@test.in";//付款人邮箱
        $params['bankNumber'] = $pay_arr['bank_number'];//收款银行卡号
        $params['bankCode'] = strtoupper($pay_arr['bankName']);//PIX账号类型（CPF、CNPJ、PHONE、EMAIL、EVP）
        $params['accountHoldName'] = $pay_arr['account_name'];//收款人姓名
        $params['notifyUrl'] = $pay_arr['notify_url'];// 异步通知地址
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal', "Paythere", $params], "withdrawal");
        $url = $this->withdrawal_url;
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json; charset=utf-8'
        );
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_withdrawal', "Paythere", $res], "withdrawal");
        $res = json_decode($res, true);
        if (!isset($res['data']['platOrderNo'])) {
            throw new \Exception('代付信息错误', 0);
        }
        return $res['data']['platOrderNo'];
    }

    // 签名验证
    private function makeSign($data, $signKey)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        return md5(md5($str) . $signKey);
    }


    //异步回调处理
    public function notify($post_data)
    {
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign'] ?? '';
        unset($notify_data['sign']);//不参与签名
        if ($notify_data['orderStatus'] == 'FAILED' || $notify_data['orderStatus'] == 'CREATED') {
            return "faile";
        }
        $sign_str = $this->makeSign($notify_data, $this->sign_key);
        if ($sign_str != $notify_sign) {
            return "sign error";
        }

        $rsps['order_sn'] = $notify_data['merchantOrderNo'];
        $rsps['trade_sn'] = $notify_data['platOrderNo'];
        $rsps['result'] = "success";
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data)
    {
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign'] ?? '';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if ($notify_data['orderStatus'] != 'SUCCESS') {
            $rsps['status'] = 2;
        }
        $sign_str = $this->makeSign($notify_data, $this->sign_key);
        if ($sign_str != $notify_sign) {
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $notify_data['merchantOrderNo'];
        $rsps['othr_number'] = $notify_data['platOrderNo'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }


}