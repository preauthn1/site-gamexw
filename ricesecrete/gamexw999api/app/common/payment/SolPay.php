<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
use think\facade\Request;

class SolPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $paramArray = [
            "merchantCode" => $this->pay_set['merchantCode'], //商户号
            "orderNum" => $pay_arr['order_sn'] ,  // 商户订单号
            "payMoney" => $pay_arr['apply_price'], // 支付金额
            "productDetail" => 'productDetail', // 产品描述
            "email" => 'xxxx'.'@gmail.com', // email
            "phone" => '123123123123', // phone
            "notifyUrl" =>  $pay_arr['notify_url'],  // 回调地址
            "redirectUrl" =>  $pay_arr['call_domain'],  // 返回地址
            "expiryPeriod" =>  1440,  // 过期时间
        ];
        $Request = Request::param(false);
        if(isset($Request['pay_cpf']) && empty($Request['pay_cpf'])){
            throw new \Exception('CPF不得为空', 0);
        }
        if(isset($Request['pay_name']) && empty($Request['pay_name'])){
            throw new \Exception('名称不得为空', 0);
        }
        if(isset($Request['pay_name']) && isset($Request['pay_cpf'])){
            $paramArray['name'] = $Request['pay_name'] ?? '' ;#CPF
            $paramArray['cpf'] = $Request['pay_cpf'] ?? '' ;#CPF
        }
        $paramArray['sign'] = $this->generateSign($paramArray, $this->pay_set['PrivateKey']); // 加密秘钥

        Log::write(['Submit_create', "SolPay", $paramArray], "payment");
        $req = json_encode($paramArray);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . '/BRL/pay';
        $res = PayRequest::curlS($url , $req, $header);
        $res = json_decode($res, true);
        Log::write(['Return_create', "SolPay", $res], "payment");
        if (empty($res) || $res['platRespCode'] != 'SUCCESS') {
//            throw new \Exception($res['platRespMessage'], 0);
            throw new \Exception('Por favor insira o CPF correto', 0);
        }
        $pay_info['pay_url'] = $res['url'];
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

        $paramArray = [
            "merchantCode" => $this->pay_set['merchantCode'], //商户号
            "orderNum" => $pay_arr['order_sn'] ,  // 商户订单号
            "money" => $pay_arr['amount'], // 支付金额
            "description" => 'description', // 产品描述
            "name" => $pay_arr['account_name'], //收款人姓名
            "pixType" => $bank_name, //PIX类型：[CPF、PHONE、EMAIL]
            "cpf" => $pay_arr['bank_number'], // CPF号码
            "bankAccount" => $pay_arr['bank_number'], //收款账户号码
            "notifyUrl" =>  $pay_arr['notify_url'],	// 转账结果回调URL
            "feeType" =>  1,	// 转账结果回调URL
        ];
        $paramArray['sign'] = $this->generateSign($paramArray, $this->pay_set['PrivateKey']); // 加密秘钥
        Log::write(['Submit_withdrawal', "SolPay", $paramArray], "withdrawal");
        $req = json_encode($paramArray);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . '/BRL/cash';
        $res = PayRequest::curlS($url, $req, $header);
        $res = json_decode($res, true);
        Log::write(['Return_withdrawal', "SolPay", $res], "withdrawal");
        if (empty($res) || $res['platRespCode'] != 'SUCCESS') {
            throw new \Exception($res['platRespMessage'], 0);
        }
        return $res['platOrderNum'] ?? '';
    }

    private static function generateSign($params, $mchKey)
    {
        $pivate_key = 'REDACTED';
        $pi_key = openssl_pkey_get_private($pivate_key);
        $crypto = '';
        ksort($params);
        $params_str = implode('', $params);
        foreach (str_split($params_str, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $pi_key);
            $crypto .= $encryptData;
        }
        return base64_encode($crypto);
    }

    private static function verifySign($data, $public_key){

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

    //异步回调支付
    public function notify($data)
    {
        $sign = $data['platSign'];
        if($data['code'] != '00'){
            return 'fail';
        }
        unset($data['platSign']);//不参与签名
        Log::write(['notify', "SolPay", $data], "payment");
        $resSign = $this->verifySign($sign, $this->pay_set['PublicKey']); // 验签
        ksort($data);
        $params_str = '';
        foreach ($data as $val) {
            $params_str = $params_str . $val;
        }
        if($params_str != $resSign) {
            return 'faile';
        }
        $rsps['order_sn'] = $data['orderNum'];
        $rsps['trade_sn'] = $data['platOrderNum'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['payMoney'] ?:0;
        return $rsps;
    }

    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = $data['platSign'];
        unset($data['platSign']);//不参与签名
        Log::write(['notify_withdrawal', "SolPay", $data], "payment");
        $resSign = $this->verifySign($sign, $this->pay_set['PublicKey']); // 验签
        ksort($data);
        $params_str = '';
        foreach ($data as $val) {
            $params_str = $params_str . $val;
        }
        if($params_str != $resSign) {
            throw new Exception('faile');
        }
        if( $data['status'] == 2 ){
            $rsps['status'] = 1;
        }elseif ($data['status'] == 4){
            $rsps['status'] = 2;
        }else{
            throw new Exception('faile');
        }
        $rsps['order_sn'] = $data['orderNum'];
        $rsps['othr_number'] = $data['platOrderNum'];
        $rsps['msg'] = $data['msg'] ?? '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    public function get_balance($type){
        $params['merchantCode'] = $this->pay_set['merchantCode'];//商户号
        $params['sign'] = $this->generateSign($params, $this->pay_set['PrivateKey']); // 加密秘钥
        Log::write(['get_balance_create',"SolPay",$params], "payment");
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . '/queryBalance';
        $req = json_encode($params);
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['get_balance_return',"SolPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['platRespCode'] != 'SUCCESS') {
            if($type == 1){
                //抛出错误
                throw new \Exception($res['platRespMessage'], 0);
            }else{
                return  'fail';
            }
        }
        return $res['balance'] ?? '';
    }

}
