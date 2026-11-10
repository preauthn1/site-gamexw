<?php
namespace app\common\payment;
use app\common\lib\Util;
use app\common\payment\PayRequest;
use think\Exception;
use think\facade\Log;

class JunePay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['order_no'] = $pay_arr['order_sn'];#订单号
        $params['order_amount'] = $pay_arr['apply_price'];#金额
        $params['order_note'] = 'payment';#备注
        $params['customer_id'] = $pay_arr['username'];#客户id
        $params['customer_email'] = $pay_arr['mail'] ?: '123@gmail.com';#客户邮箱
        $params['customer_phone'] = $pay_arr['phone'] ??'0815648636';#客户手机

        $header = [
            "Content-Type: application/json",
            "MerchantCode: ". $this->pay_set['merchant_code'],
            "BizTimestamp: ". time(),
            "BizReqSignature: ". $this->sign($params, $this->pay_set['private_key']),
        ];
        Log::write(['Submit_create', "JunePay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],json_encode($params), $header);
        Log::write(['Return_create', "JunePay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['error_code']) || $res['error_code'] != '0000') {
            throw new \Exception($res['error_message'] ??'支付信息错误', 0);
        }
        if (!isset($res['payment_link'])) {
            throw new \Exception($res['error_message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['payment_link'];
        $pay_info['othr_number'] = $res['gp_order_no'];
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

        $params['order_no'] = $pay_arr['order_sn'];//商户代付订单号
        $params['order_amount'] = $pay_arr['amount'];//金额
        $params['order_note'] = 'withdrawal';//订单备注
        $params['transfer_mode'] = $this->pay_set['transfer_mode'];// ⽀付⽅式 固定：banktransfer
        $params['bene_id'] = $pay_arr['username'];//账户姓名
        $params['name'] = $pay_arr['username'];//账户姓名
        $params['email'] = $pay_arr['mail'] ?: '123@gmail.com';#客户邮箱
        $params['phone'] = $pay_arr['phone'] ??'0815648636';#客户手机
        $params['address_1'] = $pay_arr['bank_number'];#收款地址
        $params['bank_name'] = $bank_name;#收款⼈银⾏名
        $params['bank_account'] = $pay_arr['bank_number'];#收款⼈银⾏账号
        $params['ifsc'] = $pay_arr['ifsc'];#收款⼈银⾏ifsc

        $header = [
            "Content-Type: application/json",
            "MerchantCode: ". $this->pay_set['merchant_code'],
            "BizTimestamp: ". time(),
            "BizReqSignature: ". $this->sign($params, $this->pay_set['private_key']),
        ];
        Log::write(['Submit_withdrawal', "JunePay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],json_encode($params), $header);
        Log::write(['Return_withdrawal', "JunePay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['error_code']) || $res['error_code'] != '0000') {
            throw new \Exception($res['error_message'] ??'支付信息错误', 0);
        }
        return $res['gp_order_no'] ?? '';
    }

    private function sign($data, $key)
    {
        ksort($data);
        $msg = urldecode(http_build_query($data) . '|' . time());

        $privateKey = "REDACTED\n";
        //生成签名
        if(openssl_sign($msg, $sign, $privateKey, OPENSSL_ALGO_SHA256)) {
            return base64_encode($sign);
        }
    }

    private function verify($data, $sign, $keys)
    {
        $data = array_filter($data);
        ksort($data);
        $msg = urldecode(http_build_query($data) . '|' . request()->header()['biztimestamp']);
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
        $sign = request()->header()['bizreqsignature'];
        if( !$this->verify($data, $sign, $this->pay_set['public_key']) ){
            return 'faile';
        }
        if( $data['order_status'] != 'SUCCESS' ){
            return 'faile';
        }
        $rsps['order_sn'] = $data['order_no'];
        $rsps['trade_sn'] = $data['gp_order_no'];
        $rsps['result'] = "ok";
        $rsps['money'] = $data['order_pay_amount'] ?: 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $data = $this->handleAmount($data);
        $sign = request()->header()['bizreqsignature'];
        if( !$this->verify($data, $sign, $this->pay_set['public_key']) ){
            return 'faile';
        }
        $rsps['status'] = 1;
        if( $data['order_status'] != 'SUCCESS' ){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_no'];
        $rsps['othr_number'] = $data['gp_order_no'];
        $rsps['msg'] = '';
        $rsps['result'] = "ok";
        return $rsps;
    }

    //处理金额
    private function handleAmount($data){
        $data['order_amount'] = number_format($data['order_amount'], 2, '.', '');
        $data['order_pay_amount'] = number_format($data['order_pay_amount'], 2, '.', '');
        return $data;
    }


    //获取三方代收余额
    public function get_balance($type){
        $params = [];
        $header = [
            "Content-Type: application/json",
            "MerchantCode: ". $this->pay_set['merchant_code'],
            "BizTimestamp: ". time(),
            "BizReqSignature: " . '',
        ];
        Log::write(['get_balance_create', "JunePay", $this->pay_set['balance_url'], $params,$header], "payment");
        $res = PayRequest::curl_get($this->pay_set['balance_url'], $params, $header);
        Log::write(['get_balance_return', "JunePay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['error_code']) || $res['error_code'] != '0000') {
            if($type == 1){
                //抛出错误
                throw new \Exception('支付信息错误', 0);
            }else{
                return  'fail';
            }
        }
        return $res['availableBalance'] ?? '';
    }


}