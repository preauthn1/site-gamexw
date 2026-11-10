<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class YoPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['userid'] = $this->pay_set['userid'];//商户号
        $params['orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['type'] = $this->pay_set['type'];  //通道编码
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['notifyurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['returnurl'] = $pay_arr['call_domain']; // 重定向
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "YoPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "YoPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '1' || !isset($res['data'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $data = json_decode($res['data'], true);
        if (!isset($data['pageurl'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $data['pageurl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['userid'] = $this->pay_set['userid'];//商户号
        $params['orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['notifyurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['payload'] = json_encode([
            'accountType'=>'BANK',
            'accountInfo'=>[
                'name'=>$pay_arr['account_name'], //姓名
                'accountNumber'=>$pay_arr['bank_number'], //银行卡号
                'ifsc'=>$pay_arr['ifsc'],
                'bankCode'=>$pay_arr['bank_code'], //银行编码
            ],
        ]);
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "YoPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "YoPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '1') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }

        return isset($res['data']) ? json_decode($res['data'],true)['ticket']:'';
    }

    private function generateSign($data, $signKey)
    {
        $data = $signKey.$data['orderid'].$data['amount'];
        return strtolower(md5($data));
    }


    //异步回调支付
    public function notify($data)
    {
        if (!isset($data['data']) || $data['code'] != '1') {
            return "faile";
        }
        $data = json_decode($data['data'],true);
        if(!isset($data['ispay'])){
            return "faile";
        }
        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $data['sign']) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderid'];
        $rsps['trade_sn'] = $data['ticket'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['payamount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if (!isset($data['data']) || $data['code'] != '1') {
            $rsps['status'] = 2;
        }
        $data = json_decode($data['data'],true);
        if(!isset($data['ispay'])){
            $rsps['status'] = 2;
        }
        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $data['sign']) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderid'];
        $rsps['othr_number'] = $data['ticket'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}