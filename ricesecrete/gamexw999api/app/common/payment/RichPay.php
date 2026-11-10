<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Request;
use think\facade\Log;

class RichPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['amount'] =  intval($pay_arr['apply_price']); // 订单金额
        $params['orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['phone'] = $pay_arr['phone'] ??'0912345678';
        $params['type'] = 1;
        $params['url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['userid'] = $this->pay_set['userid'];//商户号
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "RichPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "RichPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['cashier'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['cashier'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['account'] = $pay_arr['bank_number']; //银行卡号
        $params['amount'] = intval($pay_arr['amount']); // 订单金额
        $params['bank'] = $pay_arr['bankName'];//银行名称
        $params['bank_branch'] = $pay_arr['bank_open'];//分行
        $params['bank_code'] = $pay_arr['ifsc']; //银行代码
        $params['orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['receiver'] = $pay_arr['account_name']; //姓名
        $params['url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['userid'] = $this->pay_set['userid'];//商户号
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "RichPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "RichPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return '';
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
        $data = Request::param(false);
        unset($data['channel'],$data['s']);
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != '成功') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['bill_number'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "OK";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $data = Request::param(false);
        unset($data['channel'],$data['s']);
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '成功') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['bill_number'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }
}