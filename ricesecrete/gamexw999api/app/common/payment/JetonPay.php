<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
use think\facade\Request;

class JetonPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $Request = Request::param(false);
        $wallet_id = $Request['extend'] ?? '';
        if(empty($wallet_id)){
            throw new \Exception($res['msg'] ??'钱包id不得为空', 0);
        }
        $params['orderId'] = $pay_arr['order_sn']; //订单号
        $params['amount'] =  intval($pay_arr['apply_price']); // 订单金额
        $params['currency'] = $this->pay_set['currency']; //货币
        $params['method'] = $this->pay_set['method']; //类型
        $params['returnUrl'] = $pay_arr['call_domain']; // 返回地址
        $params['customer'] = $Request['extend']; // 用户钱包id

//        $params['customerReferenceNo'] = $pay_arr['rec_code']; // 用户参考编号
        $params['language'] = $this->pay_set['lang']; // 用户参考编号

        Log::write(['Submit_create', "JetonPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'X-API-KEY: ' . $this->pay_set['api_key'],
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . '/api/v3/integration/merchants/payments/pay';
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_create', "JetonPay", $res], "payment");
        $res = json_decode($res, true);

        if (empty($res) || isset($res['code'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['checkout'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['orderId'] = $pay_arr['order_sn']; //订单号
        $params['amount'] =  intval($pay_arr['amount']); // 订单金额
        $params['currency'] = $this->pay_set['currency']; //货币
        $params['customer'] = $pay_arr['bank_number']; // 用户钱包id

        Log::write(['withdrawal_create', "JetonPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'X-API-KEY: ' . $this->pay_set['api_key'],
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . '/api/v3/integration/merchants/payments/payout';
        $res = PayRequest::curlS($url, $req,$header);
        Log::write(['Return_withdrawal', "JetonPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || isset($res['code'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        return $res['order'] ??'';
    }

    private function generateSign($data, $signKey)
    {
        $data = array_filter($data);
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if($value != ''){
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= 'key='.$signKey;
        Log::write(['SignStr', "AntPay", $str], "payment");
        return urlencode(strtoupper(md5($str)));
    }


    //异步回调支付
    public function notify($data)
    {
//        $sign = isset($data['sign']) ? $data['sign'] : '';
//        $data_decode = json_decode(urldecode($data['transdata']), true);
        if ($data['status'] != 'SUCCESS' || $data['message'] != 'SUCCESS') {
            return "faile";
        }
        $rsps['order_sn'] = $data['orderId'];
        $rsps['trade_sn'] = $data['paymentId'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ? $data['amount']:0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if ($data['status'] != 'SUCCESS' || $data['message'] != 'SUCCESS') {
            return "faile";
        }
        $rsps['order_sn'] = $data['orderId'];
        $rsps['othr_number'] = $data['paymentId'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}
