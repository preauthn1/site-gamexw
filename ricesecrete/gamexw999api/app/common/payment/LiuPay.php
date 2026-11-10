<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class LiuPay
{

    private $sign_key="";
    private $payType="";
    private $appId="";
    private $gateway = '';
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->sign_key=$pay_set['sign_key'];
        $this->appId=$pay_set['appId'];
        $this->payType=$pay_set['payType'];
        $this->gateway = $pay_set['gateway'];
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['appId'] = $this->appId;
        $params['version'] = '1.0';     //接口版本
        $params['amount'] = $pay_arr['apply_price']*100; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['customOrderId'] = $pay_arr['order_sn']; // 商户订单号
        $params['notifyUrl'] = $this->domain.'/notify/LiuPay';// 异步通知地址
        $params['callbackUrl'] = $pay_arr['call_domain'];   //前端重定向地址
        $params['payType'] = $this->payType;             //支付类型
        $params['remark'] = $pay_arr['user_id'];          //备注 - 用户id
        $params['name'] = $pay_arr['username'];          //用户姓名
        $params['phone'] = $pay_arr['phone'];          //手机号
        $params['responseType'] = "JSON";       //响应类型
        $params['ip'] = $pay_arr['pay_ip'];     //用户id地址
        $data_sign = [
            'appId'=>$params['appId'],
            'version'=>$params['version'],
            'amount'=>$params['amount'],
            'customOrderId'=>$params['customOrderId'],
            'notifyUrl'=>$params['notifyUrl'],
            'callbackUrl'=>$params['callbackUrl'],
            'payType'=>$params['payType'],
            'remark'=>$params['remark'],
            'responseType'=>$params['responseType'],
            'ip'=>$params['ip'],
        ];
        $params['sign'] = $this->generateSign($data_sign, $this->sign_key);
        Log::write(['Submit_create',"LiuPay",$params], "payment");
        $url= $this->gateway.'/client/pay/getPayUrl';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"LiuPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != 1) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }

        $pay_info['pay_url'] = $res['data']['payUrl'];
        return $pay_info;
    }


    //获取代付信息
    public function create_withdrawal($pay_arr){
        $params['appId'] = $this->appId;
        $params['withdrawalType'] = 1;      // 类型 1 银行卡 2 gcash
        $params['amount'] = $pay_arr['amount']*100; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['username'] = $pay_arr['account_name']; // 姓名
        $params['cardno'] = $pay_arr['bank_number']; // 银行卡号
        $params['Ifscode'] = $pay_arr['bankName'];
        $params['phone'] = $pay_arr['phone'];    //手机号
        $params['version'] = '1.0';   //接口版本
        $params['remark'] = $pay_arr['order_sn']; //订单号
        $params['callback'] = $this->domain.'/withdrawal/notify/LiuPay';// 异步通知地址
        $data_sign = [
            'appId'=>$params['appId'],
            'version'=>$params['version'],
            'amount'=>$params['amount'],
            'username'=>$params['username'],
            'phone'=>$params['phone'],
            'remark'=>$params['remark']
        ];
        $params['sign'] = $this->generateSign($data_sign, $this->sign_key);
        Log::write(['Submit_withdrawal',"LiuPay",$params], "withdrawal");
        $url= $this->gateway.'/client/pay/withdrawal';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"LiuPay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] !== 1) {
            throw new \Exception("渠道返回:".$res['msg'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey){
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $value;
            }
        }
        $str .= $signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        unset($data['sign']);//不参与签名
        if($data['status'] != 1){
            return "faile";
        }
        
        $sign_str =$this->generateSign(['appId' => $this->appId, 'status' => $data['status'], 'customOrderId' => $data['customOrderId'], 'amount' => $data['amount'],'realAmount' => $data['realAmount'],'payTime' => $data['payTime'],'remark' => $data['remark']], $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            return "sign error";
        }
        $rsps['order_sn'] = $data['customOrderId'];
        $rsps['trade_sn'] = '';
        $rsps['money'] = $data['realAmount']/100;
        $rsps['result'] = "success";
        return $rsps;
    }


    //代付异步回调支付
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['status'] != 1){
            $rsps['status'] = 2;
        }
        $data_sign = [
            "appId" => $this->appId,
            "amount" => $notify_data['amount'],
            "remark" => $notify_data['remark'],
            "withdrawId" => $notify_data['withdrawId'],
        ];
        $sign_str =$this->generateSign($data_sign, $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            $rsps['status'] = 2;
        }
        $rsps['order_sn']=$notify_data['remark'];
        $rsps['othr_number']="";
        $rsps['msg'] = "";
        $rsps['result'] = "success";
        return $rsps;
    }

}
