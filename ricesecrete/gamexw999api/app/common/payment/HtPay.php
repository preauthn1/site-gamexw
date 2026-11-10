<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class HtPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchant'] = $this->pay_set['merchant'];//商户号
        $params['channel'] = $this->pay_set['channel'];
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['order_number'] = $pay_arr['order_sn']; // 商户订单号
        $params['code'] = $this->pay_set['code'];
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['done_url'] = $pay_arr['call_domain']; // 重定向
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "HtPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "HtPay", $res], "payment");
        $res = json_decode($res, true);
        if(!isset($res['status'])){
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (empty($res) || $res['status'] != '1') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['pay_url'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['pay_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchant'] = $this->pay_set['merchant'];//商户号
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['order_number'] = $pay_arr['order_sn']; // 商户订单号
        $params['code'] = $pay_arr['bank_code']; //银行编码
        $params['name'] = $pay_arr['account_name']; //姓名
        $params['account'] = $pay_arr['bank_number']; //银行卡号
        $params['remark'] = 'no';
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "HtPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "HtPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != '1') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != '5') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_number'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '5') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_number'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}