<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class KakaPay
{	

    private $partner="";
    private $sign_key="";
    private $gateway="";
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->partner = $pay_set['partner'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['partner'] = $this->partner;//商户号
        $params['orderNo'] = $pay_arr['order_sn']; // 商户订单号:            
        $params['amount'] = $pay_arr['apply_price']; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['inType'] = 'E-BANK';
        $params['notifyUrl'] = $this->domain.'/notify/KakaPay';// 异步通知地址
        $params['language'] = "tr-tr";        
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create',"KakaPay",$params], "payment");
        $url = $this->gateway.'/kaka/app/api/in/create';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"KakaPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != 200) {
            throw new \Exception('支付信息错误', 0);
        }

        $pay_info['pay_url']=$res['result']['payWebUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['partner'] = $this->partner;//商户号
        $params['orderNo'] = $pay_arr['order_sn']; // 商户订单号:
        $params['amount'] = $pay_arr['amount']; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['bankCardNo'] = $pay_arr['bank_number'];
        $params['bankCardHolder'] = $pay_arr['account_name'];
        $params['bankName'] = $pay_arr['bankName'];
        $params['notifyUrl'] = $this->domain.'/withdrawal/notify/KakaPay';// 异步通知地址
        
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"KakaPay",$params], "withdrawal");
        $url = $this->gateway.'/kaka/app/api/out/create';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"KakaPay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] !== 200) {
            throw new \Exception("渠道返回:".$res['message'], 0);
        }
        
        return $res['result']['orderId'];
    }

    private function generateSign($data, $signKey){
        // 按照ASCII码升序排序
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "key=".$signKey;
        return strtoupper(md5($str));
    }
	

    //异步回调支付fmpay
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        unset($data['sign']);//不参与签名
        if($data['status'] != 4){
            return "faile";
        }
        $sign_str =$this->generateSign($data, $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            return "sign error";
        }
        
        $rsps['order_sn'] = $data['orderNo'];
        $rsps['trade_sn'] = $data['orderId'];
        $rsps['result'] = "success";
        return $rsps;
    }


    //异步回调支付fmpay
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['status'] != 4){
            $rsps['status'] = 2;
        }
        $sign_str =$this->generateSign($notify_data, $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            $rsps['status'] = 2;
        }
        $rsps['order_sn']=$notify_data['orderNo'];
        $rsps['othr_number']=$notify_data['orderId'];
        $rsps['msg'] = "";
        $rsps['result'] = "success";
        return $rsps;
    }
}