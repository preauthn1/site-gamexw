<?php
namespace app\common\payment;
use app\common\lib\Util;
use app\common\payment\PayRequest;
use think\Exception;
use think\facade\Log;

class SitBank
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mchOrderNo'] = $pay_arr['order_sn'];#订单号
        $params['amount'] = intval($pay_arr['apply_price'] * 100);#金额(单位分)
        $params['notifyUrl'] = $pay_arr['notify_url'];#回调地址
        $params['successUrl'] = $pay_arr['call_domain'];#支付返回地址
        $header = [
            "Content-Type: application/json",
            "X-Account: ". $this->pay_set['account'],
            "X-Signature: ". $this->sign(json_encode($params), $this->pay_set['private_key']),
        ];
        Log::write(['Submit_create', "SigBank", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],json_encode($params), $header);
        Log::write(['Return_create', "SigBank", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != 2) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['url'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['url'];
        $pay_info['othr_number'] = $res['orderNo'];
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
        $bank_name = strtoupper($bank_name);

        $params['mchOrderNo'] = $pay_arr['order_sn'];//商户代付订单号
        $params['notifyUrl'] = $pay_arr['notify_url'];//回调地址
        $params['amount'] = intval($pay_arr['amount'] * 100);//金额
        $params['accountName'] = $pay_arr['username'];//账户姓名
        $params['accountNo'] = $pay_arr['bank_number'];//账户号码
        $params['accountType'] = 2;//账户类型 1.CPF, 2.CNPJ, 3.EMAIL, 4.PHONE, 5.EVP
        if( $bank_name == 'CPF' ){
            $params['idNumber'] = $pay_arr['bank_number'];//CPF
            $params['accountType'] = 1;
        }elseif( $bank_name == 'EMAIL' ){
            $params['email'] = $pay_arr['bank_number'];//邮箱
            $params['accountType'] = 3;
        }elseif( $bank_name == 'PHONE' ){
            //号码
            $params['accountType'] = 4;
            $params['phone'] = '+55'. ($pay_arr['bank_number'] ?: $pay_arr['bank_number'] ??'0815648636');
        }
        $header = [
            'Content-Type: application/json',
            "X-Account: ". $this->pay_set['account'],
            "X-Signature: ". $this->sign(json_encode($params), $this->pay_set['private_key']),
        ];
        Log::write(['Submit_withdrawal', "SigBank", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],json_encode($params), $header);
        Log::write(['Return_withdrawal', "SigBank", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != 2) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['orderNo'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        return $res['orderNo'];
    }

    private function sign($msg, $key)
    {
        $privateKey = "REDACTED\n";

        //生成签名
        if(openssl_sign($msg, $sign, $privateKey, OPENSSL_ALGO_SHA256)) {
            return base64_encode($sign);
        }
    }

    private function verify($msg, $sign, $keys)
    {
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($keys, 64, "\n", true) . "\n-----END PUBLIC KEY-----\n";
        if(openssl_verify($msg, base64_decode($sign), $publicKey, OPENSSL_ALGO_SHA256)) {
            return true;
        }
        return false;
    }


    //异步回调支付
    public function notify($data)
    {
        $data = $this->handleAmount($data);
        $sign = request()->header()['x-signature'];
        if( !$this->verify(json_encode($data), $sign, $this->pay_set['public_key']) ){
            throw new Exception('faile');
        }
        if( $data['status'] != 3 ){
            throw new Exception('faile');
        }
        $rsps['order_sn'] = $data['mchOrderNo'];
        $rsps['trade_sn'] = $data['orderNo'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = isset($data['orderAmount']) ? $data['orderAmount'] / 100: 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        Log::write(['withdrawal_test', request()->header(), $data], "withdrawal");
        $data = $this->handleAmount($data);
        $sign = request()->header()['x-signature'];
        if( !$this->verify(json_encode($data), $sign, $this->pay_set['public_key']) ){
            throw new Exception('faile');
        }
        if( $data['status'] == 3 ){
            $rsps['status'] = 1;
        }elseif ($data['status'] == 4){
            $rsps['status'] = 2;
        }else{
            throw new Exception('faile');
        }

        $rsps['order_sn'] = $data['mchOrderNo'];
        $rsps['othr_number'] = $data['mchOrderNo'];
        $rsps['msg'] = $data['transMsg']??'';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    //处理金额
    private function handleAmount($data){
        $data['orderAmount'] = intval($data['orderAmount']);
        $data['amount'] = intval($data['amount']);
        $data['fee'] = intval($data['fee']);
        $data['performedAt'] = $data['performedAt']?intval($data['performedAt']):$data['performedAt'];
        $data['status'] = intval($data['status']);
        if(isset($data['payments']) && is_array($data['payments'])){
            foreach ($data['payments'] as &$v){
                $v['orderAmount'] = intval($v['orderAmount']);
                $v['amount'] = intval($v['amount']);
                $v['fee'] = intval($v['fee']);
                $v['performedAt'] = intval($v['performedAt']);
            }
        }
        return $data;
    }
}