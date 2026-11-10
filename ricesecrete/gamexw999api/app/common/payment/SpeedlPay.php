<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class SpeedlPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        date_default_timezone_set('UTC');
        $params['merchant_no'] = $this->pay_set['merchant_no'];//商户号
        $data = [
            'country'=>$this->pay_set['country'],
            'currency'=>$this->pay_set['currency'],
            'payment_method_id'=>$this->pay_set['payment_method_id'],
            'payment_method_flow'=>$this->pay_set['payment_method_flow'],
            'order_id'=>$pay_arr['order_sn'],
            'amount'=>sprintf("%.2f",$pay_arr['apply_price']),
            'notification_url'=>$pay_arr['notify_url'],
            'success_redirect_url'=>$pay_arr['call_domain'],
            'timestamp'=>intval(microtime(1) * 1000),
            'payer'=>[
                'name'=>'User',
                'document'=>$pay_arr['user_id'],
                'email'=>$pay_arr['username']."@in.com",
                'phone'=>$pay_arr['phone'] ??'0815648636',
            ],
        ];
        $data['signature'] = $this->generateSign($data, $this->pay_set['sign_key']);
        $params['data'] = json_encode($data);
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'ApiVersion: 1.0',
            'AppId: '.$this->pay_set['AppId'],
            'Noncestr: '.rand(1,9999999),
            'Timestamp: '.intval(microtime(1) * 1000)
        );
        Log::write(['Submit_create', "SpeedlPay", $params,$header], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "SpeedlPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['state'] != 'ok') {
            throw new \Exception($res['errorMsg'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['redirect_url'])) {
            throw new \Exception($res['errorMsg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['redirect_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        date_default_timezone_set('UTC');
        $params['merchant_no'] = $this->pay_set['merchant_no'];//商户号
        $data = [
            'country'=>$this->pay_set['country'],
            'currency'=>$this->pay_set['currency'],
            'order_id'=>$pay_arr['order_sn'],
            'amount'=> sprintf("%.2f",$pay_arr['amount']),
            'notification_url'=>$pay_arr['notify_url'],
            'timestamp'=>intval(microtime(1) * 1000),
            'payee'=>[
                'name'=>$pay_arr['account_name'],
                'account'=>$pay_arr['bank_number'],
                'account_type'=>'BANK',
                'phone'=>$pay_arr['phone'] ??'0815648636',
                'email'=>$pay_arr['username']."@in.com",
                'bank_code'=>$pay_arr['bank_code'],
            ],
        ];
        $data['signature'] = $this->generateSign($data, $this->pay_set['withdrawal_sign_key']);
        $params['data'] = json_encode($data);
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json',
            'ApiVersion: 1.0',
            'AppId: '.$this->pay_set['AppId'],
            'Noncestr: '.rand(1,9999999),
            'Timestamp: '.intval(microtime(1) * 1000)
        );
        Log::write(['Submit_withdrawal', "SpeedlPay", $params,$header], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "SpeedlPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['state'] != 'ok') {
            throw new \Exception("渠道返回:" . $res['errorMsg'], 0);
        }
        return isset($res['data']['settlement_id']) ? $res['data']['settlement_id']:'';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["signature"],$data["payer"],$data["payee"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$signKey;
        return  strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['signature']) ? $data['signature'] : '';
        if ($data['status_code'] != 100) {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_id'];
        $rsps['trade_sn'] = $data['payment_id'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['signature']) ? $data['signature'] : '';
        $rsps['status'] = 1;
        if ($data['status_code'] != 100) {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_id'];
        $rsps['othr_number'] = $data['settlement_id'];
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}