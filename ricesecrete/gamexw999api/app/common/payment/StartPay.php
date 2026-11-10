<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class StartPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchantNum'] = $this->pay_set['merchantNum'];//商户号
        $params['orderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['apply_price']; // 订单金额
        $params['fiat'] = $this->pay_set['fiat'];//法币单位
        $params['payType'] = $this->pay_set['payType'];//支付类型（信用卡-creditCard）
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['returnUrl'] = $pay_arr['call_domain']; // 重定向
        if($this->pay_set['payType'] == 'creditCard-B'){
            $params['email'] = $pay_arr['username']."@in.com";
            $params['cellphone'] = $pay_arr['phone'];
        }
        $sign_data = [$params['merchantNum'],$params['orderNo'],$params['amount'],$params['notifyUrl']];
        $params['sign'] = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "StartPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "StartPay", $res], "payment");
        $res = json_decode($res, true);
        if (!isset($res['code'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (empty($res) ||  $res['code'] != '200') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['payUrl'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['payUrl'];
        return $pay_info;
    }


    //获取转账信息
//    public function create_withdrawal($pay_arr)
//    {
//        $params['appid'] = $this->pay_set['appid'];//商户号
//        $params['money'] = $pay_arr['amount']; // 订单金额
//        $params['chain_type'] = $this->pay_set['chain_type'];//链路：1:波场(TRC20) 2:以太坊(ERC20)
//        $params['df_sn'] = $pay_arr['order_sn']; // 商户订单号
//        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
//        $params['receive_address'] = $pay_arr['address'];//收款地址
//        $params['signature'] = $this->generateSign($params, $this->pay_set['sign_key']);
//        Log::write(['Submit_withdrawal', "StartPay", $params], "withdrawal");
//        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
//        $res = json_decode($res, true);
//        Log::write(['Return_withdrawal', "StartPay", $res], "withdrawal");
//        if (empty($res) ||  $res['code'] != '1') {
//            throw new \Exception("渠道返回:" . $res['msg'], 0);
//        }
//        return '';
//    }

    private function generateSign($data, $signKey)
    {
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('' != $value) {
                $str .=  $value;
            }
        }
        $str .= $signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['state'] != '1') {
            return "faile";
        }
        $sign_data = [$data['state'],$data['merchantNum'],$data['orderNo'],$data['fiatAmount']];
        $sign_str = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderNo'];
        $rsps['trade_sn'] = $data['platformOrderNo'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['fiatAmount'] ??0;
        return $rsps;
    }


    //异步回调支付
//    public function notify_withdrawal($data)
//    {
//        $sign = isset($data['sign']) ? $data['sign'] : '';
//        $rsps['status'] = 1;
//        if ($data['status'] != '1') {
//            $rsps['status'] = 2;
//        }
//
//        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
//        if ($sign_str != $sign) {
//            $rsps['status'] = 2;
//        }
//
//        $rsps['order_sn'] = $data['df_sn'];
//        $rsps['othr_number'] = '';
//        $rsps['msg'] = '';
//        $rsps['result'] = "success";
//        return $rsps;
//    }
}