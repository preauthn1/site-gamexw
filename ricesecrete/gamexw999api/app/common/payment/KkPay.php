<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class KkPay
{

    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['pay_memberid'] = $this->pay_set['merchant_id'];//商户号
        $params['pay_orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['pay_applydate'] = date('Y-m-d H:i:s',time());//提交时间
        $params['pay_bankcode'] = $this->pay_set['pay_bankcode'];//通道编码
        $params['pay_notifyurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['pay_callbackurl'] = $pay_arr['call_domain']; // 重定向
        $params['pay_amount'] = $pay_arr['apply_price']; // 订单金额
        $params['pay_productname'] = 'this is a order'; // 描述
        $params['pay_md5sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "KkPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "KkPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != 'success') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['H5_url'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['mchid'] = $this->pay_set['merchant_id'];//商户号
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['money'] = $pay_arr['amount']; // 订单金额
        $params['bankname'] = $pay_arr['bank_code'];//银行编码
        $params['accountname'] = $pay_arr['account_name']; //姓名
        $params['cardnumber'] = $pay_arr['bank_number']; //银行卡号
        $params['notifyurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['pay_md5sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "KkPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "KkPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != 'success') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return '';
    }


    private function generateSign($data, $signKey)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value && $key != 'pay_md5sign' && $key != 'pay_productname' && $key != 'type') {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= 'key='.$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['returncode'] != '00') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderid'];
        $rsps['trade_sn'] = $data['transaction_id'];
        $rsps['result'] = "OK";
        $rsps['money'] = $data['amount'];
        return $rsps;
    }

    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['returncode'] != '00') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderid'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }

}
