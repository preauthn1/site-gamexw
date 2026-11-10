<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class UsrPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['appid'] = $this->pay_set['appid'];//商户号
        $params['pay_money'] = $pay_arr['apply_price']; // 订单金额
        $params['money_type'] = $this->pay_set['money_type'];//单位：1:USDT 2:CNY，当单位为CNY时，会实时转换成等值USDT
        $params['chain_type'] = $this->pay_set['chain_type'];//链路：1:波场(TRC20) 2:以太坊(ERC20)
        $params['order_sn'] = $pay_arr['order_sn']; // 商户订单号
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['callback_url'] = $pay_arr['call_domain']; // 重定向
        $params['signature'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "UsrPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "UsrPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '1') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['en_pay_url'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['en_pay_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['appid'] = $this->pay_set['appid'];//商户号
        $params['money'] = $pay_arr['amount']; // 订单金额
        $params['chain_type'] = $this->pay_set['chain_type'];//链路：1:波场(TRC20) 2:以太坊(ERC20)
        $params['df_sn'] = $pay_arr['order_sn']; // 商户订单号
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['receive_address'] = $pay_arr['address'];//收款地址
        $params['signature'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "UsrPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "UsrPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '1') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["signature"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&appsecret='.$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['signature']) ? $data['signature'] : '';
        if ($data['status'] != '1') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_sn'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "OK";
        $rsps['money'] = $data['pay_usdt'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['signature']) ? $data['signature'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '1') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['df_sn'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }
}
