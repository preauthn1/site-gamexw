<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class WddPay
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
        $params['stamp'] = time();
        $params['channelcode'] = $this->pay_set['channelcode'];
        $params['notifyurl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['amount'] = floatval($pay_arr['apply_price']); // 订单金额
        $params['email'] = $pay_arr['username']."@in.com";
        $params['firstname'] = $pay_arr['username'];
        $params['lastname'] = $pay_arr['username'];
        $params['phone'] = $pay_arr['phone'] ??'0815648636';
        $sign_data = [
            'userid'=>$params['userid'],
            'orderid'=>$params['orderid'],
            'stamp'=>$params['stamp'],
            'channelcode'=>$params['channelcode'],
            'notifyurl'=>$params['notifyurl'],
            'amount'=>$params['amount'],
        ];
        $params['sign'] = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "WddPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "WddPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['backUrl'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['backUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['userID'] = $this->pay_set['userid'];//商户号
        $params['amount'] = floatval($pay_arr['amount']); // 订单金额
        $params['payeeName'] = $pay_arr['username']; //姓名
        $params['payeePhone'] = $pay_arr['phone'] ??'0815648636';
        $params['payType'] = '1';
        $params['bankCardOwnerName'] = $pay_arr['account_name']; //银行卡姓名
        $params['bankCardName'] = $pay_arr['ifsc'];
        $params['accountNo'] = $pay_arr['bank_number']; //银行卡号
        $params['stamp'] = time(); //银行卡号
        $params['orderID'] = $pay_arr['order_sn']; // 商户订单号
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['channelcode'] = $this->pay_set['withdrawal_channelcode'];
        $sign_data = [
            'userID'=>$params['userID'],
            'amount'=>$params['amount'],
            'payeeName'=>$params['payeeName'],
            'payeePhone'=>$params['payeePhone'],
            'payType'=>$params['payType'],
            'stamp'=>$params['stamp'],
            'orderID'=>$params['orderID'],
            'notifyUrl'=>$params['notifyUrl'],
        ];
        $params['sign'] = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "WddPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "WddPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"],$data['remark']);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['code'] != '0') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderid'];
        $rsps['trade_sn'] = $data['platformOrderid'];
        $rsps['result'] = "OK";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '0') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderID'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = $data['remark'] ??'';
        $rsps['result'] = "OK";
        return $rsps;
    }
}