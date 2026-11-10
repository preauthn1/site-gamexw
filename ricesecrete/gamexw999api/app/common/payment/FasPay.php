<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class FasPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mer_no'] = $this->pay_set['mer_no'];//商户号
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['order_amount'] = $pay_arr['apply_price']; // 订单金额
        $params['payname'] = $pay_arr['username'];
        $params['payemail'] = $pay_arr['username']."@in.com";
        $params['payphone'] = $pay_arr['phone'] ??'0815648636';
        $params['currency'] = $this->pay_set['currency'];
        $params['paytypecode'] = $this->pay_set['paytypecode'];
        $params['method'] = 'trade.create';
        $params['returnurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['pageurl'] = $pay_arr['call_domain']; // 重定向
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "FasPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "FasPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != 'success') {
            throw new \Exception($res['status_mes'] ??'支付信息错误', 0);
        }
        if (!isset($res['order_data'])) {
            throw new \Exception($res['status_mes'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['order_data'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['mer_no'] = $this->pay_set['mer_no'];//商户号
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['method'] = 'fund.apply';
        $params['order_amount'] = $pay_arr['amount']; // 订单金额
        $params['currency'] = $this->pay_set['currency'];
        $params['acc_code'] = '000';
        $params['acc_name'] = $pay_arr['account_name']; //姓名
        $params['acc_no'] = $pay_arr['bank_number']; //银行卡号
        $params['returnurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['province'] = $pay_arr['ifsc'];
        $params['acc_phone'] = $pay_arr['phone'] ??'0815648636';
        $params['acc_email'] = $pay_arr['username']."@in.com";
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "FasPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "FasPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != 'success') {
            throw new \Exception("渠道返回:" . $res['status_mes'], 0);
        }
        return $res['sys_no'];
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= $signKey;
        return strtolower(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != 'success') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_no'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "ok";
        $rsps['money'] = $data['order_realityamount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['result'] != 'success') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_no'];
        $rsps['othr_number'] = $data['sys_no'];
        $rsps['msg'] = '';
        $rsps['result'] = "ok";
        return $rsps;
    }
}