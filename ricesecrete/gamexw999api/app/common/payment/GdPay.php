<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class GdPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取token
    public function AuthToken()
    {
        $params['merchantNo'] = $this->pay_set['merchantNo'];
        $params['dateTime'] = date('ymdHis',time());
        $params['signature'] = $this->generateSign($params, $this->pay_set['Token_sign_key']);
        Log::write(['Submit_AuthToken', "GdPay", $params], "payment");
        $header = ['Content-Type:application/x-www-form-urlencoded;charset=utf-8'];
        $res = PayRequest::curlS($this->pay_set['Token_url'],$params,$header);
        Log::write(['Return_AuthToken', "GdPay", $res], "payment");
        $res = json_decode($res, true);
        return $res['code'] == '0' ? ($res['data']['auth_token'] ?? ""):'';
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchantNo'] = $this->pay_set['merchantNo'];//商户号
        $params['merchantUser'] = $pay_arr['username'];
        $params['merchantOrder'] = $pay_arr['order_sn']; // 商户订单号
        $params['channel'] = $this->pay_set['channel'];
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['currency'] = $this->pay_set['currency'];
        $params['dateTime'] = date('ymdHis',time());
        $params['callbackUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['payerName'] = '09659845600';
        $params['signature'] = $this->generateSign($params, $this->pay_set['sign_key']);
        $header = array(
            'Authorization:Bearer '.$this->AuthToken(),
            'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
        );
        Log::write(['Submit_create', "GdPay", $params,$header], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],$params,$header);
        Log::write(['Return_create', "GdPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['pageUrl'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['pageUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchantNo'] = $this->pay_set['merchantNo'];//商户号
        $params['merchantOrder'] = $pay_arr['order_sn']; // 商户订单号
        $params['channel'] = $this->pay_set['withdrawal_channel'];
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['currency'] = $this->pay_set['currency'];
        $params['bankCode'] = $pay_arr['bank_code']; //银行编码
        $params['bankAccountName'] = $pay_arr['account_name']; //银行姓名
        $params['bankAccountNo'] = $pay_arr['bank_number']; //银行卡号
        $params['dateTime'] = date('ymdHis',time());
        $params['callbackUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['signature'] = $this->generateSign($params, $this->pay_set['sign_key']);
        $header = array(
            'Authorization:Bearer '.$this->AuthToken(),
            'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
        );
        Log::write(['Submit_withdrawal', "GdPay", $params,$header], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params,$header);
        Log::write(['Return_withdrawal', "GdPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['data']['orderNo'] ??'';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["signature"],$data['callbackUrl'],$data['successTime'],$data['cancelTime'],$data['cancelReason'],$data['isSuccess'],$data['payerName']);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('' != $value) {
                $str .= $value;
            }
        }
        $str .= $signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['signature']) ? $data['signature'] : '';
        if ($data['status'] != '2') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['merchantOrder'];
        $rsps['trade_sn'] = $data['orderNo'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['realAmount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['signature']) ? $data['signature'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '2') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['merchantOrder'];
        $rsps['othr_number'] = $data['orderNo'];
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}