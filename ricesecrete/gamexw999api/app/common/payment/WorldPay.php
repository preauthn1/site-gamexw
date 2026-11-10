<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class WorldPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['Amount'] = intval($pay_arr['apply_price']); // 订单金额
        $params['CurrencyId'] = intval($this->pay_set['CurrencyId']);
        $params['IsTest'] = boolval($this->pay_set['IsTest']);
        $params['PayerKey'] = strval($pay_arr['user_id']);
        $params['PaymentChannelId'] = intval($this->pay_set['PaymentChannelId']);
        $params['ShopInformUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['ShopOrderId'] = $pay_arr['order_sn']; // 商户订单号
        $params['ShopReturnUrl'] = $pay_arr['call_domain']; // 重定向
        $params['ShopUserLongId'] = strval($this->pay_set['ShopUserLongId']);//商户编号
        $params['EncryptValue'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "WorldPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "WorldPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  !$res['Success']) {
            throw new \Exception($res['ErrorMessage'] ??'支付信息错误', 0);
        }
        if (!isset($res['PayUrl'])) {
            throw new \Exception($res['ErrorMessage'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['PayUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['Amount'] = intval($pay_arr['amount']); // 订单金额
        $params['CurrencyId'] = intval($this->pay_set['CurrencyId']);
        $params['IsTest'] = boolval($this->pay_set['IsTest']);
        $params['PayeeAccountName'] = $pay_arr['account_name']; //姓名
        $params['PayeeAccountNumber'] = $pay_arr['bank_number']; //银行卡号
        $params['PayeeBankName'] = $pay_arr['bankName'];//银行名称
        $params['PaymentChannelId'] = intval($this->pay_set['PaymentChannelId']);
        $params['ShopInformUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['ShopOrderId'] = $pay_arr['order_sn']; // 商户订单号
        $params['ShopUserLongId'] = strval($this->pay_set['ShopUserLongId']);
        $params['EncryptValue'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "WorldPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "WorldPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  !$res['Success']) {
            throw new \Exception("渠道返回:" . $res['ErrorMessage'], 0);
        }
        return $res['TrackingNumber'] ??'';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["EncryptValue"]);
        ksort($data);
        $data['IsTest'] = $data['IsTest'] == 1 ? 'true':'false';
        $str = strtolower(urldecode(http_build_query(myArrayFilter($data))).'&HashKey='.$signKey);
        return strtoupper(hash('sha256', $str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['EncryptValue']) ? $data['EncryptValue'] : '';
        if ($data['OrderStatusId'] != '2') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['ShopOrderId'];
        $rsps['trade_sn'] = $data['TrackingNumber'];
        $rsps['result'] = "ok";
        $rsps['money'] = $data['AmountPaid'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['EncryptValue']) ? $data['EncryptValue'] : '';
        $rsps['status'] = 1;
        if ($data['PaymentOrderStatusId'] != '2') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['ShopOrderId'];
        $rsps['othr_number'] = $data['TrackingNumber'];
        $rsps['msg'] = '';
        $rsps['result'] = "ok";
        return $rsps;
    }
}
