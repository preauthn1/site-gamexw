<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class RusPay
{
    private $merchant_id = "";
    private $pay_type = "";
    private $channel_id = "";
    private $sign_key = "";
    private $gateway = '';
    private $channel = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->pay_type = $pay_set['pay_type'];
        $this->channel_id = $pay_set['channel_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->channel = $pay_set['channel'] ??'';
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mch_id'] = $this->merchant_id;//商户号
        $params['notify_url'] = $pay_arr['notify_url'];// 异步通知地址
        $params['user_id'] = $pay_arr['phone']; // 用户编号
        $params['mch_order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['pay_type'] = $this->pay_type;//支付类型
        $params['trade_amount'] = intval($pay_arr['apply_price']); // 订单金额
        $params['order_date'] = date('Y-m-d H:i:s',time());//提交时间
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "RusPay", $params], "payment");
        $url = $this->gateway . '/api/Pay/addPay';
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($url, $req,$header);
        Log::write(['Return_create', "RusPay", $res], "payment");
        $res = json_decode($res, true);
        if (!isset($res['code'])) {
            $res = json_decode($res, true);
            throw new \Exception($res['messageEn'] ??'支付信息错误', 0);
        }
        if (empty($res) ||  $res['code'] != '20000') {
            throw new \Exception($res['messageEn'] ??'支付信息错误', 0);
        }
        if (!isset($res['result']['pay_pageurl'])) {
            throw new \Exception($res['messageEn'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['result']['pay_pageurl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['mch_id'] = $this->merchant_id;//商户号
        $params['channel_id'] = $this->channel_id;//通道列表
        $params['order_sn'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['payer_ifsc'] = 'ifsc1001';
        if ($pay_arr['bankName'] == 'Phone'){
            $pixKey = $pay_arr['bank_number'];
            if (str_starts_with($pixKey, "+")) {
                $pixKey = substr($pixKey,1);
            }
            if (strlen($pixKey)>11 && str_starts_with($pixKey,"55")){
                $pixKey = substr($pixKey,2);
            }
            $pay_arr['bank_number'] = "+55".$pixKey;
        }
        $params['payer_account'] = $pay_arr['bank_number'];// 银行账号
        $params['payer_name'] = $pay_arr['account_name'];// 收款人姓名
        $params['notify_url'] = $pay_arr['notify_url'];// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        $url = $this->gateway . '/api/Pay/addPayOut';
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        Log::write(['Submit_withdrawal', "RusPay", $params], "withdrawal");
        $res = PayRequest::curlS($url, $req,$header);
        Log::write(['Return_withdrawal', "RusPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (!isset($res['code'])) {
            $res = json_decode($res, true);
            throw new \Exception("渠道返回:" . $res['messageEn'], 0);
        }
        if (empty($res) || $res['code'] != '20000') {
            throw new \Exception("渠道返回:" . $res['messageEn'], 0);
        }
        return '';
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
        $str .= 'key='.$signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['tradeResult'] != '1') {
            return "faile";
        }
        $sign_data = [
            'mch_id'=>$data['mch_id'],
            'pay_type'=>$data['pay_type'],
            'mch_order_no'=>$data['mch_order_no'],
            'trade_amount'=>intval($data['trade_amount']),
            'order_date'=>$data['order_date'],
            'notify_url'=>$data['notify_url'],
            'user_id'=>$data['user_id'],
        ];
        $sign_str = $this->generateSign($sign_data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['mch_order_no'];
        $rsps['trade_sn'] = $data['orderNo'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ? $data['amount']:$data['trade_amount'];
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['tradeResult'] != '1') {
            $rsps['status'] = 2;
        }
        $sign_data = [
            'payer_ifsc'=>$data['payer_ifsc'],
            'order_sn'=>$data['order_sn'],
            'payer_name'=>$data['payer_name'],
            'mch_id'=>$data['mch_id'],
            'channel_id'=>$data['channel_id'],
            'amount'=>$data['amount'],
            'payer_account'=>$data['payer_account'],
            'notify_url'=>$this->domain . '/withdrawal/notify/'.$this->channel,
        ];
        $sign_str = $this->generateSign($sign_data, $this->sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_sn'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = $data['message'] ??'';
        $rsps['result'] = "success";
        return $rsps;
    }
}
