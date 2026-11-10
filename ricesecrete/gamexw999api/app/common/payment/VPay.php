<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class VPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchantid'] = $this->pay_set['merchantid'];//商户号
        $params['merchantorderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['version'] = '6.4';
        $params['country'] = $this->pay_set['country'];
        $params['currency'] = $this->pay_set['currency'];
        $params['bankcode'] = $this->pay_set['bankcode'];
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['notifyurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['backurl'] = $pay_arr['call_domain']; // 重定向
        $params['timestamp'] = time() * 1000;
        $params['subject'] = 'this is a order'; // 描述
        $params['name'] = $pay_arr['username'];
        $params['email'] = $pay_arr['username']."@in.com";
        $params['mobile'] = $pay_arr['phone'] ??'0815648636';
        $params['accno'] = rand(1,99999999999);
        $params['userip'] = $pay_arr['user_id'];
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "VPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "VPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '20000') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['url'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchantid'] = $this->pay_set['merchantid'];//商户号
        $params['merchantorderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['version'] = '6.4';
        $params['country'] = $this->pay_set['country'];
        $params['currency'] = $this->pay_set['currency'];
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['banktype'] = '1';
        $params['bankname'] = $pay_arr['bankName'];
        $params['bankcode'] = $pay_arr['bankName'];
        $params['accountname'] = $pay_arr['account_name']; //姓名
        $params['cardnumber'] = strtoupper($pay_arr['bankName']) == 'PHONE' ? '+55'.$pay_arr['bank_number']:$pay_arr['bank_number']; //银行卡号
        $params['idcard'] =  rand(1,99999999999);
        $params['mobile'] = '+55'. $pay_arr['bank_number'] ?: $pay_arr['phone'] ??'0815648636';
        $params['email'] = $pay_arr['username']."@in.com";
        $params['accounttype'] = '2';
        $params['notifyurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['timestamp'] = time() * 1000;
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "VPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "VPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '20000') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['orderid'];
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
        if ($data['code'] != '20000') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['merchantorderid'];
        $rsps['trade_sn'] = $data['orderid'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['code'] != '20000') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['merchantorderid'];
        $rsps['othr_number'] = $data['orderid'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}