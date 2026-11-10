<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class SrbrlPay
{

    private $mchNo="";
    private $sign_key="";
    private $appId="";
    private $currency="";
    private $gateway = '';
    private $transferType = '';
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->mchNo=$pay_set['mchNo'];
        $this->sign_key=$pay_set['sign_key'];
        $this->appId=$pay_set['appId'];
        $this->currency=$pay_set['currency'];
        $this->gateway = $pay_set['gateway'];
        $this->transferType = $pay_set['transferType'];
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['mchNo'] = $this->mchNo;//商户号
        $params['appId'] = $this->appId;
        $params['mchOrderNo'] = $pay_arr['order_sn']; // 商户订单号:
        $params['amount'] = $pay_arr['apply_price']*100; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['currency'] = $this->currency;
        $params['clientIp'] = $pay_arr['pay_ip'];
        $params['subject'] = $pay_arr['username'];
        $params['body'] = $pay_arr['username'];
        $params['notifyUrl'] = $this->domain.'/notify/SrbrlPay';// 异步通知地址
        $params['returnUrl'] = $pay_arr['call_domain'];
        $params['reqTime'] = time()*1000;
        $params['version'] = '1.0';
        $params['signType'] = "MD5";
        $params['email'] = $pay_arr['username']."@in.com";
        $params['paymentOptions'] = "card";
        $params['phoneNumber'] = $pay_arr['phone'] ??'0815648636';
        $params['name'] = $pay_arr['username'];
        $params['title'] = $pay_arr['username'];
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "SrbrlPay", $params], "payment");
        $url = $this->gateway.'/api/pay/unifiedOrder';
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res = PayRequest::curlS($url,$req,$header);
        Log::write(['Return_create', "SrbrlPay", $res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != 0) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }

        if (isset($res['data']['errCode'])) {
            throw new \Exception($res['data']['errMsg'] ??'支付信息错误', 0);
        }

        $pay_info['pay_url']=$res['data']['payData'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $pix = strtoupper($pay_arr['bankName']);
        $params['mchNo'] = $this->mchNo;//商户号
        $params['appId'] = $this->appId;
        $params['mchOrderNo'] = $pay_arr['order_sn']; // 商户订单号:
        $params['amount'] = $pay_arr['amount']*100; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['currency'] = $this->currency;
        $params['accountNo'] = $pay_arr['bank_number'];
        $params['accountName'] = $pay_arr['account_name'];
        $params['transferType'] = $this->transferType;
        if($this->transferType == 'Card'){
            $params['bankName'] = $pay_arr['bank_code'];
        }
        $params['version'] = '1.0';
        $params['signType'] = "MD5";
        $params['email'] = $pay_arr['username']."@in.com";
        $params['phoneNumber'] = $pay_arr['phone'] ??'0815648636';
        $params['clientIp'] = $pay_arr['pay_ip'];
        $params['cardType'] = $pix;
        $params['panNum'] = $pix == 'CPF' ? $pay_arr['bank_number']:'';
        $params['transferDesc'] = "transfer accounts";
        $params['notifyUrl'] = $this->domain.'/withdrawal/notify/SrbrlPay';// 异步通知地址
        $params['reqTime'] = time()*1000;
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal', "SrbrlPay", $params], "withdrawal");
        $url= $this->gateway.'/api/transferOrder';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal', "SrbrlPay", $res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] !== 0) {
            throw new \Exception("渠道返回:".$res['msg'] ??'', 0);
        }
        if($res['data']['state'] != 1){
            throw new \Exception("渠道返回:".$res['data']['errMsg'] ??'', 0);
        }
        return $res['data']['transferId'];
    }

    private function generateSign($data, $signKey){
        // 按照ASCII码升序排序
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "key=".$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付fmpay
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        unset($data['sign']);//不参与签名
        if($data['state'] != 2){
            return "faile";
        }
        $sign_str =$this->generateSign($data, $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            return "sign error";
        }

        $rsps['order_sn'] = $data['mchOrderNo'];
        $rsps['trade_sn'] = $data['payOrderId'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ? $data['amount']/100:0;
        return $rsps;
    }


    //异步回调支付fmpay
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['state'] != 2){
            $rsps['status'] = 2;
        }
        $sign_str =$this->generateSign($notify_data, $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            $rsps['status'] = 2;
        }
        $rsps['order_sn']=$notify_data['mchOrderNo'];
        $rsps['othr_number']=$notify_data['transferId'];
        $rsps['msg'] = $notify_data['errMsg'] ??'';
        $rsps['result'] = "success";
        return $rsps;
    }
}