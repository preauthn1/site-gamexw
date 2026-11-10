<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class HackPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['agent'] = $this->pay_set['agent'];//商户号
        $params['order_sn'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = intval($pay_arr['apply_price']); // 订单金额
        $params['type'] = $this->pay_set['type'];
        if($this->pay_set['type'] == 1){
            $params['user_name'] = $pay_arr['username'];
            $params['pay_content'] = 'create order';
        }
        $params['user_phone'] = $pay_arr['phone'];
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "HackPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "HackPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '1') {
            throw new \Exception('支付信息错误', 0);
        }
        if (!isset($res['data']['payment_account'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['payment_account'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['agent'] = $this->pay_set['agent'];//商户号
        $params['order_sn'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = intval($pay_arr['amount']); // 订单金额
        $params['bank_name'] = $pay_arr['bankName'];//银行名称
        $params['bank_code'] = $pay_arr['bank_code']; //银行编码
        $params['bank_account'] = $pay_arr['bank_number']; //银行卡号
        $params['user_name'] = $pay_arr['account_name']; //姓名
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "HackPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$params);
        Log::write(['Return_withdrawal', "HackPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '1') {
            throw new \Exception("代付信息错误", 0);
        }
        return $res['data']['system_sn'] ?? '';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = http_build_query(myArrayFilter($data));
        $str .= '&api_key='.$signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != '01') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_sn'];
        $rsps['trade_sn'] = $data['system_sn'];
        $rsps['result'] = "OK";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '01') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_sn'];
        $rsps['othr_number'] = $data['system_sn'];
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }
}
