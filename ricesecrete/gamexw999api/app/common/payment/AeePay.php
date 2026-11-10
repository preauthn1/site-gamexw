<?php
namespace app\common\payment;
use app\common\FastGame\Base\Utils;
use app\common\payment\PayRequest;
use think\facade\Log;

class AeePay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchantno'] = $this->pay_set['merchantno'];//商户编号
        $params['morderno'] = $pay_arr['order_sn'];//商户订单号
        $params['productname'] = Utils::random(16);//只要不为空的字符串
        $params['money'] = $pay_arr['apply_price'];//充值金额
        $params['paycode'] = $this->pay_set['paycode'];//参考附录  6.2  BDTQR  QR code,BDTTP  Transfer payment
        if( isset($this->pay_set['devicetype']) ){
            $params['devicetype'] = $this->pay_set['devicetype'];//Android:1；IOS:2; PC:3; defaut 1
        }
        if( isset($this->pay_set['bankcode']) ){
            $params['bankcode'] = $this->pay_set['bankcode'];//当paycode = BDTTP, 用于选择支付所需要的用银行 参考附录 6.1
        }
        $params['sendtime'] = date('YmdHis');//格式：yyyyMMddHHmmss, 例如：20170502144100
        $params['notifyurl'] = $pay_arr['notify_url'];//通知回调地址
        $params['buyerip'] = Utils::get_ip();//用户IP
        $params['userinfo'] = $pay_arr['username'];//用户唯一标识，例如:用户名，或转译用户名

        $str = "{$params['merchantno']}|{$params['morderno']}|{$params['paycode']}|{$params['notifyurl']}|{$params['money']}|{$params['sendtime']}|{$params['buyerip']}|{$this->pay_set['sign_key']}";
        $sign = md5($str);

        $params['sign'] = $sign;
        Log::write(['Submit_create', "AeePay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "AeePay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  !$res['success'] || $res['resultCode'] != '10000' || !isset($res['data']['payurl'])) {
            throw new \Exception($res['resultMsg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['payurl'];
        $pay_info['othr_number'] = $res['data']['orderno'];
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
        $params['merchantno'] = $this->pay_set['merchantno'];//商户编号
        $params['morderno'] = $pay_arr['order_sn'];//商户订单号
        $params['type'] = 0;//Default: 0
        $params['money'] = $pay_arr['amount'];//提现金额
        $params['bankcode'] = $bank_codes;//银行编码
        $params['realname'] = $pay_arr['username'];//姓名
        $params['cardno'] = $pay_arr['bank_number'];//账号
        $params['sendtime'] = date('YmdHis');//格式：yyyyMMddHHmmss, 例如：20170502144100
        $params['notifyurl'] = $pay_arr['notify_url'];//通知地址
        $params['buyerip'] = Utils::get_ip();//用户IP

        $str = "{$params['merchantno']}|{$params['morderno']}|{$params['bankcode']}|{$params['type']}|{$params['realname']}|{$params['cardno']}|{$params['money']}|{$params['sendtime']}|{$params['buyerip']}|{$this->pay_set['sign_key']}";
        $sign = md5($str);

        $params['sign'] = $sign;
        Log::write(['Submit_withdrawal', "AeePay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$params);
        Log::write(['Return_withdrawal', "AeePay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  !$res['success'] || !isset($res['data']['morderno'])) {
            throw new \Exception($res['resultMsg'] ??'支付信息错误', 0);
        }
        return $res['data']['morderno'];
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"], $data['productname'], $data['userinfo'], $data['devicetype'], $data['bankcode'], $data['reqmoney'], $data['realname']);
        ksort($data);
        $signStr = '';
        foreach ($data as $v){
            $signStr.= $v.'|';
        }
        $signStr .= $signKey;
        return md5($signStr);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != 1) {
            return "faile";
        }

        $str = "{$data['merchantno']}|{$data['orderno']}|{$data['morderno']}|{$data['paycode']}|{$data['tjmoney']}|{$data['money']}|{$data['status']}|{$this->pay_set['sign_key']}";
        $sign_str = md5($str);

//        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['morderno'];
        $rsps['trade_sn'] = $data['orderno'];
        $rsps['result'] = "ok";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if( !in_array($data['status'], [3,4]) ){
            return "faile";
        }
        $rsps['status'] = 1;
        if ($data['status'] != 3) {
            $rsps['status'] = 2;
        }

        $str = "{$data['merchantno']}|{$data['orderno']}|{$data['bankcode']}|{$data['morderno']}|{$data['cardno']}|{$data['tjmoney']}|{$data['money']}|{$data['status']}|{$this->pay_set['sign_key']}";
        $sign_str = md5($str);
//        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "faile";
        }

        $rsps['order_sn'] = $data['morderno'];
        $rsps['othr_number'] = $data['orderno'];
        $rsps['msg'] = '';
        $rsps['result'] = "ok";
        return $rsps;
    }
}
