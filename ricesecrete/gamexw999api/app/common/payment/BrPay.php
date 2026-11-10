<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class BrPay
{
    private $mchId = "";
    private $sign_key = "";
    private $productId = '';
    private $appId = '';
    private $gateway = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->mchId = $pay_set['mchId'];
        $this->sign_key = $pay_set['sign_key'];
        $this->productId = $pay_set['productId'];
        $this->appId = $pay_set['appId'];
        $this->gateway = $pay_set['gateway'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mchId'] = $this->mchId;//商户号
        $params['appId'] = $this->appId;//应用ID
        $params['productId'] = $this->productId; // 支付产品ID
        $params['amount'] =$pay_arr['apply_price']*100; //订单金额
        $params['idNumber'] = $pay_arr['order_sn']; //订单号
        $params['contact'] = isset($pay_arr['phone']) ? $pay_arr['phone'] : '15698456131'; // 手机号
        $params['notifyUrl'] = $this->domain . '/notify/BrPay';// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "BrPay", $params], "payment");
        $url = $this->gateway . '/api/v1/collection/create';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_create', "BrPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['retCode'] != 'SUCCESS') {
            throw new \Exception($res['retMsg'], 0);
        }
        $pay_info['pay_url'] = $res['payUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {

        $params['mchId'] = $this->mchId;//商户号
        $params['mchOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] =$pay_arr['amount']*100; //订单金额
        $params['accountType'] = 3; // 账户类型
        $params['accountName'] = $pay_arr['account_name'];// 收款人姓名
        $params['accountNo'] = $pay_arr['bank_number'];// 银行账号
        $params['phone'] = $pay_arr['phone'] ? $pay_arr['phone'] :'15698456131'; // 手机号
        $params['bankCode'] = $pay_arr['bank_code'];//银行code
        $params['bankName'] = $pay_arr['bankName'];//银行名称
        $params['notifyUrl'] = $this->domain . '/withdrawal/notify/BrPay';// 异步通知地址
        $params['remark'] = $pay_arr['username'];//备注
        $params['reqTime'] = date('YmdHis',time());
        $url = $this->gateway . '/api/agentpay/apply';
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal', "BrPay", $params], "withdrawal");
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal', "BrPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['retCode'] != 'SUCCESS') {
            throw new \Exception("渠道返回:" . $res['retMsg'], 0);
        }
        if ($res['status'] != '1') {
            throw new \Exception("渠道返回:" . $res['retMsg'], 0);
        }
        return $res['agentpayOrderId'];
    }

    private function generateSign($data, $signKey)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "key=" . $signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['status'] != '2') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['idNumber'];
        $rsps['trade_sn'] = $data['payOrderId'];
        $rsps['result'] = "success";
        $rsps['money'] = isset($data['income'])?$data['income']/100:$data['amount'];
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        $rsps['status'] = 1;
        if ($data['status'] != '2') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['mchOrderNo'];
        $rsps['othr_number'] = $data['agentpayOrderId'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}
