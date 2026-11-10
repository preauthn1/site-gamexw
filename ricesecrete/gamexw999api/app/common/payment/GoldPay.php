<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class GoldPay
{	
    private $merchant="";
    private $sign_key="";
    private $url="";
    private $mode="";
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->merchant=$pay_set['merchant'];
        $this->sign_key=$pay_set['pay_key'];
        $this->url=$pay_set['url'];
        $this->mode=$pay_set['mode'] ??'IMPS';
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchant'] =$this->merchant;//商户号
        $params['orderId'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['apply_price']; // 订单金额:
        $params['customName'] = $pay_arr['username']; // 
        $params['customMobile'] = $pay_arr['phone']; 
        $params['customEmail'] = $pay_arr['username']."@test.in";
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['callbackUrl'] = $pay_arr['call_domain'];// 同步回调
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        Log::write(['Submit_create',"GoldPay",$params], "payment");
        $url= $this->url.'/openApi/pay/createOrder';
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_create',"GoldPay",$res], "payment");
        $res=json_decode($res,true);
        if (empty($res) && $res['code'] != 200) {
            throw new \Exception($res['errorMessages']??'支付信息错误', 0);
        }
        if (!isset($res['data']['url'])) {
                throw new \Exception($res['errorMessages']??'支付信息错误', 0);
        }
        $pay_info['pay_url']=$res['data']['url'];
        return $pay_info;
    }


    //获取提现信息
    public function create_withdrawal($pay_arr){
        $params['merchant'] = $this->merchant; //商户号
        $params['orderId'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['amount']; // 订单金额:
        $params['customName'] = $pay_arr['account_name']; // 姓名
        $params['customMobile'] = empty($pay_arr['phone'])?"1234567890":$pay_arr['phone']; // 手机号
        $params['customEmail'] = $pay_arr['user_id'] . "@test.in"; // 邮箱
        $params['bankAccount'] = $pay_arr['bank_number']; // 银行账号
        $params['ifscCode'] = $pay_arr['ifsc']; // IFSC
        $params['mode'] = $this->mode;
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"GoldPay",$params], "withdrawal");
        $url = $this->url.'/openApi/payout/createOrder';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal',"GoldPay",$res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) && $res['code'] != 200) {
            throw new \Exception($res['errorMessages']??'支付信息错误', 0);
        }
        if (!isset($res['data']['platOrderId'])) {
            throw new \Exception($res['errorMessages'] ?? '代付信息错误', 0);
        }
        return $res['data']['platOrderId'];
    }

    // 签名验证
    private function makeSign($data,$key) {
        //去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = urldecode(http_build_query($data));
        //签名步骤二：在string后加入KEY
        $string_sign_temp = $string_a . "&key=" .$key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        return $sign;
    }


    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $notify_sign=$notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        if($notify_data['status'] != "PAY_SUCCESS"){
            return "faile";
        }
        $sign_str =$this->makeSign($notify_data, $this->sign_key);
        if($sign_str != $notify_sign){
            return "sign error";
        }
        
        $rsps['order_sn'] = $notify_data['orderId'];
        $rsps['trade_sn'] = $notify_data['platOrderId'];
        $rsps['result'] = "ok";
        $rsps['money'] = $notify_data['amount'];
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign'] ?? '';
        unset($notify_data['sign']); //不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if ($notify_data['status'] != "PAY_SUCCESS") {
            $rsps['status'] = 2;
        }

        $sign_str = $this->makeSign($notify_data, $this->sign_key);
        if ($sign_str != $notify_sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $notify_data['orderId'];
        $rsps['othr_number'] = $notify_data['platOrderId'];
        $rsps['msg'] = '';
        $rsps['result'] = "ok";
        return $rsps;
    }
}