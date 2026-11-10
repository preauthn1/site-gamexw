<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class TopwinPay
{

    private $md5_sign_key="";
    private $appId="";
    private $gateway = '';
    private $merchantplatformid = '';
    private $withdrawal_merchantplatformid = '';
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->appId=$pay_set['appId'];
        $this->gateway = $pay_set['gateway'];
        $this->md5_sign_key = $pay_set['md5_sign_key'];
        $this->merchantplatformid = $pay_set['merchantplatformid'] ??'';
        $this->withdrawal_merchantplatformid = $pay_set['withdrawal_merchantplatformid'] ??'';
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchantid'] = $this->appId;
        $params['timestamp'] = time();     //Md5签名时间戳(10位)
        $params['merchantplatformid'] = $this->merchantplatformid;     //商户第三方配置信息id
        $params['merchantorderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['applyamount'] = $pay_arr['apply_price']; // 申请支付金额
        $params['userName'] = $pay_arr['username']; //付款人姓名
        $params['phoneNum'] = $pay_arr['phone'];    //付款人手机号
        $params['emailAddress'] = $pay_arr['username']."@test.in"; //付款人邮箱
        $params['callbackurl'] = $this->domain.'/notify/TopwinPay';// 异步通知地址
        $params['notifyurl'] = $pay_arr['call_domain'];   //前端重定向地址
        $sign_data = ['merchantid'=> $params['merchantid'],'timestamp'=> $params['timestamp']];
        $params['sign'] = $this->generateSign($sign_data, $this->md5_sign_key);
        Log::write(['Submit_create',"TopwinPay",$params], "payment");
        $url= $this->gateway.'/api/createOrder';
        $res = PayRequest::curlS($url,json_encode($params), ['Content-Type: application/json']);
        Log::write(['Return_create',"TopwinPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != 0) {
            throw new \Exception('支付信息错误', 0);
        }

        $pay_info['pay_url'] = $res['data']['payurl'];
        return $pay_info;
    }


    //获取代付信息
    public function create_withdrawal($pay_arr){
        $params['merchantid'] = $this->appId;
        $params['timestamp'] = time();     //Md5签名时间戳(10位)
        $params['merchantplatformid'] = $this->withdrawal_merchantplatformid;     //商户第三方配置信息id
        $params['merchantorderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['applyamount'] = $pay_arr['amount']; // 申请待付金额
        $params['transferName'] = $pay_arr['account_name']; //接收人姓名
        $params['transferCardNumber'] = $pay_arr['bank_number'];    //接收人银行卡
        $params['transferBankType'] = 'CIB'; //接收人银行类别：CIB，NEQUI
        $params['callbackurl'] = $this->domain.'/withdrawal/notify/TopwinPay';// 异步通知地址
        $sign_data = ['merchantid'=> $params['merchantid'],'timestamp'=> $params['timestamp']];
        $params['sign'] = $this->generateSign($sign_data, $this->md5_sign_key);
        Log::write(['Submit_withdrawal',"TopwinPay",$params], "withdrawal");
        $url= $this->gateway.'/api/createOutOrder';
        $res = PayRequest::curlS($url,json_encode($params), ['Content-Type: application/json']);
        Log::write(['Return_withdrawal',"TopwinPay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] !== 0) {
            throw new \Exception("渠道返回:".$res['msg'], 0);
        }
        return $res['data']['transid'];  //对方平台订单号
    }

    private function generateSign($data, $signKey){
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $value;
            }
        }
        $str .= $signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        unset($data['sign']);//不参与签名
        if($data['status'] != 2){
            return "faile";
        }

        $sign_data = ['merchantid'=>  $this->appId,'timestamp'=> $data['timestamp']];
        $sign_str =$this->generateSign($sign_data, $this->md5_sign_key);
        if($sign_str != $notify_sign){
            return "sign error";
        }
        $rsps['order_sn'] = $data['merchantorderid']; //我方平台订单号
        $rsps['trade_sn'] = $data['orderid'];   //对方平台订单号
        $rsps['result'] = "success";
        return $rsps;
    }


    //代付异步回调支付
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['status'] != 1){
            $rsps['status'] = 2;
        }
        $sign_data = ['merchantid'=>  $this->appId,'timestamp'=> $notify_data['timestamp']];
        $sign_str =$this->generateSign($sign_data, $this->md5_sign_key);
        if($sign_str != $notify_sign){
            $rsps['status'] = 2;
        }
        $rsps['order_sn']= $notify_data['merchantorderid'];  //我方平台订单号
        $rsps['othr_number']= $notify_data['orderid'];  //对方平台订单号
        $rsps['msg'] = "";
        $rsps['result'] = "success";
        return $rsps;
    }

}