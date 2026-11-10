<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class LumaPay
{

    private $merchantCode = "";
    private $PublicKey = "";
    private $PrivateKey = "";
    private $url = "";
    private $withdrawal_url = "";
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->merchantCode = $pay_set['merchantCode'];
        $this->PublicKey = $pay_set['PublicKey'];
        $this->PrivateKey = $pay_set['PrivateKey'];
        $this->url = $pay_set['url'];
        $this->withdrawal_url = $pay_set['withdrawal_url'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params = array(
            'merchantCode' => $this->merchantCode,
            'method' => 'PAYU',
            'payMoney' => (string)$pay_arr['apply_price'],
            'orderNum' => $pay_arr['order_sn'],
            'productDetail' => $pay_arr['username'],
            'name' => $pay_arr['username'],
            'email' => $pay_arr['username']."@0s.com",
            'phone' => (string)$pay_arr['user_id'],
            'redirectUrl' => $pay_arr['call_domain'],
            'notifyUrl' => $this->domain.'/notify/LumaPay',
            'dateTime' => date("YmdHis",time()),
        );
        ksort($params);
        $params_str = '';
        foreach ($params as $key => $val) {
            $params_str = $params_str . $val;
        }
        $sign = $this->pivate_key_encrypt($params_str, $this->PrivateKey);
        $params['sign'] = $sign;
        Log::write(['Submit_create',"LumaPay",$params], "payment");
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res = PayRequest::curlS($this->url,$req,$header);
        Log::write(['Return_create',"LumaPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['platRespCode'] == 'FAIL') {
            throw new \Exception($res['platRespMessage'], 0);
        }
        $pay_info['pay_url']=$res['vaNumber'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params = array(
            'merchantCode' => $this->merchantCode,
            'orderNum' => $pay_arr['order_sn'],
            'money' => intval($pay_arr['amount']),
            'feeType' => '1',
            'dateTime' => date("YmdHis",time()),
            'name' => $pay_arr['account_name'],
            'bankname' => $pay_arr['bankName'],
            'bankLinked' => $pay_arr['ifsc'],
            'email' => $pay_arr['username']."@0s.com",
            'phone' => empty($pay_arr['phone'])?"1234567890":$pay_arr['phone'],
            'number' => $pay_arr['bank_number'],
            'description' => "zhuan zhang",
            'notifyUrl' => $this->domain.'/withdrawal/notify/LumaPay'
        );

        ksort($params);
        $params_str = '';
        foreach ($params as $key => $val) {
            $params_str = $params_str . $val;
        }

        $sign = $this->pivate_key_encrypt($params_str, $this->PrivateKey);

        $params['sign'] = $sign;
        Log::write(['Submit_withdrawal',"LumaPay",$params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res = PayRequest::curlS($this->withdrawal_url,$req,$header);
        Log::write(['Return_withdrawal',"LumaPay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['platRespCode'] == 'FAIL') {
            throw new \Exception($res['platRespMessage'], 0);
        }
        return $res['platOrderNum'];
    }

    //异步回调支付fmpay
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['platSign']??'';
        unset($data['platSign']);//不参与签名
        if($data['code'] != '00'){
            return "faile";
        }

        $decryptSign = $this->public_key_decrypt($notify_sign, $this->PublicKey);
        ksort($data);
        $params_str = '';
        foreach ($data as $key => $val) {
            $params_str = $params_str . $val;
        }

        if($params_str != $decryptSign) {
            return "sign error";
        }

        $rsps['order_sn'] = $data['orderNum'];
        $rsps['trade_sn'] = $data['platOrderNum'];
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    //异步回调支付fmpay
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['platSign']??'';
        unset($notify_data['platSign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['status'] != "2"){
            $rsps['status'] = 2;
        }

        $decryptSign = $this->public_key_decrypt($notify_sign, $this->PublicKey);
        ksort($notify_data);
        $params_str = '';
        foreach ($notify_data as $key => $val) {
            $params_str = $params_str . $val;
        }

        if($params_str != $decryptSign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn']=$notify_data['orderNum'];
        $rsps['othr_number']=$notify_data['platOrderNum'];
        $rsps['msg'] = $notify_data['statusMsg'];
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    public function pivate_key_encrypt($data, $pivate_key)
    {
        $pivate_key = 'REDACTED';
        $pi_key = openssl_pkey_get_private($pivate_key);
        $crypto = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $pi_key);
            $crypto .= $encryptData;
        }

        return base64_encode($crypto);
    }

    public function public_key_decrypt($data, $public_key)
    {
        $public_key = '-----BEGIN PUBLIC KEY-----'."\n".$public_key."\n".'-----END PUBLIC KEY-----';
        $data = base64_decode($data);
        $pu_key =  openssl_pkey_get_public($public_key);
        $crypto = '';
        foreach (str_split($data, 128) as $chunk) {
            openssl_public_decrypt($chunk, $decryptData, $pu_key);
            $crypto .= $decryptData;
        }

        return $crypto;
    }
}