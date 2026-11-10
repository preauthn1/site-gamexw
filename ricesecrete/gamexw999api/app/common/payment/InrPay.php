<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class InrPay
{
    private $merchantNo = "";
    private $sign_key = "";
    private $gateway = '';
    private $type = '';
    private $url = '';
    private $withdrawal_url = '';
    private $domain = "";
    public function __construct($pay_set,$domain){
        $this->merchantNo = $pay_set['merchantNo'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->type = $pay_set['type'];
        $this->url = $pay_set['url'];
        $this->withdrawal_url = $pay_set['withdrawal_url'] ??'';
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchantNo'] = $this->merchantNo;//商户号
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号:
        $params['amount'] = $pay_arr['apply_price']; // 交易数量:
        $params['notifyUrl'] = $this->domain.'/notify/InrPay';// 异步通知地址
        $params['name'] = $pay_arr['username'];// 持卡人
        $params['phone'] = isset($pay_arr['phone'])?$pay_arr['phone']:$pay_arr['user_id']; // 手机号:
        $params['email'] = $pay_arr['username']."@0s.com";// 持卡人
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create',"InrPay",$params], "payment");
        $url = $this->gateway.$this->url;
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"InrPay",$res], "payment");
        $res = json_decode($res,true);
        if(empty($res) || $res['status'] != 0){
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
        $params['type'] = 'ifsc';// 账户类型(ifsc,upi)
        $params['ifsc'] = $pay_arr['ifsc'];//
        $params['bankAccount'] = $pay_arr['bank_number'];// 银行账号
        $params['name'] = $pay_arr['account_name'];// 收款人姓名
        $params['phone'] = $pay_arr['bank_number']; // 收款人手机号
        $params['email'] = $pay_arr['username']."@0s.com";// 收款人邮箱
        $url = $this->gateway.$this->withdrawal_url;
        $params['notifyUrl'] = $this->domain.'/withdrawal/notify/InrPay';// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"InrPay",$params], "withdrawal");
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"InrPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if(empty($res) || $res['status'] != 0){
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