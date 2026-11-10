<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class CcPay
{
    private $merchant_id = "";
    private $paytype_id = "";
    private $ccp = "";
    private $token_id = "";
    private $sign_key = "";
    private $gateway = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->paytype_id = $pay_set['paytype_id'];
        $this->ccp = $pay_set['ccp'];
        $this->token_id = $pay_set['token_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['merchant_id'] = $this->merchant_id;//商户号
        $params['muid'] = $pay_arr['user_id'];// 用户id
        $params['paytype_id'] = $this->paytype_id;//会员充值模式ID
        $params['ccp'] = $this->ccp;//默认模式参数
        $params['token_id'] = $this->token_id;//代币ID
        $params['nonstr'] = time().rand(123,999);//随机字符串
        $params['body'] = 'order';
        $params['detail'] = $pay_arr['username']; // 描述
        $params['amount'] = sprintf("%.3f",$pay_arr['apply_price']); // 订单金额
        $params['notify_url'] = $this->domain . '/notify/CcPay';// 异步通知地址
        $params['redirect_url'] = $pay_arr['call_domain']; // 重定向
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "CcPay", $params], "payment");
        $url = $this->gateway . '/api/unifiedorder/v2';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_create', "CcPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception($res['desc'] ??'支付信息错误', 0);
        }
        if (!isset($res['desc']['desktop_url'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['desc']['desktop_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['merchant_id'] = $this->merchant_id;//商户号
        $params['muid'] = $pay_arr['user_id'];// 用户id
        $params['payment_info'] = $pay_arr['bankName'];//银行名称
        $params['payment_account'] = $pay_arr['bank_number'];// 银行账号
        $params['account_name'] = $pay_arr['account_name'];// 收款人姓名
        $params['token_id'] = $this->token_id;//代币ID
        $params['nonstr'] = time().rand(123,999);//随机字符串
        $params['body'] = 'withdrawal_order';
        $params['detail'] = 'this is a withdrawal_order'; // 描述
        $params['amount'] = sprintf("%.3f",$pay_arr['amount']); // 订单金额
        $params['notify_url'] = $this->domain . '/withdrawal/notify/CcPay';// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        $url = $this->gateway . '/api/refund/v2';
        Log::write(['Submit_withdrawal', "CcPay", $params], "withdrawal");
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal', "CcPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != '0') {
            throw new \Exception("渠道返回:" . $res['desc'], 0);
        }
        return $res['desc']['order_no'];
    }

    private function generateSign($data, $signKey)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if ('sign' != $key && '' != $value && $key != 'transfer_orders') {
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
        if ($data['state'] != 'PAID') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['trade_sn'] = $data['order_no'];
        $rsps['result'] = ['code'=>0];
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        $rsps['status'] = 1;
        if ($data['state'] != 'TRANSFERED') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['othr_number'] = $data['id'];
        $rsps['msg'] = '';
        $rsps['result'] = ['code'=>0];
        return $rsps;
    }
}
