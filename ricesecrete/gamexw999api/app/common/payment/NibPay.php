<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class NibPay
{
    private $merchant_id = '';  //商户ID
    private $key ='';
    private $currency ='';
    private $country ='';
    private $payUrl = 'https://api.nibpay.com';
    private $domain='';
    private $channel='';
    private $channels ='';

    public function __construct($pay_set,$domain){
        $this->merchant_id=$pay_set['merchant_id'];
        $this->key=$pay_set['key'];
        $this->currency=$pay_set['currency'];
        $this->country=$pay_set['country'];
        $this->channel = $pay_set['channel']??'';
        $this->channels = $pay_set['channels']??'';
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['service'] = 'App.Pay.GatewayPay'; // 接口名称
        $params['merchant_id'] = $this->merchant_id; // 商户号
        $params['amount'] = $pay_arr['apply_price']; // 金额
        $params['out_order_id'] = $pay_arr['order_sn']; // 订单号
        $params['nonce'] = $this->getNonce(); // 随机字符串
        $params['timestamp'] = time(); // 时间戳
        $params['notify_url'] = $this->domain.'/notify/NibPay';// 异步通知地址
        $params['pay_currency'] = $this->currency; // USDT/BTC; // 支付币种
        $params['buy_currency'] = 'USDT'; // 购买币种
        if (!empty($this->channel)){
            $params['channel'] = $this->channel;
        }
        if (!empty($this->channels)){
            $params['channels'] = $this->channels;
        }
        $params['sign'] = $this->sign($params, $this->key);
        Log::write(['Submit_create',"NibPay",$params], "payment");
        $url=$this->payUrl;
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"NibPay",$res], "payment");
        $res=json_decode($res,true);
        if ($res['ret'] != 200) {
            throw new \Exception($res['msg']??'支付信息错误', 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']= $res['data']['gateway_url'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['service'] = 'App.Payout.Payout'; // 接口名称
        $params['nonce'] = $this->getNonce(); // 随机字符串
        $params['timestamp'] = time(); // 时间戳
        $params['merchant_id'] = $this->merchant_id; // 商户号
        $params['out_order_id'] = $pay_arr['order_sn']; //商家自己平台的订单号

        $params['receive_type'] = 'BANK';
        $params['receive_currency'] = $this->currency;
        $params['receive_country'] = $this->country;

        $params['amount'] = $pay_arr['amount']; // 订单金额:
        $params['first_name'] = $pay_arr['first_name'];
        $params['last_name'] = $pay_arr['last_name'];
        $params['location_id'] = $pay_arr['location_id'];
        $params['bank_id'] = $pay_arr['bank_code'];
        $params['bank_account_number'] = $pay_arr['bank_number'];
        $params['notify_url'] = $this->domain.'/withdrawal/notify/NibPay';// 异步通知地址

        $params['sign'] = $this->sign($params, $this->key);
        Log::write(['Submit_withdrawal',"NibPay",$params], "withdrawal");
        $url = $this->payUrl;
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal',"NibPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if ($res['ret'] != 200) {
            throw new \Exception($res['msg']??'代付信息错误', 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception('代付信息错误', 0);
        }
        return $res['data']['transactionid'];
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['sign']??"";
        if ($sign!=$this->sign($notify_data,$this->key)){
            return 'error';
        }
        $order_status = $notify_data['order_status'];
        if (!isset($order_status)||$order_status!=1) {
            return 'error';
        }
        
        $rsps['order_sn'] = $notify_data['out_order_id'];
        $rsps['trade_sn'] = $notify_data['order_id'];
        $rsps['result'] = "success";
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['sign']??"";
        $rsps = [];
        $rsps['status'] = 1;
        if ($sign!=$this->sign($notify_data,$this->key)){
            $rsps['status'] = 0;
        }
        $order_status = $notify_data['order_status'];
        if (!isset($order_status)) {
            $rsps['status'] = 0;
        }else{
            if ($order_status==1){
                $rsps['status'] = 1;
            }else if($order_status==2){
                $rsps['status'] = 2;
            }else{
                $rsps['status'] = 0;
            }
        }
        
        $rsps['order_sn'] = $notify_data['out_order_id'];
        $rsps['othr_number'] = $notify_data['order_id'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }

    /* 生成随机数*/
    private function getNonce($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }

        return $str;
    }

    /*生成签名*/
    public function sign($values, $secret)
    {
        //签名步骤一：按字典序排序参数
        ksort($values);
        $buff = '';
        foreach ($values as $k => $v)
        {
            if($k != "sign" && !is_array($v)){
                $buff .= $k . '=' . $v . '&';
            }
        }
        $string = trim($buff, '&');
        //签名步骤二：在string后加入KEY
        $string = $string . '&secret=' . $secret;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }
}