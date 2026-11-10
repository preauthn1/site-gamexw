<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class FivePay
{
    private $merchantNo="";
    private $sign_key="";
    private $gateway = '';
    private $create_channelCode = '';
    private $withdrawal_channelCode = '';
    private $currency = '';
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->merchantNo = $pay_set['merchantNo'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->create_channelCode = $pay_set['create_channelCode'];
        $this->withdrawal_channelCode = $pay_set['withdrawal_channelCode'];
        $this->currency = $pay_set['currency'] ??'NGN';
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchantNo'] = $this->merchantNo;//商户号
        $params['version'] = "1.0"; //版本号，固定传：1.0
        $params['merchantOrderId'] = $pay_arr['order_sn']; // 商户订单号
        $params['channelCode'] = $this->create_channelCode; // 通道编码
        $params['amount'] = $pay_arr['apply_price']*100; // 订单金额
        $params['currency'] = $this->currency; // 货币代码
        $params['subject'] = $pay_arr['username'];
        $params['email'] = $pay_arr['username']."@gmail.com"; // 邮箱
        $params['userName'] = $pay_arr['username']; // 姓名
        $params['mobileNo'] = empty($pay_arr['phone'])?"123457890":$pay_arr['phone']; // 手机号
        $params['notifyUrl'] = $pay_arr['notify_url'];// 异步通知地址
        $params['expireTime'] = 30;
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        Log::write(['Submit_create',"FivePay",$params], "payment");
        $url= $this->gateway.'/payin/unifiedorder.do';
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        if(strlen($params['mobileNo']) < 10 || !is_numeric($params['mobileNo'])){
            throw new \Exception('手机号格式不正确', 0);
        }
        $res = PayRequest::curlS($url,$req,$header);
        Log::write(['Return_create',"FivePay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != '000') {
            throw new \Exception($res['msg'], 0);
        }
        if (isset($res['data']) && $res['data']['status'] == 'FAIL') {
            throw new \Exception($res['msg'], 0);
        }
        if (!isset($res['data']['checkStand'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']=$res['data']['checkStand'];
        return $pay_info;
    }


    //获取提现信息
    public function create_withdrawal($pay_arr){
        $params['merchantNo'] = $this->merchantNo;//商户号
        $params['version'] = "1.0"; //版本号，固定传：1.0
        $params['merchantOrderId'] = $pay_arr['order_sn']; // 商户订单号
        $params['channelCode'] = $this->withdrawal_channelCode; // 通道编码
        $params['amount'] = $pay_arr['amount']*100; // 订单金额
        $params['currency'] = $this->currency; // 货币代码
        $params['email'] = $pay_arr['username']."@gmail.com"; // 收款人邮箱
        $params['userName'] = $pay_arr['account_name']; // 收款人姓名
        $params['mobileNo'] = $pay_arr['phone']; // 收款人手机号
        $pay_data = [];
        if($this->currency == 'EUR'){
            $pay_data = [
                'countryOrigin'=>'EUR',
                'swift'=>$pay_arr['ifsc'],      //银行代码
                'bankName'=>$pay_arr['bankName'],
                'iban'=>$pay_arr['bank_number'],
            ];
        }else if($this->currency == 'NGN'){
            $pay_data = [
                'cardNumber'=>$pay_arr['bank_number'],
                'bankCode'=>$pay_arr['bank_code']
            ];
        }else if($this->currency == 'INR'){
            $pay_data = [
                'bankName'=>$pay_arr['bankName'],
                'cardNumber'=>$pay_arr['bank_number'],
                'ifsc'=>$pay_arr['ifsc'],
            ];
        }

        $params['bankInfo'] = json_encode($pay_data);
        $params['notifyUrl'] = $pay_arr['notify_url'];// 异步通知地址
        $params['expireTime'] = 30;
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"FivePay",$params], "withdrawal");
        $url= $this->gateway.'/payout/unifiedorder.do';
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res = PayRequest::curlS($url,$req,$header);
        Log::write(['Return_withdrawal',"FivePay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != '000') {
            throw new \Exception($res['msg'], 0);
        }
        if (isset($res['data']) && $res['data']['status'] == 'FAIL') {
            throw new \Exception($res['msg'], 0);
        }
        return $res['data']['sysOrderId'];
    }

    // 签名验证
    private function makeSign($data, $key) {
        ksort($data);
        $str = '';
        foreach ($data as $k => $v){
            if($v != '' && $k != 'sign' && $k != 'bankInfo' && $k != 'version' && $k != 'subject' && $k != 'errCode' && $k != 'errMsg'){
                $str .= $k."=".$v."&";
            }
        }
        $str .= "key=".$key;
        $sign_str = hash_hmac("sha256", $str, $key);
        return $sign_str;
    }


    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        if($notify_data['status'] != 'SUCCESS'){
            return "faile";
        }
        $sign_str =$this->makeSign($notify_data, $this->sign_key);
        if($sign_str != $notify_sign){
            return "sign error";
        }

        $rsps['order_sn'] = $notify_data['merchantOrderId'];
        $rsps['trade_sn'] = $notify_data['sysOrderId'];
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign=$notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['status'] != 'SUCCESS'){
            $rsps['status'] = 2;
        }
        $sign_str =$this->makeSign($notify_data, $this->sign_key);
        if($sign_str != $notify_sign){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $notify_data['merchantOrderId'];
        $rsps['othr_number'] = $notify_data['sysOrderId'];
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }


}