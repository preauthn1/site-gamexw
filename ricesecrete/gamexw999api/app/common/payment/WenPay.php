<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class WenPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchantNo'] = $this->pay_set['merchantNo'];//商户号
        $params['orderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['userNo'] = $pay_arr['user_id'];
        $params['userName'] = $pay_arr['username'];
        $params['channelNo'] = $this->pay_set['channelNo'];
        $params['amount'] =  $pay_arr['apply_price']; // 订单金额
        $params['discount'] =  '';
        $params['payeeName'] =  '';
        $params['bankName'] =  '';
        $params['extra'] =  '';
        $params['datetime'] = date('Y-m-d H:i:s',time());
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['returnUrl'] = $pay_arr['call_domain']; // 重定向
        $params['time'] = time();
        $params['appSecret'] = $this->pay_set['appSecret'];
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "WenPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "WenPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception($res['text'] ??'支付信息错误', 0);
        }
        if (!isset($res['targetUrl'])) {
            throw new \Exception($res['text'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['targetUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchantNo'] = $this->pay_set['merchantNo'];//商户号
        $params['orderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['name'] = $pay_arr['account_name']; //姓名
        $params['bankName'] = $pay_arr['bank_code']; //银行编码
        $params['bankAccount'] = $pay_arr['bank_number']; //银行卡号
        $params['bankBranch'] = '';
        $params['memo'] = '';
        $params['mobile'] = '';
        $params['datetime'] = date('Y-m-d H:i:s',time());
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['reverseUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['extra'] = '';
        $params['time'] = time();
        $params['appSecret'] = $this->pay_set['appSecret'];
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key'],2);
        Log::write(['Submit_withdrawal', "WenPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$params);
        Log::write(['Return_withdrawal', "WenPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception("渠道返回:" . $res['text'], 0);
        }
        return $res['tradeNo'] ??'';
    }

    private function generateSign($data, $signKey,$ste = 1)
    {
        unset($data["sign"],$data['appSecret']);
        if($ste == 1){
            unset($data['userName'],$data['channelNo'],$data['appSecret'],$data['payeeName'],$data['amountBeforeFixed'],$data['returnUrl'],$data['bankName']);
        }else{
            unset($data['bankBranch'],$data['memo']);
        }
        ksort($data);
        $str = urldecode(http_build_query($data));
        $str .= $signKey;
        return  strtoupper(md5(hash("sha256", $str)));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != 'PAID') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderNo'];
        $rsps['trade_sn'] = $data['tradeNo'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['paid'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != 'PAID') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key'],2);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderNo'];
        $rsps['othr_number'] = $data['tradeNo'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}
