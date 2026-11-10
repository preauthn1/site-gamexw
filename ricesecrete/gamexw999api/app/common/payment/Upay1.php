<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class Upay1
{
    private $merchantNo = "";
    private $sign_key = "";
    private $gateway = '';
    private $type = '';
    private $domain = "";
    public function __construct($pay_set,$domain){
        $this->merchantNo = $pay_set['merchantNo'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->type = $pay_set['type'];
        $this->url = isset($pay_set['url'])?$pay_set['url']:'/u_payment/create';
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchantNo'] = $this->merchantNo;//商户号
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号:
        $params['type'] = $this->type;// 交易类型: UTRC,UERC
        $params['amount'] = $pay_arr['apply_price']; // 交易数量:
        $params['notifyUrl'] = $this->domain.'/notify/Upay1';// 异步通知地址
        $params['returnUrl'] = $pay_arr['call_domain'];// 支付提交之后跳转链接
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create',"Upay1",$params], "payment");
        $url = $this->gateway.$this->url;
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"Upay1",$res], "payment");
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
            $params['bankCode'] = $pay_arr['bank_code'];//IFSC
            $params['bankAccount'] = $pay_arr['bank_number'];//IFSC
            $params['name'] = $pay_arr['account_name'];//IFSC
            $url = $this->gateway.'/payout/thb/create';
        }else{
            $params['type'] = $this->type;// 交易类型: UTRC,UERC
            $params['address'] = $pay_arr['address'];//IFSC
            $url = $this->gateway.'/u_payout/create';
        }

        $params['notifyUrl'] = $this->domain.'/withdrawal/notify/Upay1';// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"Upay1",$params], "withdrawal");
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"Upay1",$res], "withdrawal");
        $res=json_decode($res,true);
        if(empty($res) || $res['status'] == 1){
            throw new \Exception($res['msg'], 0);
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
        $rsps['money'] = isset($data['payAmount'])?$data['payAmount']:$data['amount'];
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