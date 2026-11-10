<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class YyPay
{
    private $merchant_id = "";
    private $sign_key = "";
    private $pay_type = '';
    private $withdrawal_pay_type = '';
    private $gateway = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->pay_type = $pay_set['pay_type'];
        $this->withdrawal_pay_type = $pay_set['withdrawal_pay_type'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchantId'] = $this->merchant_id;//商户号
        $params['userId'] = $pay_arr['user_id'];//用户id
        $params['payMethod'] = $this->pay_type;//支付通道
        $params['money'] = $pay_arr['apply_price']*100; // 订单金额
        $params['bizNum'] = $pay_arr['order_sn']; // 商户订单号
        $params['notifyAddress'] = $pay_arr['notify_url'];// 异步通知地址
        $params['type'] = 'recharge';
        $params['name'] = $pay_arr['username'];// 姓名
        $params['mobile'] = isset($pay_arr['phone']) ? $pay_arr['phone'] : '15698456131'; // 手机号
        $params['email'] = $pay_arr['username'] . "@0s.com";// 邮箱
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "YyPay", $params], "payment");
        $url = $this->gateway . '/pay/order';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_create', "YyPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !$res['success']) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['url'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr){


        $params['merchantId'] = $this->merchant_id;//商户号
        $params['payMethod'] = $this->withdrawal_pay_type;//支付通道
        $params['money'] = $pay_arr['amount']*100; // 订单金额
        $params['bizNum'] = $pay_arr['order_sn']; // 商户订单号
        $params['notifyAddress'] = $pay_arr['notify_url'];// 异步通知地址
        $params['name'] = $pay_arr['account_name'];// 姓名
        $params['mobile'] = isset($pay_arr['phone']) ? $pay_arr['phone'] : '15698456131'; // 手机号
        $params['account'] = $pay_arr['bank_number'];// 账号
        $params['ifscCode'] = $pay_arr['ifsc']; // ifsc
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"YyPay",$params], "withdrawal");
        $url = $this->gateway . '/pay/order/paid';
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"YyPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if (empty($res) || !$res['success']) {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['data']['sysOrderNum'];
    }

    private function generateSign($data, $signKey)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "key=" . $signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['status'] != '1') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['merchantBizNum'];
        $rsps['trade_sn'] = $data['sysBizNum'];
        $rsps['result'] = "success";
        $rsps['money'] = isset($data['money'])?$data['money']:$data['orderMoney'];
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign=$notify_data['sign']??'';
        $rsps = [];
        $rsps['status'] = 1;
        if ($notify_data['status'] != '1') {
            $rsps['status'] = 2;
        }

        $sign =$this->generateSign($notify_data,$this->sign_key);
        if($sign != $notify_sign){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $notify_data['merchantBizNum'];
        $rsps['othr_number'] = $notify_data['sysBizNum'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }

}
