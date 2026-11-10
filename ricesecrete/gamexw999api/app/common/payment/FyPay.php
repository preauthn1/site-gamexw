<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class FyPay
{
    private $uid = '';  //商户ID
    private $key ='';  //商户的Keyd在商户后台可以获取
    private $payUrl = 'https://api.fy13.support';
    private $channel = '';
    private $withdrawal_channel = '';
    private $domain='';

    public function __construct($pay_set,$domain){
        $this->uid=$pay_set['uid'];
        $this->key=$pay_set['key'];
        $this->channel=$pay_set['channel'];
        $this->withdrawal_channel=$pay_set['withdrawal_channel'] ??712;
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['uid'] = $this->uid;
        $params['orderid'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['channel'] = $this->channel;
        $params['notify_url'] = $this->domain.'/notify/FyPay';// 异步通知地址
        $params['return_url'] = $pay_arr['call_domain'];
        $params['amount'] = $pay_arr['apply_price']; // 订单金额:
        $params['userip'] = $pay_arr['pay_ip'];
        $params['timestamp'] = time()*1000;
        $params['custom'] = '';
        $params['sign'] = $this->sign($params);
        Log::write(['Submit_create',"FyPay",$params], "payment");
        $url=$this->payUrl.'/pay';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"FyPay",$res], "payment");
        $res=json_decode($res,true);
        if ($res['status'] != 10000) {
            throw new \Exception($res['status']??'支付信息错误', 0);
        }
        if (!isset($res['result'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']= $res['result']['payurl'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['uid'] = $this->uid;
        $params['orderid'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['channel'] = $this->withdrawal_channel;
        $params['notify_url'] = $this->domain.'/withdrawal/notify/FyPay';// 异步通知地址
        $params['amount'] = $pay_arr['amount']; // 订单金额:
        $params['userip'] = $pay_arr['pay_ip'];
        $params['timestamp'] = time()*1000;
        $params['custom'] = '';

        $params['bank_account'] = $pay_arr['account_name'];
        $params['bank_no'] = $pay_arr['bank_number'];
        $params['bank_id'] = $pay_arr['bank_code'];
        $params['bank_province'] = $pay_arr['address'];
        $params['bank_city'] = $pay_arr['address'];
        $params['bank_sub'] = $pay_arr['info']['bank_open'];
        $params['sign'] = $this->sign($params);
        Log::write(['Submit_withdrawal',"FyPay",$params], "withdrawal");
        $url = $this->payUrl.'/applyfor';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal',"FyPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if ($res['status'] != 10000) {
            throw new \Exception($res['status']??'代付信息错误', 0);
        }
        if (!isset($res['result'])) {
            throw new \Exception('代付信息错误', 0);
        }
        return $res['result']['transactionid'];
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['sign']??"";
        $status = $notify_data['status']??0;
        $result = $notify_data['result']??"";
        if ($sign!=$this->sign(array('status'=>$status,'result'=>$result))){
            return 'error';
        }
        if ($status!=10000) {
            return 'error';
        }
        $result = json_decode($result,true);        
        $rsps['order_sn'] = $result['orderid'];
        $rsps['trade_sn'] = $result['transactionid'];
        $rsps['result'] = "success";
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['sign']??"";
        $status = $notify_data['status']??0;
        $result = $notify_data['result']??"";
        $rsps = [];
        if ($sign!=$this->sign(array('status'=>$status,'result'=>$result))){
            $rsps['status'] = 0;
            return $rsps;
        }
        if ($status==10000) {
            $rsps['status'] = 1;
        }else{
            $rsps['status'] = 2;
        }
        $result = json_decode($result,true);
        $rsps['order_sn'] = $result['orderid'];
        $rsps['othr_number'] = $result['transactionid'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }

    // 签名验证
    private function sign($data) {
        // 按照ASCII码升序排序
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if ('sign' != $key) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .='key='.$this->key;
        return strtoupper(md5($str));
    }
}