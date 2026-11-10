<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class CPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['MerchantAccount'] = $this->pay_set['MerchantAccount'];//商户号
        $params['MerchantOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['ChannelCode'] = $this->pay_set['ChannelCode'];
        $params['PaymentAmount'] = intval($pay_arr['apply_price']*100); // 订单金额
        $params['PaymentCurrency'] = $this->pay_set['PaymentCurrency'];
        $params['NotifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['SuccessUrl'] = $pay_arr['call_domain']; // 重定向
        $params['TimeStart'] = time();
        $params['MemberIP'] = $pay_arr['pay_ip'];
        $params['Sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "CPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "CPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '200') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['qrcode'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['qrcode'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['MerchantAccount'] = $this->pay_set['MerchantAccount'];//商户号
        $params['MerchantOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['ChannelCode'] = $this->pay_set['withdrawal_ChannelCode'];
        $params['PayerName'] = $pay_arr['account_name']; //姓名
        $params['PayerAccount'] = $pay_arr['bank_number']; //银行卡号
        $params['PaymentAmount'] = intval($pay_arr['amount']*100); // 订单金额
        $params['PayerBankName'] = $pay_arr['bank_code']; //银行编码
        $params['PaymentCurrency'] = $this->pay_set['PaymentCurrency'];
        $params['NotifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['TimeStart'] = time();
        $params['MemberIP'] = $pay_arr['pay_ip'];
        $params['Sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "CPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "CPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '200') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["Sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        return base64_encode(hash_hmac("sha256", $str, $signKey,true));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['Sign']) ? $data['Sign'] : '';
        if ($data['Status'] != '1') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['MerchantOrderNo'];
        $rsps['trade_sn'] = $data['OrderNo'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['Amount'] ?? 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['Sign']) ? $data['Sign'] : '';
        $rsps['status'] = 1;
        if ($data['Status'] != '1') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['MerchantOrderNo'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}