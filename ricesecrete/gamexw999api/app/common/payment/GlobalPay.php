<?php

namespace app\common\payment;

use think\facade\Log;

class GlobalPay
{
    private $merchant = "";
    private $sign_key = "";
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant = $pay_set['merchantUUID'];
        $this->sign_key = $pay_set['sign_key'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchantUUID'] = $this->merchant;//商户号
        $params['price'] = $pay_arr['apply_price']; // 订单金额:
        $params['channelCode'] = "104";
        $params['merchantOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['fullName'] = $pay_arr['username'];
        $params['email'] = $pay_arr['username'] . "@test.in";
        $params['phone'] = $pay_arr['phone'];
        $params['notifyUrl'] = $this->domain . '/notify/GlobalPay';// 异步通知地址
        $params['returnUrl'] = $pay_arr['call_domain'];
        $params['errorReturnUrl'] = $pay_arr['call_domain'];
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        Log::write(['Submit_create', "GlobalPay", $params], "payment");
        $url = 'http://client.quanqiupay.cc/api/gateway/generateOrderV2';
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_create', "GlobalPay", $res], "payment");
        $res = json_decode($res, true);
        if (!isset($res['data']['payUrl'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['payUrl'];
        return $pay_info;
    }


    //获取提现信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchantUUID'] = $this->merchant;//商户号
        $params['amount'] = $pay_arr['amount']; // 订单金额:
        $params['channelCode'] = "301";
        $params['productType'] = "ind.bankcard.payout";
        $params['currency'] = "INR";
        $params['approveType'] = 2;
        $params['merchantOrderNo'] = $pay_arr['order_sn']; // 商户订单号        
        $params['notifyUrl'] = $this->domain . '/withdrawal/notify/GlobalPay';// 异步通知地址
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        $params["extInfo"] = [
            "userName" => $pay_arr['account_name'],
            "IFSC" => $pay_arr['ifsc'],
            "bankCardNumber" => $pay_arr['bank_number'],
            "phone" => $pay_arr['phone'],
            "email" => $pay_arr['username'] . "@test.in",
        ];
        Log::write(['Submit_withdrawal', "GlobalPay", $params], "withdrawal");
        $url = 'http://client.quanqiupay.cc/api/gateway/walletWithdrawAPIV2';
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_withdrawal', "GlobalPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (!isset($res['sysOrderCode'])) {
            throw new \Exception('代付信息错误', 0);
        }
        return $res['sysOrderCode'];
    }

    // 签名验证
    private function makeSign($data, $key)
    {
        ksort($data);
        $buff = '';
        foreach ($data as $k => $v) {
            $buff .= $k . "=" . $v . "&";
        }
        $buff = substr($buff, 0, strlen($buff) - 1);
        $strEncode = base64_encode(openssl_encrypt($buff, 'AES-256-ECB', $key, OPENSSL_RAW_DATA));
        return $strEncode;
    }


    //异步回调处理
    public function notify($post_data)
    {
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign'] ?? '';
        unset($notify_data['sign']);//不参与签名
        if ($notify_data['status'] != 2) {
            return "faile";
        }
        $sign_key = $this->sign_key;
        $sign_str = $this->makeSign($notify_data, $this->sign_key);
        if ($sign_str != $notify_sign) {
            return "sign error";
        }

        $rsps['order_sn'] = $notify_data['merchantOrderCode'];
        $rsps['trade_sn'] = $notify_data['sysOrderCode'];
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
        if ($notify_data['status'] != 4) {
            $rsps['status'] = 2;
        }
        $sign_key = $this->sign_key;
        $sign_str = $this->makeSign($notify_data, $this->sign_key);
        if ($sign_str != $notify_sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $notify_data['merchantOrderCode'];
        $rsps['othr_number'] = $notify_data['sysOrderCode'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }


}