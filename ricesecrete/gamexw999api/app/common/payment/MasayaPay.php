<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class MasayaPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchantNo'] =  $this->pay_set['merchantNo'];//商户号
        $params['outTradeNo'] = $pay_arr['order_sn'];
        $params['amount'] = $pay_arr['apply_price']; // 订单金额:
        $params['notifyUrl'] =  $pay_arr['notify_url']; // 异步通知地址
        $params['type'] = $this->pay_set['type'];
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "MasayaPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "MasayaPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != 0) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['payUrl'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url']= $res['data']['payUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchantNo'] = $this->pay_set['merchantNo'];//商户号
        $params['outTradeNo'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['amount'] = $pay_arr['amount']; // 订单金额:
        $params['type'] = $this->pay_set['type'];
        $params['name'] = $pay_arr['account_name'];
        $params['bankCode'] = $this->pay_set['bankCode'] ?? $pay_arr['bank_code'];
        $params['bankAccount'] =  $pay_arr['bank_number'];
        $params['notifyUrl'] =  $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "MasayaPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "MasayaPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != 0) {
            throw new \Exception("渠道返回：".$res['msg'] ??'', 0);
        }
        return $res['data']['tradeId'] ??'';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&signKey='.$signKey;
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
        $rsps['order_sn'] = $data['outTradeNo'];
        $rsps['trade_sn'] = $data['sn'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ??0;
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

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['outTradeNo'];
        $rsps['othr_number'] = $data['sn'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}