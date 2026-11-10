<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class BrotherPay
{	
    private $mchId="";
    private $appId="";
    private $host="";
    private $sign_key="";
    private $accountType="";
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->mchId = $pay_set['mchId'];
        $this->appId = $pay_set['appId'];
        $this->host = $pay_set['host'];
        $this->sign_key = $pay_set['sign_key'];
        $this->accountType = isset($pay_set['accountType'])?$pay_set['accountType']:3;
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['mchId'] = $this->mchId;//商户号
        $params['appId'] = $this->appId;
        $params['productId'] = 9008;
        $params['amount'] = $pay_arr['apply_price'] * 100; // 订单金额:
        $params['idNumber'] = $pay_arr['order_sn']; // 商户订单号
        $params['contact'] = $pay_arr['username'];
        $params['notifyUrl'] = $this->domain.'/notify/BrotherPay';// 异步通知地址
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        Log::write(['Submit_create',"BrotherPay",$params], "payment");
        $url = $this->host.'/api/v1/collection/create';
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_create',"BrotherPay",$res], "payment");
        $res=json_decode($res,true);
        if ($res['retCode'] != "SUCCESS") {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']=$res['payUrl'];
        return $pay_info;
    }


    //获取提现信息
    public function create_withdrawal($pay_arr){
        $params['mchId'] =$this->mchId;//商户号
        $params['mchOrderNo'] = $pay_arr['order_sn']; // 商户订单号  
        $params['amount'] = $pay_arr['amount'] * 100; // 订单金额:
        $params['accountType'] = $this->accountType;
        $params['accountName'] = $pay_arr['account_name'];
        $params['accountNo'] = $pay_arr['bank_number'];
        $params['bankCode'] = $pay_arr['bank_code'] ? $pay_arr['bank_code']:$pay_arr['bankName']; //开户名称;
        $params['bankName'] = $pay_arr['bankName'];        
        $params['notifyUrl'] = $this->domain.'/withdrawal/notify/BrotherPay';// 异步通知地址
        $params['remark'] = "transfer accounts";
        $params['reqTime'] = date("YmdHis");
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        
        Log::write(['Submit_withdrawal',"BrotherPay",$params], "withdrawal");
        $url = $this->host.'/api/agentpay/apply';
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"BrotherPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if ($res['retCode'] != "SUCCESS") {
            throw new \Exception('代付信息错误:'.$res['retMsg'], 0);
        }
        return $res['agentpayOrderId'];
    }

	// 签名验证
    private function makeSign($data, $key) {
        //去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a="";
        foreach ($data as $key1 => $val) {
            if ('sign' != $key1 && '' != $val) {
                $string_a = $string_a . $key1 . "=" . $val . "&";
            }
        }
        $string_a = $string_a . "key=" .$key;
        // //签名步骤三：MD5加密
        $sign = strtoupper(md5($string_a));
        return $sign;
    }


    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        if($notify_data['status'] != 2){
            return "faile";
        }
        $sign_key = $this->sign_key;
        $sign_str =$this->makeSign($notify_data, $this->sign_key);
        if($sign_str != $notify_sign){
            return "sign error";
        }
        
        $rsps['order_sn'] = $notify_data['idNumber'];
        $rsps['trade_sn'] = $notify_data['payOrderId'];
        $rsps['result'] = "success";
        $rsps['money'] = $notify_data['amount']/100;
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign=$notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['status'] != 2){
            $rsps['status'] = 2;
        }
        $sign_key = $this->sign_key;
        $sign_str =$this->makeSign($notify_data, $this->sign_key);
        if($sign_str != $notify_sign){
            $rsps['status'] = 2;
        }
        
        $rsps['order_sn'] = $notify_data['mchOrderNo'];
        $rsps['othr_number'] = $notify_data['agentpayOrderId'];
        $rsps['msg'] = $notify_data['transMsg'];
        $rsps['result'] = "success";
        return $rsps;
    }


}