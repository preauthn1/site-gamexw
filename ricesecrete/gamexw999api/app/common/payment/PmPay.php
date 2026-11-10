<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class PmPay
{
    private $mer_no = "";
    private $sign_key = "";
    private $gateway = '';
    private $domain = "";
    public function __construct($pay_set,$domain){
        $this->mer_no=$pay_set['mer_no'];
        $this->sign_key=$pay_set['sign_key'];
        $this->gateway=$pay_set['gateway'];
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
        $params['country_code'] = 'IND'; // 国家代码:
        $params['cyy_no'] = 'INR'; // 币种:
        $params['pay_type'] = 'UPI'; // 代收方式:
        $params['notify_url'] = $this->domain.'/notify/PmPay';// 异步通知地址
        $params['callback_url'] = $pay_arr['call_domain'];// 支付提交之后跳转链接		
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create',"PmPay",$params], "payment");
        $url = $this->gateway.'/poi/pay/index/PayOrderCreate';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"PmPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || !isset($res['pay_url'])) {
            throw new \Exception('支付信息错误', 0);
        }
        
        $pay_info['pay_url']=$res['pay_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['mer_no'] =$this->mer_no;//商户号
        $params['mer_order_no'] = $pay_arr['pay_number']; // 商户订单号:  
        $params['order_amount'] = $pay_arr['pay_price']; // 订单金额:
        $params['pay_type'] = 'BANK'; // 国家代码:
        $params['cyy_no'] = 'INR'; // 币种:        
        $params['acc_no'] = $pay_arr['acc_no'];//银行账号
        $params['acc_name'] = $pay_arr['acc_name'];//收款人姓名
        $params['province'] = $pay_arr['ifsc'];//IFSC
        $params['notifyurl'] = $this->domain.'/withdrawal/notify/PmPay';// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"PmPay",$params], "withdrawal");
        $url = $this->gateway.'/poi/dai/index/DaiOrderCreate';
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"PmPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if (empty($res) || $res['code'] != 1) {
            throw new \Exception('提现信息错误', 0);
        }
        return $res['order_number'];
    }

    private function generateSign($data, $signKey){
        $sign_str = $data['mer_no'].$data['mer_order_no'].$signKey;
        return md5($sign_str);
    }
	

    //异步回调支付
    public function notify($post_data){
        $notify_data = $post_data;
        $notify_sign = isset($post_data['sign'])?$post_data['sign']:'';
        if($notify_data['code'] != 1){
            return "faile";
        }
        
        if($notify_data['order_status'] != 4){
            return "faile";
        }
        
        $sign_str =$this->generateSign($notify_data, $this->sign_key);        
        if($sign_str != $notify_sign){
            return "sign error";
        }
        
        $rsps['order_sn'] = $notify_data['mer_order_no'];
        $rsps['othr_number'] = $notify_data['order_no'];
        $rsps['msg'] = "";
        $rsps['result'] = "success";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = isset($post_data['sign'])?$post_data['sign']:'';
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['code'] != 1){
            $rsps['status'] = 2;
        }
        
        if($notify_data['order_status'] != 4){
            $rsps['status'] = 2;
        }
        
        $sign_str =$this->generateSign($notify_data, $this->sign_key);
        if($sign_str != $notify_sign){
            $rsps['status'] = 2;
        }
        $rsps['order_sn']=$notify_data['mer_order_no'];
        $rsps['othr_number']=$notify_data['order_no'];
        $rsps['result'] = "success";
        return $rsps;
    }
}