<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class VcPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['app_id'] = $this->pay_set['app_id'];//商户号
        $params['nonce_str'] = get_rand_char();
        $params['trade_type'] = $this->pay_set['trade_type'];
        $params['order_amount'] = intval($pay_arr['apply_price']*100); // 订单金额
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['back_url'] = $pay_arr['call_domain']; // 重定向
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "VcPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "VcPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '200') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['pay_url'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['pay_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['app_id'] = $this->pay_set['app_id'];//商户号
        $params['nonce_str'] = get_rand_char();
        $params['trade_type'] = $this->pay_set['trade_type'];
        $params['order_amount'] = intval($pay_arr['amount']*100); // 订单金额
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['bank_code'] = $this->pay_set['bank_code'] ?? $pay_arr['bank_code']; //银行编码
        $params['bank_owner'] = $pay_arr['account_name']; //姓名
        $params['bank_account'] = preg_replace('/^0+/','',$pay_arr['bank_number']); //银行卡号
        $params['identity_type'] = 'PHONE';
        $params['identity'] = preg_replace('/^0+/','',$pay_arr['bank_number']);
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "VcPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "VcPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '200') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['trade_no'];
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"],$data["code"],$data["msg"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['trade_state'] != '1') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['trade_sn'] = $data['trade_no'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['order_amount'] ? $data['order_amount']/100:0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['trade_state'] != '1') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['othr_number'] = $data['trade_no'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}