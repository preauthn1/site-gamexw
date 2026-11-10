<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class CuPay
{
    private $mer_no = "";
    private $PrivateKey = "";
    private $url = '';
    private $withdrawal_url = '';
    private $domain = "";
    private $ccy_no = "";
    private $busi_code = "";
    private $bankCode = "";
    private $md5key = "";

    public function __construct($pay_set,$domain){
        $this->mer_no=$pay_set['mer_no'];
        $this->PrivateKey=$pay_set['PrivateKey'];
        $this->url=$pay_set['url'];
        $this->withdrawal_url=$pay_set['withdrawal_url'];
        $this->ccy_no=$pay_set['ccy_no'];
        $this->busi_code=$pay_set['busi_code'];
        $this->bankCode=$pay_set['bankCode'];
        $this->md5key=$pay_set['md5key'];
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['mer_no'] =$this->mer_no;//商户号
        $params['mer_order_no'] = $pay_arr['order_sn']; // 商户订单号:
        $params['pname'] = $pay_arr['username'];// 姓名
        $params['pemail'] = $pay_arr['user_id']."@test.com";// 姓名
        $params['phone'] = $pay_arr['phone'];// 姓名
        $params['order_amount'] = $pay_arr['apply_price']; // 订单金额:
        $params['ccy_no'] = $this->ccy_no; // 币种
        $params['busi_code'] = $this->busi_code; // 支付方式
        $params['notifyUrl'] = $this->domain.'/notify/CuPay';// 异步通知地址
        $params['bankCode'] = $this->bankCode; // 银行编码
        $params['pageUrl'] = $pay_arr['call_domain'];// 支付提交之后跳转链接
        $data = $this->generate($params);
        $params['sign'] = $this->pivate_key_encrypt($data, $this->PrivateKey);
        Log::write(['Submit_create',"CuPay",$params], "payment");
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res = PayRequest::curlS($this->url,$req,$header);
        Log::write(['Return_create',"CuPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['status'] != 'SUCCESS' || !isset($res['order_data'])) {
            throw new \Exception($res['err_msg'] ??'支付信息错误', 0);
        }

        $pay_info['pay_url']=$res['order_data'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['mer_no'] =$this->mer_no;//商户号
        $params['mer_order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['acc_no'] = $pay_arr['bank_number'];//银行账号
        $params['acc_name'] = $pay_arr['account_name'];//收款人姓名
        $params['ccy_no'] = $this->ccy_no; // 币种
        $params['order_amount'] = $pay_arr['amount']; // 订单金额
        $params['bank_code'] = $pay_arr['bank_code']; // 银行编码
        $params['mobile_no'] = empty($pay_arr['phone'])?"1234567890":$pay_arr['phone']; // 收款人手机号
        $params['notifyUrl'] = $this->domain.'/withdrawal/notify/CuPay';// 异步通知地址
        $params['summary'] = $pay_arr['username']; // 备注
        $data = $this->generate($params);
        $params['sign'] = $this->pivate_key_encrypt($data, $this->PrivateKey);
        Log::write(['Submit_withdrawal',"CuPay",$params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res=PayRequest::curlS($this->withdrawal_url,$req,$header);
        Log::write(['Return_withdrawal',"CuPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if (empty($res) || $res['status'] != 'SUCCESS') {
            throw new \Exception($res['err_msg'] ??'代付信息错误', 0);
        }
        return $res['order_no'];
    }

    public function generate($data){
        ksort($data);
        $str = '';
        foreach ($data as $k => $v){
            if(!empty($v)){
                $str .=(string) $k.'='.$v.'&';
            }
        }
        $str = rtrim($str,'&');
        return $str;
    }

    private function generateSign($data){
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= 'key='.$this->md5key;
        return md5($str);
    }


    //异步回调支付fmpay
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        unset($data['sign']);//不参与签名
        if($data['status'] != 'SUCCESS'){
            return "faile";
        }
        $sign_str = $this->generateSign($data);
        if($sign_str != $notify_sign) {
            return "sign error";
        }

        $rsps['order_sn'] = $data['mer_order_no'];
        $rsps['trade_sn'] = $data['order_no'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = isset($data['pay_amount'])?$data['pay_amount']:$data['order_amount'];
        return $rsps;
    }

    //异步回调支付fmpay
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['status'] != 'SUCCESS'){
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($notify_data);
        if($sign_str != $notify_sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn']=$notify_data['mer_order_no'];
        $rsps['othr_number']=$notify_data['order_no'];
        $rsps['msg'] = $notify_data['err_msg'] ??'';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    public function pivate_key_encrypt($data, $pivate_key)
    {

        $pem = chunk_split($pivate_key,64, "\n");
        $pem = "REDACTED\n";
        $pi_key = openssl_pkey_get_private($pem);
        $crypto = '';
        foreach (str_split($data, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $pi_key);
            $crypto .= $encryptData;
        }
        $encrypted = base64_encode($crypto);
        $encrypted = str_replace(['+','/','='],['-','_',''],$encrypted);

        return $encrypted;
    }

}