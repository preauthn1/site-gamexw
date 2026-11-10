<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class AntPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchant_code'] = $this->pay_set['appId']; //商户号
        $params['order_no'] = $pay_arr['order_sn']; //订单号
        $params['order_amount'] =  intval($pay_arr['apply_price']); // 订单金额 只能整型
        $params['order_time'] = time(); //时间戳
        $params['product_name'] = 'Shopping'; //产品名称
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['pay_type'] = $this->pay_set['type']; // 支付方式
        $params['return_url'] = $pay_arr['call_domain']; // 重定向
        $params['payer_info'] = $pay_arr['username']; //用户名

        $sign = $this->generateSign($params, $this->pay_set['sign_key']);

        Log::write(['Submit_create', "AntPay", $params], "payment");
        $d = json_encode($params);
        $param = [];
        $param['signtype'] = 'MD5';
        $param['sign'] = $sign;
        $param['transdata'] = urlencode($d);
        $req = json_encode($param);
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . '/pay';
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_create', "AntPay", $res], "payment");
        $res = json_decode($res, true);

        if (empty($res) ||  $res['code'] != '0') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['payUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchant_code'] = $this->pay_set['appId'];//商户号
        $params['order_no'] = $pay_arr['order_sn']; //订单号
        $params['order_amount'] = intval($pay_arr['amount']);
        $params['pay_type'] = $this->pay_set['type_w']; // 支付方式
        $params['bank_card'] = $pay_arr['bank_number']; //PIX钱包账户
        $params['bank_branch'] = $pay_arr['ifsc']; //IFSC Code
        $params['bank_name'] = $pay_arr['bankName']; //银行名称
        $params['user_name'] = $pay_arr['account_name']; //PIX钱包账户
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址

        $sign = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['withdrawal_create', "AntPay", $params], "payment");
        $d = json_encode($params);
        $param = [];
        $param['signtype'] = 'MD5';
        $param['sign'] = $sign;
        $param['transdata'] = urlencode($d);
        $req = json_encode($param);
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . '/v2/withdraw';

        $res = PayRequest::curlS($url, $req,$header);
        Log::write(['Return_withdrawal', "AntPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  !$res['status']) {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['order'] ??'';
    }

    private function generateSign($data, $signKey)
    {
        $data = array_filter($data);
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if($value != ''){
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= 'key='.$signKey;
        Log::write(['SignStr', "AntPay", $str], "payment");
        return urlencode(strtoupper(md5($str)));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $data_decode = json_decode(urldecode($data['transdata']), true);
        if ($data_decode['payment'] != '支付成功') {
            return "faile";
        }
        $sign_str = $this->generateSign($data_decode, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data_decode['order_no'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        $rsps['money'] = $data_decode['order_amount'] ? $data_decode['order_amount']:0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $data_decode = json_decode(urldecode($data['transdata']), true);
        if ($data_decode['resp_code'] != 'S') {
            $rsps['status'] = 2;
        }
        $sign_str = $this->generateSign($data_decode, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data_decode['order_no'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}
