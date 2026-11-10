<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class Qpay
{
    private $businessNumber = "";
    private $PublicKey = "";
    private $PrivateKey = "";
    private $url = "";
    private $withdrawal_url = "";
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->businessNumber = $pay_set['businessNumber'];
        $this->PublicKey = $pay_set['PublicKey'];
        $this->PrivateKey = $pay_set['PrivateKey'];
        $this->url = $pay_set['url'];
        $this->withdrawal_url = $pay_set['withdrawal_url'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params = array(
            'businessNumber' => $this->businessNumber,
            'money' => $pay_arr['apply_price'],
            'notifyUrl' => $this->domain.'/notify/Qpay',
            'dateTime' => date("YmdHis",time()),
            'businessOrder' => $pay_arr['order_sn'],
            'redirectUrl' => $pay_arr['call_domain'],
        );
        ksort($params);
        $params_str = '';
        foreach ($params as $key => $val) {
            $params_str = $params_str . $val;
        }
        $sign = $this->pivate_key_encrypt($params_str, $this->PrivateKey);
        $params['platSign'] = $sign;
        Log::write(['Submit_create',"Qpay",$params], "payment");
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res = PayRequest::curlS($this->url,$req,$header);
        Log::write(['Return_create',"Qpay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != '200') {
            throw new \Exception($res['msg'], 0);
        }
        $pay_info['pay_url']=$res['data']['paymentUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params = array(
            'dateTime' => date("YmdHis",time()),
            'money' => $pay_arr['amount'],
            'notifyUrl' => $this->domain.'/withdrawal/notify/Qpay',
            'businessNumber' => $this->businessNumber,
            'payType' => $pay_arr['bank_code'],
            'number' => $pay_arr['bank_number'],
            'name' => $pay_arr['account_name'],
            'businessOrder' => $pay_arr['order_sn']
        );

        ksort($params);
        $params_str = '';
        foreach ($params as $key => $val) {
            $params_str = $params_str . $val;
        }

        $sign = $this->pivate_key_encrypt($params_str, $this->PrivateKey);

        $params['platSign'] = $sign;
        Log::write(['Submit_withdrawal',"Qpay",$params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res = PayRequest::curlS($this->withdrawal_url,$req,$header);
        Log::write(['Return_withdrawal',"Qpay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != '200') {
            throw new \Exception($res['msg'], 0);
        }
        return $res['data']['orderNum'];
    }

    //异步回调支付fmpay
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['platSign']??'';
        unset($data['platSign']);//不参与签名
        if($data['code'] != '200'){
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

        $rsps['order_sn'] = $data['businessOrder'];
        $rsps['trade_sn'] = $data['orderNum'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = isset($data['payMoney'])?$data['payMoney']:$data['payFee'];
        return $rsps;
    }

    //异步回调支付fmpay
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['platSign']??'';
        unset($notify_data['platSign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['code'] != "200"){
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

        $rsps['order_sn']=$notify_data['businessOrder'];
        $rsps['othr_number']=$notify_data['orderNum'];
        $rsps['msg'] = $notify_data['msg'];
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
