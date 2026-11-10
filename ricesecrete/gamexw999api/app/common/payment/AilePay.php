<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class AilePay
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
        $params['callback_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' .$this->pay_set['api_token']
        );
        Log::write(['Submit_create', "AilePay", $params,$header], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "AilePay", $res], "payment");
        $res = json_decode($res, true);
        if(!isset($res['success'])){
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (empty($res) ||  !$res['success']) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['uri'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['uri'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['bank_id'] = $pay_arr['bank_code'] ?? 'GCash'; //银行编码
        $params['bank_owner'] = $pay_arr['account_name']; //姓名
        $params['account_number'] = $pay_arr['bank_number'];
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['callback_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->generateSign($params);
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' .$this->pay_set['api_token']
        );
        Log::write(['Submit_withdrawal', "AilePay", $params,$header], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "AilePay", $res], "withdrawal");
        $res = json_decode($res, true);
        if(!isset($res['success'])){
            throw new \Exception("渠道返回:".$res['message'], 0);
        }
        if (empty($res) || !$res['success']) {
            throw new \Exception("渠道返回:".$res['message'], 0);
        }
        return $res['data']['trade_no'];
    }

    private function generateSign($data)
    {
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= $this->pay_set['api_token'].$this->pay_set['notify_token'];
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['state'] != 'completed') {
            return "faile";
        }

        $sign_str = $this->generateSign($data);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['trade_sn'] = $data['trade_no'];
        $rsps['result'] = "ok";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['state'] != 'completed') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['othr_number'] = $data['trade_no'];
        $rsps['msg'] = $data['errors'] ??'';
        $rsps['result'] = "ok";
        return $rsps;
    }
}