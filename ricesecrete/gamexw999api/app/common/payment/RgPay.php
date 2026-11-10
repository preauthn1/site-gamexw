<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class RgPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['channelId'] = $this->pay_set['channelId'];
        $params['noticeUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['orderId'] = $pay_arr['order_sn']; // 商户订单号
        $params['returnUrl'] = $pay_arr['call_domain']; // 重定向
        $params['user'] = $this->pay_set['user'];
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "RgPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "RgPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['url'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['account'] = $pay_arr['bank_number']; //银行卡号
        $params['accountMark'] = 'PHONE';
        $params['accountType'] = '1';
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['name'] = $pay_arr['account_name']; //姓名
        $params['noticeUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['orderId'] = $pay_arr['order_sn']; // 商户订单号
        $params['user'] = $this->pay_set['user'];
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "RgPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "RgPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"],$data['statusMsg'],$data['name']);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&token='.$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['payStatus'] != '2') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderId'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        $rsps['money'] = $data['payAmount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '2') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderId'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}