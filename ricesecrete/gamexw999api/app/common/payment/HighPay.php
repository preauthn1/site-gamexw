<?php

namespace app\common\payment;

use think\facade\Log;

class HighPay
{
    private $domain = "";
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params = array(
            "mchId" => $this->pay_set['mchId'],//商户号
            "signType" => "md5",//商户号
            "mchOrderNo" => $pay_arr['order_sn'],//订单编号
            "amount" => floatval($pay_arr['apply_price']) * 100, // 订单金额,
            "channelType" => $this->pay_set['channelType'],//imps
            "terminalType" => "ALL",//支付类型 PC,MOBILE,ALL
            "notifyUrl" => $pay_arr['notify_url'],
            "rand" => time() . rand(1000, 9999)
        );
        $params['sign'] = $this->generateSign($params, $this->pay_set['private_key']);
        Log::write(['Submit_create', "HighPay", $params, []], "payment");
        //网页
        $res = PayRequest::curlS($this->pay_set['url'], $params, []);
        Log::write(['Return_create', "HighPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != 0 || !isset($res['data']['payUrl'])) {
            throw new \Exception($res['info'] ?? '支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['payUrl'];
        $pay_info['othr_number'] = $res['data']['orderId'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $b_code = explode('-',$pay_arr['bank_code']);
        if(isset($b_code[1])){
            $bank_codes = $b_code[0] ??'';
            $bank_name = $b_code[1] ??'';
        }else{
            $bank_codes = $this->pay_set['bank_code'] ?? $pay_arr['bank_code'];
            $bank_name = $pay_arr['bankName'];
        }
        $params = array(
            "mchId" => $this->pay_set['mchId'],//商户号
            "signType" => "md5",//商户号
            "mchOrderNo" => $pay_arr['order_sn'],//订单编号
            "amount" => floatval($pay_arr['amount']) * 100, // 订单金额,
            "channelType" => $this->pay_set['channelType'],//imps
            "terminalType" => "ALL",//支付类型 PC,MOBILE,ALL
            "notifyUrl" => $pay_arr['notify_url'],
            "bankName" => $bank_name,//银行名称
            "bankCode" => $bank_codes,//银行代码
            "cardName" => $pay_arr['username'],//持卡人姓名
            "cardNo" => $pay_arr['bank_number'],//卡号
            "rand" => time() . rand(1000, 9999)
        );
        $params['sign'] = $this->generateSign($params, $this->pay_set['private_key']);

        Log::write(['Submit_withdrawal', "HighPay", $params, []], "payment");
        //网页
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params, []);
        Log::write(['Return_withdrawal', "HighPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != 0) {
            throw new \Exception("渠道返回:" . $res['msg'] ?? '代付信息错误', 0);
        }
        return '';
    }

    private function generateSign($params, $signKey)
    {
        // 对参数按键名排序
        ksort($params);
        $str = '';
        foreach ($params as $key => $value) {
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
        if ($data['status'] != 'PAID') {
            return "faile";
        }
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $sign_str = $this->generateSign($data, $this->pay_set['private_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }

        $rsps['order_sn'] = $data['mchOrderNo'];
        $rsps['trade_sn'] = $data['orderId'];
        $rsps['result'] = "success";
        $amount = $data['amount'] ?? 0;
        $rsps['money'] = round($amount / 100, 2);
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $sign_str = $this->generateSign($data, $this->pay_set['private_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['status'] = 1;
        if ($data['status'] != 'SUCCESS') {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['mchOrderNo'];
        $rsps['othr_number'] = '';//代付没有返回三方订单号
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}