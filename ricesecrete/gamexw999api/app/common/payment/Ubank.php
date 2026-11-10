<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class Ubank
{
    private $merchantNo = "";
    private $sign_key = "";
    private $gateway = '';
    private $type = '';
    private $withdrawal_type = '';
    private $url = '';
    private $withdrawal_url = '';
    private $bank_code = '';
    private $domain = "";
    public function __construct($pay_set,$domain){
        $this->merchantNo = $pay_set['merchantNo'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->type = $pay_set['type'];
        $this->withdrawal_type = $pay_set['withdrawal_type'] ??'';
        $this->url = isset($pay_set['url'])?$pay_set['url']:'/u_payment/create';
        $this->withdrawal_url = $pay_set['withdrawal_url'] ??'';
        $this->bank_code = $pay_set['bank_code'] ??'';
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchantNo'] = $this->merchantNo;//商户号
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号:            
        $params['amount'] = $pay_arr['apply_price']; // 交易数量:
        $params['notifyUrl'] = $this->domain.'/notify/Ubank';// 异步通知地址
        
        if($this->type == "gcash"){
            $params['type'] = $this->type;//
            $params['returnUrl'] = $pay_arr['call_domain'];// 支付提交之后跳转链接
            $params['name'] = $pay_arr['username'];// 持卡人
            $params['phone'] = isset($pay_arr['phone'])?$pay_arr['phone']:"123456789"; // 手机号:
        }else if($this->type == "promptpay"){
            $params['type'] = $this->type;// 交易类型: promptpay
            $params['name'] = $pay_arr['username'];// 付款人姓名
        }else if($this->type == "inr"){
            $params['name'] = $pay_arr['username'];// 持卡人
            $params['phone'] = isset($pay_arr['phone'])?$pay_arr['phone']:$pay_arr['user_id']; // 手机号:
            $params['email'] = $pay_arr['username']."@0s.com";// 持卡人
        }else if($this->type == "ifsc"){
            $params['type'] = "ifsc";
            $params['name'] = $pay_arr['username'];// 持卡人
            $params['phone'] = isset($pay_arr['phone'])?$pay_arr['phone']:$pay_arr['user_id']; // 手机号:
            $params['email'] = $pay_arr['username']."@0s.com";// 持卡人
        }else{
            $params['type'] =  $this->type; // 类型
        }
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create',"Ubank",$params], "payment");
        $url = $this->gateway.$this->url;
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"Ubank",$res], "payment");
        $res = json_decode($res,true);
        if(empty($res) || $res['status'] == 1){
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']=$res['data']['payUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['merchantNo'] =$this->merchantNo;//商户号
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号:  
        $params['amount'] = $pay_arr['amount']; // 订单金额:
        if($this->type == "promptpay"){
            $params['type'] = "bankcard";// 交易类型
            $params['bankCode'] = $pay_arr['bank_code'] ? $pay_arr['bank_code']:$this->bank_code;//IFSC
            $params['bankAccount'] = $pay_arr['bank_number'];//IFSC 
            $params['name'] = $pay_arr['account_name'];//IFSC 
            $url = $this->gateway.'/payout/thb/create';
        }else if($this->type == "gcash"){
            $params['type'] = $this->type;// 交易类型: UTRC,UERC
            $params['name'] = $pay_arr['account_name'];// 持卡人
            $params['phone'] = $pay_arr['bank_number']; // 手机号:
            $url = $this->gateway.'/payout/v1/create';
        }else if($this->type == "inr" || $this->type=="ifsc"){
            $params['type'] = 'ifsc';// 账户类型(ifsc,upi)
            $params['ifsc'] = $pay_arr['ifsc'];//
            $params['bankAccount'] = $pay_arr['bank_number'];// 银行账号
            $params['name'] = $pay_arr['account_name'];// 收款人姓名
            $params['phone'] = $pay_arr['bank_number']; // 收款人手机号
            $params['email'] = $pay_arr['username']."@0s.com";// 收款人邮箱
            $url = $this->gateway.$this->withdrawal_url;
        }else if($this->withdrawal_type){
            $params['type'] = $this->withdrawal_type;// 交易类型
            $params['bankCode'] = $pay_arr['bank_code'] ? $pay_arr['bank_code']:$this->bank_code;//IFSC
            $params['bankAccount'] = $pay_arr['bank_number'];//IFSC
            $params['name'] = $pay_arr['account_name'];//IFSC
            $url = $this->gateway.$this->withdrawal_url;
        }
        
        $params['notifyUrl'] = $this->domain.'/withdrawal/notify/Ubank';// 异步通知地址		
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"Ubank",$params], "withdrawal");
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"Ubank",$res], "withdrawal");
        $res=json_decode($res,true);
        if(empty($res) || $res['status'] == 1){
            throw new \Exception("渠道返回:".$res['msg'], 0);
        }
        return $res['data']['tradeId'];
    }

    private function generateSign($data, $signKey){
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "signKey=".$signKey;
        return strtoupper(md5($str));
    }
	

    //异步回调支付
    public function notify($data){
        $sign = isset($data['sign'])?$data['sign']:'';
        unset($data['sign']);
        if($data['status'] != 1){
            return "faile";
        }
        
        $sign_str =$this->generateSign($data, $this->sign_key);
        if($sign_str != $sign){
            return "sign error";
        }
        $rsps['order_sn'] = $data['outTradeNo'];
        $rsps['trade_sn'] = $data['sn'];
        $rsps['result'] = "success";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data){
        $sign = isset($data['sign'])?$data['sign']:'';
        unset($data['sign']);
        $rsps['status'] = 1;
        if($data['status'] != 1){
            $rsps['status'] = 2;
        }
        
        $sign_str =$this->generateSign($data, $this->sign_key);        
        if($sign_str != $sign){
            $rsps['status'] = 2;
        }
        
        $rsps['order_sn'] = $data['outTradeNo'];
        $rsps['othr_number'] = $data['sn'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}