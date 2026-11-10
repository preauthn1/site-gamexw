<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class DfPay
{

    private $memberid="";
    private $sign_key="";
    private $gateway = '';
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->memberid=$pay_set['memberid'];
        $this->sign_key=$pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->domain=$domain;
    }


    //获取代付信息
    public function create_withdrawal($pay_arr){
        $params['memberid'] = $this->memberid;   //商户编号
        $params['orderid'] = $pay_arr['order_sn']; //订单号
        $params['paycode'] = 3;      // 支付编号
        $params['amount'] = $pay_arr['amount']; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['payname'] = $pay_arr['account_name']; // 姓名
        $params['paynumber'] = $pay_arr['bank_number']; // 银行卡号
        $params['ifsccode'] =  $pay_arr['ifsc']; //ifsc code
        $params['notifyurl'] = $this->domain.'/withdrawal/notify/DfPay';// 异步通知地址
        $params['returnurl'] = $this->domain.'/withdrawal/notify/DfPay';  //同步跳转URL
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"DfPay",$params], "withdrawal");
        $url= $this->gateway.'/v1/dfpay';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"DfPay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] !== 200) {
            throw new \Exception("渠道返回:".$res['msg'], 0);
        }
        return $res['data']['orderid'];
    }

    private function generateSign($data, $signKey){
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if($key != 'sign' && $value != ''){
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "apiKey=".$signKey;
        return strtoupper(md5($str));
    }


    //代付异步回调支付
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['code'] != '200'){
            $rsps['status'] = 2;
        }
        $sign_str =$this->generateSign($notify_data, $this->sign_key);
        if($sign_str != $notify_sign){
            $rsps['status'] = 2;
        }
        $rsps['order_sn']=$notify_data['orderid'];
        $rsps['othr_number']='';
        $rsps['msg'] = "";
        $rsps['result'] = "success ";
        return $rsps;
    }

}