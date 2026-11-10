<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class EasyPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['accountName'] = $pay_arr['username']; // 姓名
        $params['amount'] = intval($pay_arr['apply_price']); // 订单金额
        $params['gatewayCode'] = $this->pay_set['gatewayCode'];//通道名称
        $params['merchantAccount'] = $this->pay_set['merchantAccount'];//商户账户
        $params['mobile'] = trim($pay_arr['qh'],"+").$pay_arr['phone'];//用户手机号
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['redirectUrl'] = $pay_arr['call_domain']; // 重定向
        $params['requestNo'] = $pay_arr['order_sn']; // 商户订单号
        $sign_data = [
            'accountName'=>$params['accountName'],
            'amount'=>$params['amount'],
            'gatewayCode'=>$params['gatewayCode'],
            'merchantAccount'=>$params['merchantAccount'],
            'requestNo'=>$params['requestNo'],
        ];
        ksort($sign_data);
        $params_str = '';
        foreach ($sign_data as $key => $val) {
            $params_str = $params_str . $val;
        }
        $params['sign'] = $this->Rsa_openssl($params_str, $this->pay_set['PublicKey']);
        Log::write(['Submit_create', "EasyPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "EasyPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '000000') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['paymentUrl'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['paymentUrl'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['requestNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['accountName'] = $pay_arr['account_name']; // 姓名
        $params['amount'] = intval($pay_arr['amount']); // 订单金额
        $params['bankName'] = $pay_arr['bankName'];//银行名称
        $params['bankNumber'] = $pay_arr['bank_number']; //银行卡号
        $params['gatewayCode'] = $this->pay_set['gatewayCode'];//通道名称
        $params['merchantAccount'] = $this->pay_set['merchantAccount'];//商户账户
        $params['mobile'] = trim($pay_arr['qh'],"+").$pay_arr['phone'];//用户手机号
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $sign_data = [
            'accountName'=>$params['accountName'],
            'amount'=>$params['amount'],
            'gatewayCode'=>$params['gatewayCode'],
            'merchantAccount'=>$params['merchantAccount'],
            'requestNo'=>$params['requestNo'],
        ];
        ksort($sign_data);
        $params_str = '';
        foreach ($sign_data as $key => $val) {
            $params_str = $params_str . $val;
        }
        $params['sign'] = $this->Rsa_openssl($params_str, $this->pay_set['PublicKey']);
        Log::write(['Submit_withdrawal', "EasyPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $req,$header);
        Log::write(['Return_withdrawal', "EasyPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '000000') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['data']['orderSeq'];
    }

    /*** @description RSA公钥加密 私钥解密 *
     * @param string $data 待加解密数据 *
     * @param string $operate 操作类型 encode:加密 decode:解密
     * @return string 返回加密内容/解密内容
     */
    protected function Rsa_openssl($data,$keys,$operate = 'encode') {
        if( $operate == 'encode'){
            $public_key = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($keys, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
            $key = openssl_pkey_get_public($public_key);
            if (!$key) {return 'key error'; }
            $return_en = openssl_public_encrypt($data, $crypted, $key);
            if (!$return_en) { return 'key error';}
            return base64_encode($crypted);
        }
        if ($operate == 'decode') {
            $private_key = "REDACTED";
            $key = openssl_pkey_get_private($private_key);
            if (!$key) { return 'key error';}$return_de = openssl_private_decrypt(base64_decode($data), $decrypted, $key);
            if (!$return_de) { return 'key error';}
            return $decrypted;
        }
        return 'key error';
    }

    //异步回调支付fmpay
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        unset($data['sign'],$data['type']);//不参与签名
        if($data['status'] != '2'){
            return "FAILED";
        }
        if(isset($data['amount'])){
            $data['amount'] = sprintf("%.4f",$data['amount']);
        }
        $decryptSign = $this->Rsa_openssl($notify_sign, $this->pay_set['PrivateKey'],'decode');
        ksort($data);
        $params_str = '';
        foreach ($data as $key => $val) {
            $params_str = $params_str . $val;
        }
        if($params_str != $decryptSign) {
            return "FAILED";
        }

        $rsps['order_sn'] = $data['requestNo'];
        $rsps['trade_sn'] = $data['orderSeq'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }

    //异步回调支付
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign'],$notify_data['type']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['status'] != "2"){
            $rsps['status'] = 2;
        }
        if(isset($notify_data['amount'])){
            $notify_data['amount'] = sprintf("%.4f",$notify_data['amount']);
        }
        $decryptSign = $this->Rsa_openssl($notify_sign,  $this->pay_set['PrivateKey'],'decode');
        ksort($notify_data);
        $params_str = '';
        foreach ($notify_data as $key => $val) {
            $params_str = $params_str . $val;
        }


        if($params_str != $decryptSign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn']=$notify_data['requestNo'];
        $rsps['othr_number']=$notify_data['orderSeq'];
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}