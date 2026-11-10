<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class UsotcPay
{
    private $merchant_id = "";
    private $sign_key = "";
    private $api_key = "";
    private $coin_type = "";
    private $url = "";
    private $withdrawal_url = "";
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->sign_key = $pay_set['secretKey'];
        $this->api_key = $pay_set['api_key'];
        $this->coin_type = $pay_set['coin_type'];
        $this->url = $pay_set['url'];
        $this->withdrawal_url = $pay_set['withdrawal_url'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mid'] = $this->merchant_id;//商户号
        $params['time'] = time();//提交时间
        $params['coin_type'] = $this->coin_type;//支付类型
        $params['fiat_amount'] = sprintf("%.2f",$pay_arr['apply_price']); // 订单金额
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['notify_url'] = $this->domain . '/notify/UsotcPay';// 异步通知地址
        $params['return_url'] = $pay_arr['call_domain']; // 重定向
        $params['ip'] = $pay_arr['pay_ip']; //ip
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "UsotcPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/problem+json',
            'Content-Type: application/json',
            'Authorization: api-key ' . $this->api_key,
        );
        $res = PayRequest::curlS($this->url,$req,$header);
        Log::write(['Return_create', "UsotcPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '200') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['url'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['mid'] = $this->merchant_id;//商户号
        $params['time'] = time();//提交时间
        $params['coin_type'] = $this->coin_type;//支付类型
        $params['fiat_amount'] = sprintf("%.2f",$pay_arr['amount']); // 订单金额
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['notify_url'] = $this->domain . '/withdrawal/notify/UsotcPay';// 异步通知地址
        $params['ip'] = $pay_arr['pay_ip']; //ip
        $params['to_address'] = $pay_arr['address'];// 收款地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal', "UsotcPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/problem+json',
            'Content-Type: application/json',
            'Authorization: api-key ' . $this->api_key,
        );
        $res = PayRequest::curlS($this->withdrawal_url,$req,$header);
        Log::write(['Return_withdrawal', "UsotcPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '200') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        return base64_encode(hash_hmac('sha1', $str, $signKey, true));
    }

    //异步回调支付
    public function notify($data)
    {
        if ($data['code'] != '200') {
            return "faile";
        }
        $sign_str = $this->generateSign($data['data'], $this->sign_key);
        if ($sign_str != $data['data']['sign']) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['data']['order_no'];
        $rsps['trade_sn'] = $data['data']['no'];
        $rsps['result'] = "ok";
        $rsps['money'] = $data['data']['actual_amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if ($data['code'] != '200') {
            $rsps['status'] = 2;
        }
        $sign_str = $this->generateSign($data['data'], $this->sign_key);
        if ($sign_str != $data['data']['sign']) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['data']['order_no'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = $data['message'] ??'';
        $rsps['result'] = "ok";
        return $rsps;
    }
}
