<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class FlbPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['orderNum'] = $pay_arr['order_sn'];
        $params['version'] = $this->pay_set["version"];
        $params['charset'] = 'UTF-8';
        $params['random'] = (string)rand(1000, 9999);
        $params['merNo'] = $this->pay_set["merNo"];
        $params['netway'] = $this->pay_set["netway"];
        $params['amount'] = (string)intval($pay_arr['apply_price'] * 100);
        $params['goodsName'] = "User Payment";
        $params['callBackUrl'] = $pay_arr['notify_url']; //ip
        $params['callBackViewUrl'] = $pay_arr['call_domain'];
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);

        $params_data['merchNo'] = $this->pay_set["merNo"]; // 商号号
        $params_data['version'] = $this->pay_set["version"];
        $params_data['data'] = $this->encryption($params, $this->pay_set["public_key"]); // 做签

        Log::write(['Submit_create',"FlbPay",$params,$params_data], "payment");
        $res = PayRequest::curlS($this->pay_set["url"],$params_data);
        Log::write(['Return_create',"FlbPay", $this->pay_set["url"], $res], "payment");

        $res = json_decode($res,true);
        if (empty($res) ||  $res['stateCode'] != "00") {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['qrcodeUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['orderNum'] = $pay_arr['order_sn'];
        $params['version'] = $this->pay_set["version"];
        $params['charset'] = 'UTF-8';
        $params['merNo'] = $this->pay_set["merNo"];
        $params['amount'] = (string)intval($pay_arr['amount'] * 100);
        $params['remitMode'] = "D0";
        $params['callBackUrl'] = $pay_arr['notify_url']; //ip
        $params['bankCode'] = "FLB";//$pay_arr['bank_code'];
        $params['bankAccountName'] = $pay_arr['account_name'];
        $params['bankAccountNo'] = $pay_arr['bank_number'];
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);

        $params_data['merchNo'] = $this->pay_set["merNo"]; // 商号号
        $params_data['version'] = $this->pay_set["version"];
        $params_data['data'] = $this->encryption($params, $this->pay_set["withdrawal_public_key"]); // 做签
        Log::write(['Submit_withdrawal',"FlbPay",$params,$params_data], "withdrawal");
        $res=PayRequest::curlS($this->pay_set["withdrawal_url"],$params_data);
        Log::write(['Return_withdrawal',"FbPay",$res], "withdrawal");
        $res=json_decode($res,true);

        if (empty($res) ||  $res['stateCode'] != "00") {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        return '';
    }

    //异步回调处理
    public function notify($post_data){
        if (!isset($post_data["data"])) {
            return 'faile';
        }

        $order_str = $this->decryption($post_data["data"], $this->pay_set["private_key"]);
        Log::write(['notify_create',"FlbPay",'解密数据',$order_str], "payment");

        $sign = isset($order_str['sign']) ? $order_str['sign'] : '';
        $sign_str = $this->generateSign($order_str, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }

        if ($order_str['payResult'] != "00"){
            return 'faile';
        }

        $rsps['order_sn'] = $post_data["orderNum"];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = isset($order_str['amount'])?$order_str['amount']/100:0;
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        if (!isset($post_data["data"])) {
            return 'faile';
        }

        $rsps = [];
        $rsps['status'] = 1;
        $order_str = $this->decryption($post_data["data"], $this->pay_set["private_key"]);
        Log::write(['notify_withdrawal',"FlbPay",'解密数据',$order_str], "withdrawal");

        $sign = isset($order_str['sign']) ? $order_str['sign'] : '';
        $sign_str = $this->generateSign($order_str, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        if (in_array($order_str['remitResult'], ["1000", '02'])){
            $rsps['status'] = 2;
        }elseif( $order_str['remitResult'] != '00' ){
            return 'faile';
        }

        $rsps['order_sn'] = $order_str["orderNum"];
        $rsps['othr_number'] = "";
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    function encryption($data, $keys)
    {
        $signPars = json_encode($data);//urldecode(http_build_query(myArrayFilter($data)));
        $public_key = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($keys, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
        $key = openssl_pkey_get_public($public_key);
        if (!$key) {return false; }
        $crypto = '';
        foreach (str_split($signPars, 117) as $chunk) {
            openssl_public_encrypt($chunk, $encryptData, $key);
            $crypto .= $encryptData;
        }

        return urlencode(base64_encode($crypto));
    }

    function decryption($data, $keys)
    {
        $private_key = "REDACTED";
        $key = openssl_pkey_get_private($private_key);
        if (!$key) { return false;}
        $crypto = '';
        foreach (str_split(base64_decode($data), 128) as $chunk) {
            openssl_private_decrypt($chunk, $decryptData, $key);
            $crypto .= $decryptData;
        }
        $resda = json_decode($crypto, true);
        Log::write(['解密内容', "FlbPay", $resda], "payment");
        return $resda;
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        foreach ($data as $k => $v){
            if( empty($v) ){
                unset($data[$k]);
            }
        }
        ksort($data);
        $paramStr = utf8_encode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).$signKey;
        Log::write(['Sign_string',"FlbPay", $paramStr], "payment");
        return strtoupper(md5($paramStr));
    }
}