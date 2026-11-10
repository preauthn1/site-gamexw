<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class KirinPay
{	
    private $appid="";
    private $sign_key="";
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->appid=$pay_set['appid'];
        $this->sign_key=$pay_set['pay_key'];
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['appid'] =$this->appid;//商户号
        $params['pay_type'] = 'superupi';// 支付类型：
        $params['out_uid'] = $pay_arr['user_id'];//商户会员ID
        $params['version'] = 'v1.0.1'; // 接口版本: API协议版本，当前值：V1.0 (大写)
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['apply_price']; // 订单金额:
        $params['callback_url'] = $this->domain.'/notify/KirinPay';// 异步通知地址
        $params['success_url'] = $pay_arr['call_domain'];// 
        $params['error_url'] = $pay_arr['call_domain'];// 
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        Log::write(['Submit_create',"KirinPay",$params], "payment");
        $url='https://gw.kirinpayment.cc/pay/unifiedorder?format=json';
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_create',"KirinPay",$res], "payment");
        $res=json_decode($res,true);
        if (!isset($res['url'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']=$res['url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['appid'] =$this->appid;//商户号
        $params['type'] = 'IMPS';// 支付类型：
        $params['account'] = $pay_arr['bank_number'];//收款方账号
        $params['ifsc_code'] = $pay_arr['ifsc'];//收款方账号IFSC
        $params['name'] = $pay_arr['account_name'];//收款方姓名
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['money'] = number_format($pay_arr['amount'],2,'.',''); // 订单金额:
        $params['callback'] = $this->domain.'/withdrawal/notify/KirinPay';// 异步通知地址
        $params['sign'] = $this->makeSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"KirinPay",$params], "withdrawal");
        $url='https://gw.kirinpayment.cc/mch/withdrawin';
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"KirinPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if (!isset($res['data']['order_no'])) {
            throw new \Exception('代付信息错误', 0);
        }
        return $res['data']['order_no'];
    }

	// 签名验证
    private function makeSign($data,$key) {
        //去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
        //签名步骤二：在string后加入KEY
        $string_sign_temp = $string_a . "&key=" .$key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($sign);
        return $result;
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $notify_sign=$notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        if($notify_data['callbacks'] != "CODE_SUCCESS"){
            return "faile";
        }
        $sign_key = $this->sign_key;
        $sign_str =$this->makeSign($notify_data, $this->sign_key);
        if($sign_str != $notify_sign){
            return "sign error";
        }
        
        $rsps['order_sn'] = $notify_data['out_trade_no'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign=$notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['code'] != "CODE_FINISHED"){
            $rsps['status'] = 2;
        }
        $sign_key = $this->sign_key;
        $sign_str =$this->makeSign($notify_data, $this->sign_key);
        if($sign_str != $notify_sign){
            $rsps['status'] = 2;
        }
        
        $rsps['order_sn'] = $notify_data['out_trade_no'];
        $rsps['othr_number'] = $notify_data['order_no'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}