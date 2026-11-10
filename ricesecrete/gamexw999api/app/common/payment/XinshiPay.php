<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class XinshiPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchant'] = $this->pay_set['merchant'];//商户号
        $params['payment_type'] = $this->pay_set['payment_type'] ?? '3';
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['order_id'] = $pay_arr['order_sn']; // 商户订单号
        if (isset($this->pay_set['bank_code']) && $this->pay_set['bank_code'] == 'QRPH') {
            $params['bank_code'] = "PMP";
        } else {
            $params['bank_code'] = $this->pay_set['bank_code'] ?? 'gcash';
        }
        $params['callback_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['return_url'] = $pay_arr['call_domain']; // 重定向
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "XinshiPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "XinshiPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != '1') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (
            isset($this->pay_set['bank_code']) &&
            $this->pay_set['bank_code'] == 'QRPH' &&
            isset($res['gcash_qr_url']) && $res['gcash_qr_url']
        ) {
            $pay_url = $res['gcash_qr_url'];
        } elseif ($params['payment_type'] == 1 && $res['qrcode_url']) {
            $pay_url = $res['qrcode_url'];
        } else {
            $pay_url = $res['redirect_url'];
        }
        if (empty($pay_url)) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $pay_url;
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchant'] = $this->pay_set['merchant'];//商户号
        $params['total_amount'] = $pay_arr['amount']; // 订单金额
        $params['callback_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['order_id'] = $pay_arr['order_sn']; // 商户订单号
        $params['bank'] = $pay_arr['bank_code']; //银行编码
        $params['bank_card_name'] = $pay_arr['account_name']; //姓名
        $params['bank_card_account'] = $pay_arr['bank_number']; //银行卡号
        $params['bank_card_remark'] = 'no';
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "XinshiPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "XinshiPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != '1') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return '';
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
        if ($data['status'] != '5') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_id'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '5') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_id'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}