<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class KaolaPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['app_key'] = $this->pay_set['app_key'];//商户号
        $params['balance'] = $pay_arr['apply_price']; // 订单金额
        $params['ord_id'] = $pay_arr['order_sn']; // 商户订单号
        $params['p_method'] = $this->pay_set['p_method'];
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "KaolaPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "KaolaPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['err'] != '0') {
            throw new \Exception($res['err_msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['url'])) {
            throw new \Exception($res['err_msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['app_key'] = $this->pay_set['app_key'];//商户号
        $params['balance'] = $pay_arr['amount']; // 订单金额
        $params['ord_id'] = $pay_arr['order_sn']; // 商户订单号
        $params['card'] = $pay_arr['bank_number']; //银行卡号
        $params['name'] = $pay_arr['account_name']; //姓名
        $params['p_bank_code'] = $pay_arr['bank_code']; //银行编码
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "KaolaPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "KaolaPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['err'] != '0') {
            throw new \Exception("渠道返回:" . $res['err_msg'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey){
        unset($data['sign']);
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if($value != ''){
                $str .= $value;
            }
        }
        $str .= $signKey;
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
        $rsps['order_sn'] = $data['order'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ??0;
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

        $rsps['order_sn'] = $data['order'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}
