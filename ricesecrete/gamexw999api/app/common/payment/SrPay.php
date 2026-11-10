<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class SrPay
{	

    private $mchNo="";
    private $sign_key="";
    private $appId="";
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->mchNo=$pay_set['mchNo'];
        $this->sign_key=$pay_set['sign_key'];
        $this->appId=$pay_set['appId'];
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['mchNo'] = $this->mchNo;//商户号
        $params['appId'] = $this->appId;
        $params['mchOrderNo'] = $pay_arr['order_sn']; // 商户订单号:            
        $params['wayCode'] = 'FWP_APP';
        $params['amount'] = $pay_arr['apply_price']*100; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['currency'] = 'NGN';
        $params['subject'] = $pay_arr['username'];
        $params['body'] = $pay_arr['username'];
        $params['notifyUrl'] = $this->domain.'/notify/SrPay';// 异步通知地址
        $params['returnUrl'] = $pay_arr['call_domain'];
        $params['reqTime'] = time()*1000;
        $params['clientIp'] = $pay_arr['pay_ip'];
        $params['version'] = '1.0';
        $params['signType'] = "MD5";
        $params['email'] = $pay_arr['user_id']."@in.com";
        $params['paymentOptions'] = "card";
        $params['phonenumber'] = $pay_arr['username'];
        $params['name'] = $pay_arr['username'];
        $params['title'] = $pay_arr['username'];
        $params['description'] = $pay_arr['username'];
        $params['logo'] = "https://assets.piedpiper.com/logo.png";
        $params['sign'] = $this->generateSign($params, $this->sign_key);

        $url='http://papi.faitheasy.com/api/pay/unifiedOrder';
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res = PayRequest::curlS($url,$req,$header);
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != 0) {
            throw new \Exception('支付信息错误', 0);
        }

        if (isset($res['data']['errCode'])) {
            throw new \Exception($res['data']['errMsg'], 0);
        }

        $pay_info['pay_url']=$res['data']['payData'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['mchNo'] = $this->mchNo;//商户号
        $params['appId'] = $this->appId;
        $params['mchOrderNo'] = $pay_arr['order_sn']; // 商户订单号:            
        $params['ifCode'] = 'flutterwavepay';
        $params['entryType'] = 'FW_CASH';
        $params['amount'] = $pay_arr['amount']*100; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['currency'] = 'NGN';
        $params['accountNo'] = $pay_arr['bank_number'];
        $params['accountName'] = $pay_arr['account_name'];
        $params['bankName'] = $pay_arr['bank_code'];
        $params['clientIp'] = $pay_arr['pay_ip'];
        $params['transferDesc'] = "transfer accounts";
        $params['notifyUrl'] = $this->domain.'/withdrawal/notify/SrPay';// 异步通知地址
        $params['reqTime'] = time()*1000;
        $params['version'] = '1.0';
        $params['signType'] = "MD5";
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(["return_withdrawal",$params], "withdrawal");
        $url='http://papi.faitheasy.com/api/transferOrder';
        $res = PayRequest::curlS($url,$params);
        Log::write(["return_withdrawal2",$res], "withdrawal");
        $res = json_decode($res,true);
        $res['msg'] = isset($res['msg'])?$res['msg']:'';
        if($res['msg'] != "SUCCESS"){
            if (empty($res) || $res['code'] !== 0) {
                throw new \Exception("渠道返回:".$res['msg'], 0);
            }
            if($res['data']['state'] != 1){
                throw new \Exception("渠道返回:".$res['msg'], 0);
            }
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
        $rsps['msg'] = "";
        $rsps['result'] = "success";
        return $rsps;
    }	
}