<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class NineUgxPay
{
    private $merchant_id = "";
    private $sign_key = "";
    private $gateway = '';
    private $domain = "";
    private $channelId = "";
    private $paytype = "";
    private $mode = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->withdrawal_sign_key = $pay_set['withdrawal_sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->channelId = $pay_set['channelId'];
        $this->withdrawal_channelId = isset($pay_set['withdrawal_channelId']) ? $pay_set['withdrawal_channelId']:$pay_set['channelId'];
        $this->paytype = $pay_set['paytype'];
        $this->mode = $pay_set['mode'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['body'] = 'this is a order'; //商品描述
        $params['channelId'] = $this->channelId;//通道代码
        $params['commodityName'] = $pay_arr['user_id']; //商品名称
        $params['extra'] = json_encode([
            'realname'=>$pay_arr['username'],   //付款人姓名
            'userMobile'=>trim($pay_arr['qh'],"+").$pay_arr['phone'],    //手机号
            'userEmail'=>$pay_arr['username'] . "@0s.com" // 邮箱
        ]);//附加参数
        $params['mchId'] = $this->merchant_id;//商户号
        $params['notifyUrl'] = $pay_arr['notify_url'];// 异步通知地址
        $params['paytype'] = $this->paytype;// 支付类型
        $params['returnUrl'] = $pay_arr['call_domain']; // 重定向
        $params['totalFee'] = intval($pay_arr['apply_price']*100); // 订单金额
        $params['traceno'] = $pay_arr['order_sn']; // 商户订单号
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "NineUgxPay", $params], "payment");
        $url = $this->gateway . '/api/payment';
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($url,$req,$header);
        Log::write(['Return_create', "NineUgxPay", $res], "payment");
        $res = json_decode($res, true);
//        if (empty($res)) {
//            throw new \Exception($res['message'] ??'支付信息错误', 0);
//        }
//        if (!isset($res['payUrl'])) {
//            throw new \Exception($res['message'] ??'支付信息错误', 0);
//        }
        $pay_info['pay_url'] = isset($res['payUrl']) ? $res['payUrl']:'about:blank';
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['channelId'] = $this->withdrawal_channelId;//通道代码
        $params['mchId'] = $this->merchant_id;//商户号
        $params['notifyUrl'] = $pay_arr['notify_url'];// 异步通知地址
        $params['paytype'] = $this->paytype;// 支付类型
        $params['mode'] = $this->mode;//代付模式
        $params['realname'] = $pay_arr['account_name'];// 收款人姓名
        $params['timestamp'] = time();//提交时间
        $params['totalFee'] = intval($pay_arr['amount']*100); // 订单金额
        $params['traceno'] = $pay_arr['order_sn']; // 商户订单号
        $params['userMobile'] = $pay_arr['bank_number']; // 银行卡号
        $params['userEmail'] = $pay_arr['username']."@in.com";; // 邮箱
        $params['sign'] = $this->generateSign($params, $this->withdrawal_sign_key);
        Log::write(['Submit_withdrawal', "NineUgxPay", $params], "withdrawal");
        $url = $this->gateway . '/api/paid/issued';
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($url,$req,$header,60);
        Log::write(['Return_withdrawal', "NineUgxPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['retCode'] != 'SUCCESS') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['tradeOrder'];
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
        $str .= 'key='.$signKey;
        return strtoupper(md5(md5($str)));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : $data['sing'];
        unset($data['sign'],$data['sing']);
        if ($data['status'] != 'success') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['traceNo'];
        $rsps['trade_sn'] = $data['tradeOrder'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['actualMoney'] ? ($data['actualMoney']/100):0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        $rsps['status'] = 1;
        if ($data['status'] != 'success') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->withdrawal_sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['traceNo'];
        $rsps['othr_number'] = $data['tradeOrder'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}

