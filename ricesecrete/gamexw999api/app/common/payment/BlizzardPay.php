<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class BlizzardPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['appId'] = $this->pay_set['appId'];//商户号
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['channelId'] = $this->pay_set['channelId'];
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['callbackUrl'] = $pay_arr['notify_url']; // 异步通知地址
        if( isset($this->pay_set['bankCode']) ){
            $params['bankCode'] = $this->pay_set['bankCode']; // 银行编码
        }
        $params['successUrl'] = $pay_arr['call_domain']; // 重定向
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "BlizzardPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "BlizzardPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '200') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['payUrl'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['payUrl'];
        $pay_info['othr_number'] = $res['data']['orderNo'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $b_code = explode('-',$pay_arr['bank_code']);
        if(isset($b_code[1])){
            $bank_codes = $b_code[0] ??'';
            $bank_name = $b_code[1] ??'';
        }else{
            $bank_codes = $this->pay_set['bank_code'] ?? $pay_arr['bank_code'];
            $bank_name = $pay_arr['bankName'];
        }
        $params['appId'] = $this->pay_set['appId'];//商户号
        $params['outOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['bankName'] = $bank_codes;//银行编码
        $params['bankUserName'] = 'name'; //姓名
        $params['bankCard'] = $pay_arr['bank_number']; //银行卡号
        $params['currency'] = $this->pay_set['currency'];
        $params['callbackUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "BlizzardPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "BlizzardPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '200') {
            throw new \Exception("渠道返回:" . $res['msg']?? '支付信息错误', 0);
        }
        return $res['data']['orderNo'] ??"";
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['payStatus'] != 'SUCCESS') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['outTradeNo'];
        $rsps['trade_sn'] = $data['orderNo'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['amountTrue'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['orderStatus'] != '1') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['outOrderNo'];
        $rsps['othr_number'] = $data['orderNo'];
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}