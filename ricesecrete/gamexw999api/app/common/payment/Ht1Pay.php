<?php
namespace app\common\payment;
use app\common\lib\Util;
use app\common\payment\PayRequest;
use think\facade\Log;

class Ht1Pay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['amount'] = $pay_arr['apply_price'] * 100;#金额
        $params['clientip'] = Util::get_ip();#IP
        $params['currency'] = $this->pay_set['currency'];#货币名称
        $params['mhtorderno'] = $pay_arr['order_sn'];#订单号
        $params['mhtuserid'] = $pay_arr['user_id'];#商戶网站的用户ID
        $params['notifyurl'] = $pay_arr['notify_url'];#回调地址
        $params['opmhtid'] = $this->pay_set['opmhtid'];#商户号
        $params['paytype'] = $this->pay_set['pay_type'];#支付类型thbank
        $params['random'] = Util::random(16);#随机字符串
        $params['returnurl'] = $pay_arr['call_domain'];#支付返回地址
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "Ht1Pay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],$params);
        Log::write(['Return_create', "Ht1Pay", $res], "payment");
        $res = json_decode($res, true);
        if(!isset($res['rtCode'])){
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (empty($res) || $res['rtCode'] != 0) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['result']['payurl'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['result']['payurl'];
        $pay_info['othr_number'] = $res['result']['pforderno'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['accno'] = $pay_arr['bank_number'];//收款人帐号/位址
        $params['acctype'] = 'bank_card';//帐户类型
        $params['accname'] = $pay_arr['bank_number'];//银行卡
        $params['bankcode'] = $pay_arr['bank_code'];//银行卡code
        $params['amount'] = $pay_arr['amount'] * 100;//金额
        $params['currency'] = $this->pay_set['currency'];//币别
        $params['mhtorderno'] = $pay_arr['order_sn'];//商户代付订单号
        $params['notifyurl'] = $pay_arr['notify_url'];//回调地址
        $params['opmhtid'] = $this->pay_set['opmhtid'];//商戶号
        $params['random'] = Util::random(16);//随机字符串
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "Ht1Pay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$params);
        Log::write(['Return_withdrawal', "Ht1Pay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['rtCode'] != 0) {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['result']['pforderno'];
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        return hash_hmac('sha384', $str, $signKey);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != 1) {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['mhtorderno'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = isset($data['paidamount']) ? $data['paidamount'] / 100: 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['resultcode'] != 0) {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['mhtorderno'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}