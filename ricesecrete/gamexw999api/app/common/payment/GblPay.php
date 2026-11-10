<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class GblPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['currency'] = $this->pay_set['currency'];//货币
        $params['payerName'] = $pay_arr['username'];
        $params['payerEmail'] = $pay_arr['username']."@in.com";
        $params['payerPhone'] = $pay_arr['phone'] ??'0815648636';
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['subject'] = 'goods'; // 描述
        $params['sub_body'] = 'goods'; // 描述
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $data = [
            'partner'=>$this->pay_set['partner'],
            'input_charset'=>'UTF-8',
            'sign_type'=>'SHA1WITHRSA',
            'sign'=>$this->generateSign($params, $this->pay_set['private_key']),
            'content'=>$this->Rsa_openssl($params, $this->pay_set['public_key']),
        ];
        Log::write(['Submit_create', "GblPay", $data,$params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $data,$params);
        Log::write(['Return_create', "GblPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['is_succ'] != 'T') {
            throw new \Exception($res['fault_reason'] ??'支付信息错误', 0);
        }
        if (!isset($res['response']) || empty($res['response'])) {
            throw new \Exception($res['fault_reason'] ??'支付信息错误', 0);
        }
        $resg = $this->Rsa_openssl($res['response'], $this->pay_set['private_key'],'decode');
        if(!$resg){
            throw new \Exception('支付信息错误', 0);
        }
        if(isset($resg['status']) && $resg['status'] != 'success'){
            throw new \Exception($resg['result'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $resg['code'] ?? '';
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['currency'] = $this->pay_set['currency'];//货币
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['acc_name'] = 'cs123'; //写死
        $params['acc_no'] = $pay_arr['bank_number']; //银行卡号
        $params['acc_type'] = 'THB';
        $params['acc_code'] = $pay_arr['bank_code']; //银行编码
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $data = [
            'partner'=>$this->pay_set['partner'],
            'input_charset'=>'UTF-8',
            'sign_type'=>'SHA1WITHRSA',
            'sign'=>$this->generateSign($params, $this->pay_set['private_key']),
            'content'=>$this->Rsa_openssl($params, $this->pay_set['public_key']),
        ];
        Log::write(['Submit_withdrawal', "GblPay", $data,$params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $data,$params);
        Log::write(['Return_withdrawal', "GblPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['is_succ'] != 'T') {
            throw new \Exception("渠道返回:" . $res['fault_reason'], 0);
        }
        if (!isset($res['response']) || empty($res['response'])) {
            throw new \Exception("代付信息错误", 0);
        }
        $resg = $this->Rsa_openssl($res['response'], $this->pay_set['private_key'],'decode');
        if(!$resg){
            throw new \Exception("代付信息错误", 0);
        }
        if(isset($resg['status']) && $resg['status'] != 'success'){
            throw new \Exception("渠道返回:" . $res['result'] ??'代付信息错误', 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        ksort($data);
        $sign_content = urldecode(http_build_query(myArrayFilter($data)));
        $private_key = "REDACTED";
        openssl_sign($sign_content, $key, $private_key, OPENSSL_ALGO_SHA1);
        return base64_encode($key);

    }

    /*** @description RSA公钥加密 私钥解密 *
     * @param string $data 待加解密数据 *
     * @param string $operate 操作类型 encode:加密 decode:解密
     * @return string 返回加密内容/解密内容
     */
    protected function Rsa_openssl($data,$keys,$operate = 'encode') {
        if( $operate == 'encode'){
            ksort($data);
            $signPars = urldecode(http_build_query(myArrayFilter($data)));
            $public_key = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($keys, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
            $key = openssl_pkey_get_public($public_key);
            if (!$key) {return false; }
            $crypto = '';
            foreach (str_split($signPars, 117) as $chunk) {
                openssl_public_encrypt($chunk, $encryptData, $key);
                $crypto .= $encryptData;
            }
            return base64_encode($crypto);
        }
        if ($operate == 'decode') {
            $private_key = "REDACTED";
            $key = openssl_pkey_get_private($private_key);
            if (!$key) { return false;}
            $crypto = '';
            foreach (str_split(base64_decode($data), 128) as $chunk) {
                openssl_private_decrypt($chunk, $decryptData, $key);
                $crypto .= $decryptData;
            }
            $resg = explode('&',$crypto);
            $resda = [];
            foreach ($resg as $v){
                $code = strstr($v,'code');
                if($code){
                    $resda['code'] = trim($code,'code=');
                }else{
                    $run = explode('=',$v);
                    $str = '';
                    foreach ($run as $k1=>$v1){
                        if($k1 > 0){
                            $str .= $v1;
                        }
                    }
                    $resda[$run[0]] = $str;
                }
            }
            Log::write(['解密内容', "GblPay", $resda], "payment");
            return $resda;
        }
        return false;
    }


    //异步回调支付
    public function notify($data)
    {
        if(!isset($data['response'])){
            return "faile";
        }
        $res = $this->Rsa_openssl($data['response'], $this->pay_set['private_key'],'decode');
        if(!$res){
            return "faile";
        }
        if ($res['pay_status'] != '1') {
            return "faile";
        }
        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        $rsps['money'] = $res['money'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if(!isset($data['response'])){
            $rsps['status'] = 2;
        }
        $res = $this->Rsa_openssl($data['response'], $this->pay_set['private_key'],'decode');
        if(!$res){
            $rsps['status'] = 2;
        }
        if ($res['pay_status'] != '1') {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}