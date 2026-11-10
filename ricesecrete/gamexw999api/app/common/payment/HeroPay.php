<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class HeroPay
{
    private $merchant_id = "";
    private $sign_key = "";
    private $pay_type = '';
    private $withdrawal_pay_type = '';
    private $account_type = '';
    private $bank_code = '';
    private $gateway = '';
    private $url = '';
    private $withdrawal_url = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->pay_type = $pay_set['pay_type'];
        $this->withdrawal_pay_type = $pay_set['withdrawal_pay_type'];
        $this->account_type = $pay_set['account_type'];
        $this->bank_code = $pay_set['bank_code'];
        $this->url = $pay_set['url'] ??'/api_peru/payment/create';
        $this->withdrawal_url = $pay_set['withdrawal_url'] ??'/api_peru/withdrawal/create';
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchant_id'] = $this->merchant_id;//商户号
        $params['pay_type'] = $this->pay_type;//支付通道
        $params['trade_amount'] = intval($pay_arr['apply_price']); // 订单金额
        $params['order_sn'] = $pay_arr['order_sn']; // 商户订单号:
        $params['notify_url'] = $this->domain . '/notify/HeroPay';// 异步通知地址
        $params['redirect_url'] = $pay_arr['call_domain']; // 重定向
        $params['order_desc'] = 'this is a order';//描述
        $params['name'] = $pay_arr['username'];// 持卡人
        $params['mobile'] = isset($pay_arr['phone']) ? $pay_arr['phone'] : '15698456131'; // 手机号
        $params['email'] = $pay_arr['username'] . "@0s.com";// 邮箱
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "HeroPay", $params], "payment");
        $url = $this->gateway . $this->url;
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_create', "HeroPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != 1000) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['native_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['pay_type'] = $this->withdrawal_pay_type;//支付通道
        $params['merchant_id'] = $this->merchant_id;//商户号
        $params['order_sn'] = $pay_arr['order_sn']; // 商户订单号:
        $params['transfer_amount'] = $pay_arr['amount']; // 订单金额:
        $params['account_type'] = $this->account_type;//收款帐号类型
        $params['bank_name'] = $pay_arr['bankName'];//银行名称
        $params['identity_type'] = $pay_arr['bank_code'] ?$pay_arr['bank_code']:$this->bank_code; // 收款银行代码
        $params['account_holder'] = $pay_arr['account_name'];// 收款人姓名
        $params['bank_number'] = $pay_arr['bank_number'];// 银行账号
        $params['phone'] = $pay_arr['bank_number']; // 收款人手机号
        $params['order_desc'] = 'withdrawal'; // 描述
        $params['name'] = $pay_arr['username'];// 持卡人
        $params['mobile'] = $pay_arr['phone'] ? $pay_arr['phone'] :'15698456131'; // 手机号
        $params['email'] = $pay_arr['username'] . "@0s.com";// 邮箱
        $params['notify_url'] = $this->domain . '/withdrawal/notify/HeroPay';// 异步通知地址
        $url = $this->gateway . $this->withdrawal_url;
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal', "HeroPay", $params], "withdrawal");
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal', "HeroPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != 1000) {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['data']['pltf_order_sn'];
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
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['pay_result'] != 'success') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_sn'];
        $rsps['trade_sn'] = $data['pltf_order_sn'];
        $rsps['result'] = "success";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        $rsps['status'] = 1;
        if ($data['pay_status'] != 'success') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_sn'];
        $rsps['othr_number'] = $data['pltf_order_sn'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}