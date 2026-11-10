<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class FredPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['userCode'] = $this->pay_set['appId']; //商户号
        $params['orderCode'] = $pay_arr['order_sn']; //订单号
        $params['amount'] =  intval($pay_arr['apply_price']); // 订单金额 只能整型
        $params['callbackUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);

        Log::write(['Submit_create', "FredPay", $params], "payment");
        $d = http_build_query($params);
        $header = array(
            'Content-Type: application/x-www-form-urlencoded'
        );
        $url = $this->pay_set['url'] . '/rupeeLink/api/pay';
        $res = PayRequest::curlS($url, $d, $header);
        Log::write(['Return_create', "FredPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != 200) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
//        $info = json_decode($res['data'], true);
        $pay_info['pay_url'] = $res['data']['url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['userCode'] = $this->pay_set['appId'];//商户号
        $params['orderCode'] = $pay_arr['order_sn']; //订单号
        $params['amount'] = intval($pay_arr['amount']);
        $params['address'] = $pay_arr['bank_number']; //
        $params['callbackUrl'] =  $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->geWithSign($params, $this->pay_set['sign_key']);

        Log::write(['withdrawal_create', "FredPay", $params], "payment");
        $d = http_build_query($params);

        $header = array(
            'Content-Type: application/x-www-form-urlencoded'
        );
        $url = $this->pay_set['url'] . '/rupeeLink/api/remit';

        $res = PayRequest::curlS($url, $d,$header);
        Log::write(['Return_withdrawal', "AntPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != 200) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        return $res['data']['orderNo'] ??'';
    }

    private function generateSign($data, $signKey)
    {
        return strtoupper(MD5("{$data['orderCode']}&{$data['amount']}&{$data['userCode']}&{$signKey}"));
    }

    private function generateSign2($data, $signKey)
    {
        return strtoupper(MD5("{$data['orderCode']}&{$data['amount']}&{$data['userCode']}&{$data['status']}&{$signKey}"));
    }

    private function geWithSign($data, $signKey)
    {
        return strtoupper (MD5("{$data['orderCode']}&{$data['amount']}&{$data['address']}&{$data['userCode']}&{$signKey}"));
    }

    private function geWithSign2($data, $signKey)
    {
        return strtoupper(MD5("{$data['orderCode']}&{$data['customerOrderCode']}&{$data['amount']}&{$data['userCode']}&{$data['status']}&{$signKey}"));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != 3) {
            return "faile";
        }
        $sign_str = $this->generateSign2($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderCode'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ? $data['amount']:0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != 2) {
            return "faile";
        }
        $sign_str = $this->geWithSign2($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['customerOrderCode'];
        $rsps['othr_number'] = $data['orderCode'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}
