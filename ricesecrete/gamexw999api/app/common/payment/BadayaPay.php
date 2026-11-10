<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class BadayaPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['merchant'] = $this->pay_set['merchant'];//商户号
        $params['paytype'] = $this->pay_set['paytype'];
        $params['outtradeno'] = $pay_arr['order_sn']; // 商户订单号
        $params['notifyurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['returnurl'] = $pay_arr['call_domain']; // 重定向
        $params['returndataformat'] = 'serverhtml ';
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "BadayaPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "BadayaPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception($res['results'] ??'支付信息错误', 0);
        }
        if (!isset($res['results'])) {
            throw new \Exception($res['results'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['results'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['merchant'] = $this->pay_set['merchant'];//商户号
        $params['bankname'] = $pay_arr['bankName'];//银行名称
        $params['cardno'] = $pay_arr['bank_number']; //银行卡号
        $params['cardname'] = $pay_arr['account_name']; //姓名
        $params['notifyurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['outtransferno'] = $pay_arr['order_sn']; // 商户订单号
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "BadayaPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$params);
        Log::write(['Return_withdrawal', "BadayaPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception("渠道返回:" . $res['results'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = http_build_query($data);
        $str .= '&secret='.$signKey;
        $str = strtolower($str);
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != '1') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['outtradeno'];
        $rsps['trade_sn'] = $data['tradeno'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['ramount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '1') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['outtransferno'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}
