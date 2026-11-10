<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class GuaranaPay
{
    private $payin_appId = '';
    private $payin_appSecret = '';
    private $payout_appId = '';
    private $payout_appSecret = '';
    private $pmId = '';
    private $payUrl = '';
    private $pix = false;
    private $domain="";

    public function __construct($pay_set,$domain){
        $this->payin_appId=$pay_set['payin_appId'];
        $this->payin_appSecret=$pay_set['payin_key'];
        $this->payout_appId=$pay_set['payout_appId'];
        $this->payout_appSecret=$pay_set['payout_key'];
        $this->pmId=$pay_set['pmId'];
        $this->payUrl=$pay_set['url']??'https://gateway.guaranapay.com';
        $this->pix = isset($pay_set['pix']);
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['appId'] = $this->payin_appId; // appId
        $params['amount'] = $pay_arr['apply_price']; // 订单金额:
        $params['country'] = 'BR';
        $params['currency'] = 'BRL';
        $params['merTransNo'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['returnUrl'] = $pay_arr['call_domain'];
        $params['notifyUrl'] = $this->domain.'/notify/GuaranaPay';// 异步通知地址
        $params['sign'] = $this->sign($params, $this->payin_appSecret);
        Log::write(['Submit_create',"GuaranaPay",$params], "payment");
        $url=$this->payUrl.'/pg/dk/order/create';
        $res = PayRequest::curlS($url,json_encode($params),array('Content-Type: application/json'));
        Log::write(['Return_create',"GuaranaPay",$res], "payment");
        $res=json_decode($res,true);
        if ($res['code'] != 200) {
            throw new \Exception('支付信息错误', 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $data = $res['data'];
        if (!isset($data['resultCode']) || $data['resultCode']!='0000') {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']= $data['url'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['appId'] = $this->payout_appId; // appId
        $params['amount'] = $pay_arr['amount']; // 订单金额:
        $params['currency'] = 'BRL';
        $params['merTransNo'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['notifyUrl'] = $this->domain.'/withdrawal/notify/GuaranaPay';// 异步通知地址
        if ($this->pix){
            $params['pmId'] = $pay_arr['bankName']?strtoupper($pay_arr['bankName']):"";
            if ($params['pmId'] == 'PHONE'){
                $pixKey = $pay_arr['bank_number'];
                if (str_starts_with($pixKey, "+")) {
                    $pixKey = substr($pixKey,1);
                }
                if (strlen($pixKey)>11 && str_starts_with($pixKey,"55")){
                    $pixKey = substr($pixKey,2);
                }
                $pay_arr['bank_number'] = "+55".$pixKey;
            }
            $params['extInfo'] = array(
                "accountNumber" => $pay_arr['bank_number'],
                "accountHolderName" => $pay_arr['account_name'],
                "document" => $pay_arr['bank_number']
            );
        }else{
            $params['pmId'] = $this->pmId;
            $params['extInfo'] = array(
                "bankCode" => $pay_arr['ifsc'],
                "accountNumber" => $pay_arr['bank_number'],
                "accountHolderName" => $pay_arr['account_name'],
                "payeePhone" => $pay_arr['phone'] ? $pay_arr['phone']:"+5511775825869",
                "payeeEmail" => $pay_arr['user_id']."@test.in",
                "payeeAddress" => $pay_arr['address'],
            );
        }

        $params['sign'] = $this->sign($params, $this->payout_appSecret);
        Log::write(['Submit_withdrawal',"GuaranaPay",$params], "withdrawal");
        $url = $this->payUrl.'/pg/dk/payout/create';
        $res = PayRequest::curlS($url, json_encode($params), array('Content-Type: application/json'));
        Log::write(['Return_withdrawal',"GuaranaPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if (is_null($res) || $res['code'] == 500)
            return $pay_arr['order_sn'];
        if ($res['code'] != 200) {
            throw new \Exception('渠道返回：'.$res['msg'], 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception('渠道返回：'.$res['msg'], 0);
        }
        $data = $res['data'];
        if ($data['resultCode']!='0000'){
            throw new \Exception('渠道返回：'.$data['message'], 0);
        }
        return $pay_arr['order_sn'];
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['sign'];
        unset($notify_data['sign']);
        if ($notify_data['transStatus'] != 'success') {
            return 'faile';
        }
        if($notify_data['amount'] != $notify_data['processAmount']){
            return 'faile';
        }

        if ($sign!=$this->sign($notify_data,$this->payin_appSecret)){
            return 'faile';
        }
        
        $rsps['order_sn'] = $notify_data['merTransNo'];
        $rsps['trade_sn'] = $notify_data['transNo'];
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
        if ($notify_data['tradeStatus'] != 'success') {
            $rsps['status'] = 2;
        }
        if ($sign!=$this->sign($notify_data,$this->payout_appSecret)){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $notify_data['merTransNo'];
        $rsps['othr_number'] = $notify_data['merTransNo'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
        
    }

    // 签名验证
    private function sign($data, $appSecret) {
        $signStr = $this->joinMap($data);
        $signStr .= '&key=' . $appSecret;
        return hash('sha256', $signStr);
    }

    private function joinMap($map) {
        if (!is_array($map)) {
            return '';
        }
        if (array_key_exists('sign', $map)) {
            unset($map['sign']);
        }
        ksort($map);
        reset($map);
        $pair = array();
        foreach($map as $key => $value) {
            if ($this->isIgnoredItem($key, $value)) {
                continue;
            }
            $tmp = $key . '=';
            if(0 === strcmp('extInfo', $key)) {
                $tmp .= $this->joinMap($value);
            } else {
                $tmp .= $value;
            }
            $pair[] = $tmp;
        }
        if (empty($pair)) {
            return '';
        }
        return join('&', $pair);
    }

    private static function isIgnoredItem($key, $value) {
        if (empty($key) || empty($value)) {
            return true;
        }
        if (0 === strcmp('sign', $key)) {
            return true;
        }
        if (0 === strcmp('extInfo', $key)) {
            return false;
        }
        if (is_string($value)) {
            return false;
        }
        if (is_numeric($value)) {
            return false;
        }
        if (is_bool($value)) {
            return false;
        }
        return true;
    }
}