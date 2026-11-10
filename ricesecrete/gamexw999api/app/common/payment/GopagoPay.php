<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class GopagoPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mchId'] = $this->pay_set['mchId'];//商户号
        $params['nonceStr'] =  time().rand(123,999);//随机字符串
        $params['mchOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['payType'] = $this->pay_set['payType'];
        $params['amount'] = intval($pay_arr['apply_price']*100); // 订单金额
        $params['successUrl'] = $pay_arr['call_domain']; // 重定向
        $params['name'] = $pay_arr['username'];
        $params['email'] = $pay_arr['username']."@in.com";
        $params['phone'] = $pay_arr['phone'] ??'0815648636';
        $params['body'] = 'this is a order'; // 描述
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8',
            'tmId: '.$this->pay_set['tmId']
        );
        Log::write(['Submit_create', "GopagoPay", $req,$header], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "GopagoPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['resCode'] != 'SUCCESS') {
            throw new \Exception($res['errDes'] ??'支付信息错误', 0);
        }
        if (!isset($res['url'])) {
            throw new \Exception($res['errDes'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $bankname = strtoupper($pay_arr['bankName']);
        $accountType = 6;
        if(strtoupper($bankname) == 'CPF'){
            $accountType = 1;
        }elseif (strtoupper($bankname) == 'CNPJ'){
            $accountType = 2;
        }elseif (strtoupper($bankname) == 'EMAIL'){
            $accountType = 3;
        }elseif (strtoupper($bankname) == 'PHONE'){
            $accountType = 4;
        }elseif (strtoupper($bankname) == 'EVP'){
            $accountType = 5;
        }
        $params['mchId'] = $this->pay_set['mchId'];//商户号
        $params['nonceStr'] =  time().rand(123,999);//随机字符串
        $params['mchOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['accountName'] = $pay_arr['username']; // 账户名
        $params['amount'] = intval($pay_arr['amount']*100); // 订单金额
        $params['accountType'] = $accountType;
        $params['accountNo'] = $accountType == 4 ? '+55'.$pay_arr['bank_number']:$pay_arr['bank_number']; //银行卡号
        if( in_array($accountType, [1, 2]) ){
            //用户的CPF/CNPJ，类型为CPF/CNPJ时才需要
            $params['idNumber'] = $params['accountNo'];//CPF/CNPJ
        }
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8',
            'tmId: '.$this->pay_set['tmId']
        );
        Log::write(['Submit_withdrawal', "GopagoPay", $req,$header], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "GopagoPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['resCode'] != 'SUCCESS') {
            throw new \Exception("渠道返回:" . $res['errDes'], 0);
        }
        return $res['orderId'] ??'';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != '2') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['mchOrderNo'];
        $rsps['trade_sn'] = $data['orderId'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['amount'] ? $data['amount']/100:0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $msg = '';
        $rsps['status'] = 1;

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
            $msg = 'sign error';
        }
        if ($data['status'] != '2') {
            $rsps['status'] = 2;
            $msg = $data['errMsg'] ?? '';
        }

        $rsps['order_sn'] = $data['mchOrderNo'];
        $rsps['othr_number'] = $data['orderId'];
        $rsps['msg'] = $msg;
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}
