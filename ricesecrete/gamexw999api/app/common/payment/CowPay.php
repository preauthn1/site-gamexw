<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class CowPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $data = [
            'merchant_code' => $this->pay_set['merchant_code'],
            'order_no' => $pay_arr['order_sn'], // 商户订单号
            'order_amount' => intval($pay_arr['apply_price']),
            'order_time' => time(),
            'product_name' => 'create_order_'.rand(1,99999),
            'notify_url' => $pay_arr['notify_url'],
            'pay_type' => $this->pay_set['pay_type'],
            'return_url' => $pay_arr['call_domain'],
            'payer_info' => $pay_arr['username']
        ];
        $params['signtype'] = "MD5";
        $params['transdata'] = urlencode(json_encode($data));
        $params['sign'] = $this->generateSign($data, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "CowPay", $params,$data], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "CowPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  (isset($res['status']) && !$res['status'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['payUrl'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['payUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $data = [
            'merchant_code' => $this->pay_set['merchant_code'],
            'order_no' => $pay_arr['order_sn'], // 商户订单号
            'order_amount' => intval($pay_arr['amount']),
            'pay_type' => $this->pay_set['withdrawal_pay_type'],
            'bank_name' => $pay_arr['bankName'],
            'bank_card' => $pay_arr['bank_number'],
            'bank_branch' => $pay_arr['ifsc'],
            'user_name' => $pay_arr['account_name'],
            'notify_url' => $pay_arr['notify_url'],
        ];
        $params['signtype'] = "MD5";
        $params['transdata'] = urlencode(json_encode($data));
        $params['sign'] = $this->generateSign($data, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "CowPay", $params,$data], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "CowPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  !$res['status']) {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if($value != ''){
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "key=".$signKey;
        return urlencode(strtoupper(md5($str)));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if(!$data){
            return "data error";
        }
        if(!isset($data['transdata']) || !isset($data['sign'])){
            return "faile";
        }
        $transdata = json_decode(urldecode($data['transdata']), true);

        if ($transdata['payment'] != '支付成功') {
            return "faile";
        }

        $sign_str = $this->generateSign($transdata, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }

        $rsps['order_sn'] = $transdata['order_no'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "200";
        $rsps['money'] = $transdata['order_amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if(!$data){
            return "data error";
        }
        if(!isset($data['transdata']) || !isset($data['sign'])){
            return "faile";
        }
        $transdata = json_decode(urldecode($data['transdata']), true);

        $sign_str = $this->generateSign($transdata, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        //resp_code 以该字段判断代付结果：S 代付成功，F 代付失败，P 代付中
        if( $transdata['resp_code'] == 'P' ){
            return "200";
        }

        if ($transdata['resp_code'] != 'S') {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $transdata['order_no'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = $transdata['message'] ??'';
        $rsps['result'] = "200";
        return $rsps;
    }
}
