<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class ShionPay
{
    private $merchantNo = '';  //商户ID
    private $key ='';
    private $ptype ='';
    private $wtype ='';
    private $domain="";
    private $country_code="";
    public function __construct($pay_set,$domain){
        $this->merchantNo=$pay_set['merchantNo'];
        $this->key=$pay_set['key'];
        $this->ptype=$pay_set['ptype']??"bankcard";
        $this->wtype=$pay_set['wtype']??"bankcard";
        $this->country_code=$pay_set['country_code'];
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchantNo'] = $this->merchantNo;
        $params['type'] = $this->ptype;
        $params['outTradeNo'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['amount'] = $pay_arr['apply_price']; // 订单金额:
        $params['notifyUrl'] =  $this->domain.'/notify/ShionPay';
        $params['sign'] = $this->sign($params,$this->key);
        Log::write(['Submit_create',"ShionPay",$params], "payment");
        $url='https://vn2zfapi.shion.vip/payment/'.$this->country_code.'/create';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"ShionPay",$res], "payment");
        $res=json_decode($res,true);
        if ($res['status'] != 0) {
            throw new \Exception('支付信息错误', 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']= $res['data']['payUrl'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['merchantNo'] = $this->merchantNo;
        $params['outTradeNo'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['amount'] = $pay_arr['amount']; // 订单金额:
        $params['type'] = $this->wtype;
        $params['bankCode'] = $pay_arr['bank_code'];
        $params['bankAccount'] =  $pay_arr['bank_number'];
        $params['name'] = $pay_arr['bank_account'];
        $params['notifyUrl'] =  $this->domain.'/withdrawal/notify/ShionPay';
        $params['sign'] = $this->sign($params,$this->key);
        Log::write(['Submit_withdrawal',"ShionPay",$params], "withdrawal");
        $url = 'https://vn2zfapi.shion.vip/payout/'.$this->country_code.'/create';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal',"ShionPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if ($res['status'] != 0) {
            throw new \Exception($res['msg']??'代付信息错误', 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception('代付信息错误', 0);
        }
        return $res['data']['tradeId'];
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['sign'];
        unset($notify_data['sign']);
        if ($sign!=$this->sign($notify_data, $this->key)){
            return 'error';
        }
        if (!isset($notify_data["status"]) || $notify_data["status"]!=1) {
            return 'error';
        }
        if(!isset($notify_data['outTradeNo']) || empty($notify_data['outTradeNo'])) {
            return 'error';
        }
        
        $rsps['order_sn'] = $notify_data['outTradeNo'];
        $rsps['trade_sn'] = $notify_data['sn'];
        $rsps['result'] = "success";
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['sign'];
        unset($notify_data['sign']);
        $rsps = [];
        $rsps['status'] = 1;
        //status 0处理中 1成功 2失败 3挂起 4审核中 5待提交
        if ($sign != $this->sign($notify_data, $this->key)){
            $rsps['status'] = 2;
        }else{
            if (!isset($notify_data["status"])) {
                $rsps['status'] = 0;
            }
            $status = $notify_data["status"];
            if ($status==1){
                $rsps['status'] = 1;
            }else if($status==2){
                $rsps['status'] = 2;
            }else{
                $rsps['status'] = 0;
                return $rsps;
            }
        }        
        $rsps['order_sn'] = $notify_data['outTradeNo'];
        $rsps['othr_number'] = $notify_data['sn'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }

    // 签名验证
    private function sign($data, $signKey){
        // 按照ASCII码升序排序
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

}