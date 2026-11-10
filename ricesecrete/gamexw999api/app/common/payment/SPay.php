<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class SPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['pay_customer_id'] = $this->pay_set['pay_customer_id'];//商户号
        $params['pay_apply_date'] = time();
        $params['pay_order_id'] = $pay_arr['order_sn']; // 商户订单号
        $params['pay_notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['pay_amount'] = $pay_arr['apply_price']; // 订单金额
        $params['pay_channel_id'] = $this->pay_set['pay_channel_id'];
        $params['user_name'] = $pay_arr['username'];
        $params['pay_md5_sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "SPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "SPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['view_url'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['view_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {

        $params['pay_customer_id'] = $this->pay_set['pay_customer_id'];//商户号
        $params['pay_apply_date'] = time();
        $params['pay_order_id'] = $pay_arr['order_sn']; // 商户订单号
        $params['pay_notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['pay_amount'] = $pay_arr['amount']; // 订单金额
        $params['pay_account_name'] = $pay_arr['account_name']; //姓名
        $params['pay_card_no'] = $pay_arr['bank_number']; //银行卡号
        $params['pay_bank_name'] = strtoupper($pay_arr['bank_code']);//银行名称
        $params['pay_md5_sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "SPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $req,$header);
        Log::write(['Return_withdrawal', "SPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['data']['transaction_id'];
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        if(isset($data['extra']))unset($data["extra"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != '30000') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_id'];
        $rsps['trade_sn'] = $data['transaction_id'];
        $rsps['result'] = "OK";
        $rsps['money'] = $data['real_amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['transaction_code'] != '30000') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_id'];
        $rsps['othr_number'] = $data['transaction_id'];
        $rsps['msg'] = $data['transaction_msg'];
        $rsps['result'] = "OK";
        return $rsps;
    }
}