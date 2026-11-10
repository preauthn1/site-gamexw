<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class BankPay
{

    private $sign_key="";
    private $payType="";
    private $payId="";
    private $appId="";
    private $url="";
    private $withdrawal_url="";
    private $gateway = '';
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->sign_key=$pay_set['sign_key'];
        $this->appId=$pay_set['appId'];
        $this->payType=$pay_set['payType'];
        $this->gateway = $pay_set['gateway'];
        $this->payId=$pay_set['payId'];
        $this->url = $pay_set['url'] ??'/bankPay/crtOrder.do';
        $this->withdrawal_url=$pay_set['withdrawal_url'] ??'/bankPay/crtAgencyOrder.do';
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['appId'] = $this->appId;
        $params['appOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['orderAmt'] = sprintf("%.2f",$pay_arr['apply_price']); // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['payId'] = $this->payType;             //支付类型 101 卡转卡 101 卡转卡 201 卡转GCash 202 GCash转GCash
        $params['notifyURL'] = $pay_arr['notify_url'];// 异步通知地址
        $params['jumpURL'] = $pay_arr['call_domain'];
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        $url= $this->gateway.$this->url;
        Log::write(['Submit_create',"BankPay",$params], "payment");
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"BankPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != '0000') {
            throw new \Exception($res['msg']??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['payUrl'];
        return $pay_info;
    }


    //获取代付信息
    public function create_withdrawal($pay_arr){
        $params['appId'] = $this->appId;
        $params['appOrderNo'] = $pay_arr['order_sn']; //订单号
        $params['orderAmt'] = sprintf("%.2f",$pay_arr['amount']); // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['payId'] = $this->payId;      // 类型 101 银行卡出款 201 GCash出款
        $params['accNo'] = $pay_arr['bank_number']; // 银行卡号
        $params['accName'] = $pay_arr['account_name']; // 姓名
        $params['bankName'] = in_array($this->payId,['101','301']) ? $pay_arr['bankName']:'';
        $params['notifyURL'] = $pay_arr['notify_url'];// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"BankPay",$params], "withdrawal");
        $url= $this->gateway.$this->withdrawal_url;
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"BankPay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] !== '0000') {
            throw new \Exception("渠道返回:".$res['msg'], 0);
        }
        return $res['data']['orderNo'];
    }

    private function generateSign($data, $signKey){
        ksort($data);
        $str = '';
        unset($data['sign'],$data['notifyURL'],$data['jumpURL']);
        foreach ($data as $key => $value) {
            $value = trim($value);
            if($value != ''){
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "key=".$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        unset($data['sign']);//不参与签名
        if($data['orderStatus'] != '00'){
            return "faile";
        }

        $data = [
            'appId' => $this->appId,
            'appOrderNo' => $data['appOrderNo'],
            'orderAmt' => $data['orderAmt'],
            'orderNo' => $data['orderNo'],
            'orderStatus' => $data['orderStatus'],
            'orderTime' => $data['orderTime'],
            'payAmt' => $data['payAmt']
        ];
        $sign_str =$this->generateSign($data, $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            return "sign error";
        }
        $rsps['order_sn'] = $data['appOrderNo'];
        $rsps['trade_sn'] = $data['orderNo'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = isset($data['payAmt'])?$data['payAmt']:$data['orderAmt'];
        return $rsps;
    }


    //代付异步回调支付
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['orderStatus'] != '02'){
            $rsps['status'] = 2;
        }
        $data_sign = [
            "appId" => $this->appId,
            "appOrderNo" => $notify_data['appOrderNo'],
            "orderNo" => $notify_data['orderNo'],
            "orderTime" => $notify_data['orderTime'],
            "orderAmt" => $notify_data['orderAmt'],
            "orderFee" => $notify_data['orderFee'],
            "orderStatus" => $notify_data['orderStatus'],
        ];
        $sign_str =$this->generateSign($data_sign, $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            $rsps['status'] = 2;
        }
        $rsps['order_sn']=$notify_data['appOrderNo'];
        $rsps['othr_number']=$notify_data['orderNo'];
        $rsps['msg'] = "";
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

}