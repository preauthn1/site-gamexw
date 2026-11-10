<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
use app\api\redis\ApiRedis;

class UsldPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchant_id'] = $this->pay_set['merchant_id'];//商户号
        $params['merchant_order_sn'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = intval($pay_arr['apply_price']*100); // 订单金额
        $params['pay_type'] = $this->pay_set['pay_type'];
        $params['user_reference'] = $pay_arr['user_id'];
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['return_url'] = $pay_arr['call_domain']; // 重定向
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "UsldPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "UsldPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '1') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (empty($res['result']['to_address'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }

        $ApiRedis = new ApiRedis();
        $key = $pay_arr['user_id'].$pay_arr['order_sn'];
        $ApiRedis->set($key,$res['result']['to_address'],3600);
        $sys = $ApiRedis->systemInfo();
        $pay_info['pay_url'] = '';
        $pay_info['is_qrcode'] = 1;
        $pay_info['qrcode_url'] = $sys['domain']."/create/payurl?order_sn=".$key;
        $pay_info['address'] = $res['result']['to_address'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchant_id'] = $this->pay_set['merchant_id'];//商户号
        $params['merchant_order_sn'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = intval($pay_arr['amount']*100); // 订单金额
        $params['pay_type'] = $this->pay_set['pay_type'];
        $params['to_address'] = $pay_arr['address'];//收款地址
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->pay_set['withdrawal_sign_key']);
        Log::write(['Submit_withdrawal', "UsldPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "UsldPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '1') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['result']['order_sn'];
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != '1') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['merchant_order_sn'];
        $rsps['trade_sn'] = $data['order_sn'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ? $data['amount']/100:0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '1') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['withdrawal_sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['merchant_order_sn'];
        $rsps['othr_number'] = $data['order_sn'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}