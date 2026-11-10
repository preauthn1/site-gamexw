<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class FsPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['channel_code'] = $this->pay_set['channel_code'];//通道
        $params['username'] = $this->pay_set['username'];//商户号
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['order_number'] = $pay_arr['order_sn']; // 商户订单号
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['return_url'] = $pay_arr['call_domain']; // 重定向
        $params['client_ip'] = $pay_arr['pay_ip'];
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "FsPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "FsPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !in_array($res['http_status_code'],[200,201])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['casher_url'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['casher_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['username'] = $this->pay_set['username'];//商户号
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['order_number'] = $pay_arr['order_sn']; // 商户订单号
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['bank_card_holder_name'] = $pay_arr['account_name']; //姓名
        $params['bank_card_number'] = $pay_arr['bank_number']; //银行卡号
        $params['bank_name'] = $pay_arr['bank_code'];
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "FsPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "FsPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !in_array($res['http_status_code'],[200,201])) {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['data']['system_order_number'];
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query($data));
        $str .= '&secret_key='.$signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        if (!in_array($data['http_status_code'],['200','201'])) {
            return "faile";
        }

        if(!isset($data['data'])){
            return "data faile";
        }

        if(!in_array($data['data']['status'],[4,5])){
            return "status faile";
        }

        $sign = isset($data['data']['sign']) ? $data['data']['sign'] : '';
        $sign_str = $this->generateSign($data['data'], $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }

        $rsps['order_sn'] = $data['data']['order_number'];
        $rsps['trade_sn'] = $data['data']['system_order_number'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if (!in_array($data['http_status_code'],['200','201'])) {
            $rsps['status'] = 2;
        }

        if(!isset($data['data'])){
            $rsps['status'] = 2;
        }

        if(!in_array($data['data']['status'],[4,5])){
            $rsps['status'] = 2;
        }

        $sign = isset($data['data']['sign']) ? $data['data']['sign'] : '';
        $sign_str = $this->generateSign($data['data'], $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['data']['order_number'];
        $rsps['othr_number'] = $data['data']['system_order_number'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}