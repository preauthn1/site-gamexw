<?php

namespace app\common\payment;

use think\facade\Log;


class ShunPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    /*
     * pay_type
     *
     * 👍————孟加拉通道————
        🇧🇩【孟加拉UPAY钱包】✔
        【通道编码】：629
        【任意金额】：300-30000
        【成功率】    ：60+🌡

        🇧🇩【孟加拉NAGAD钱包 】✔
        【通道编码】：630
        【任意金额】：300-30000
        【成功率】    ：60+🌡

        🇧🇩【孟加拉BKASH钱包 】✔
        【通道编码】：631
        【任意金额】：300-30000
        【成功率】    ：60+🌡

        🇧🇩【孟加拉ROCKET钱包】✔
        【通道编码】：632
        【任意金额】：300-30000
        【成功率】    ：60+🌡
     */

    //获取支付信息
    public function create($pay_arr)
    {
        $params['pid'] = $this->pay_set['partner']; // 商户编号
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = floatval($pay_arr['apply_price']); // 订单金额
        $params['pay_type'] = $this->pay_set['pay_type']; //
        $params['realname'] = $pay_arr['username']; // 姓名
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['return_type'] = 'json'; // 返回格式
        $params['sign'] = $this->generateSign($params, $this->pay_set['private_key']);

        Log::write(['Submit_create', "ShunPay", $params, []], "payment");
        //网页
        $res = PayRequest::curlS($this->pay_set['url'], $params, []);
        Log::write(['Return_create', "ShunPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['status']) || $res['status'] != 1 || !isset($res['data']['pay_url'])) {
            throw new \Exception($res['msg'] ?? '支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['pay_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $b_code = explode('-',$pay_arr['bank_code']);
        if(isset($b_code[1])){
            $bank_codes = $b_code[0] ??'';
            $bank_name = $b_code[1] ??'';
        }else{
            $bank_codes = $this->pay_set['bank_code'] ?? $pay_arr['bank_code'];
            $bank_name = $pay_arr['bankName'];
        }
        $params['pid'] = $this->pay_set['partner']; // 商户编号
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['amount'];//金额
        $params['bank_code'] = $bank_codes;//银行编码
        $params['bank_number'] = $pay_arr['bank_number'];//银行卡号
        $params['realname'] = $pay_arr['username'];//银行卡号
        $params['notify_url'] = $pay_arr['notify_url'];//回调地址
        $params['sign'] = $this->generateSignWithdrawal($params, $this->pay_set['private_key']);
        Log::write(['Submit_withdrawal', "ShunPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$params);
        Log::write(['Return_withdrawal', "ShunPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['status']) || $res['status'] != 1 ) {
            throw new \Exception($res['msg'] ?? '支付信息错误', 0);
        }
        return '';
    }

    //获取签名
    private function generateSign($params, $signKey)
    {
        ksort($params);
        $params['key'] = $signKey;
        $signStr = $this->buildQuery($params);
        return md5($signStr);
    }

    /**
     * build
     * @param array $params
     */
    function buildQuery($params)
    {
        $noSignField = ['sign', 'realname', 'return_type'];
        $array = array();
        foreach ($params as $k => $v) {
            if ( !in_array($k, $noSignField) && $v != '') {
                $array[] = "$k=$v";
            }
        }
        return implode('&', $array);
    }

    //获取签名
    private function generateSignWithdrawal($params, $signKey)
    {
        ksort($params);
        $signStr = $this->buildQueryWithdrawal($params);
        return md5($signKey.$signStr.$signKey);
    }

    /**
     * build
     * @param array $params
     */
    function buildQueryWithdrawal($params)
    {
        $noSignField = ['sign'];
        $array = array();
        foreach ($params as $k => $v) {
            if ( !in_array($k, $noSignField) && $v != '') {
                $array[] = "$k=$v";
            }
        }
        return implode('&', $array);
    }


    //异步回调支付
    public function notify($data)
    {
        $res = $this->verify($data, $this->pay_set['private_key']);
        if (!$res) {
            return "faile";
        }
        $rsps['order_sn'] = $data['order_no'];
        $rsps['trade_sn'] = '';//支付未返回三方订单号，不能赋值
        $rsps['result'] = "success";
        $rsps['money'] = $res['money'] ?? 0;
        return $rsps;
    }

    /**
     * 代付验证签名信息
     * @see \Common\Lib\Pay\Driver::verify()
     */
    function verify($params, $key)
    {
        if ($params['status'] != 1) {
            return false;
        }
        $verifyParams = array(
            'app_id' => $params['app_id'],
            'status' => $params['status'],
            'money' => $params['money'],
            'order_no' => $params['order_no'],
            'trade_no' => $params['trade_no']
        );
        $signRemote = $params['sign'];
        $signLocal = $this->generateSign($verifyParams, $key);
        if ($signRemote != $signLocal) {
            return false;
        }
        return true;
    }

    //代付异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 2;
        $res = $this->verifyWithdrawal($data, $this->pay_set['private_key']);
        if (!$res) {
            return 'faile';
        }
        if( in_array($data['status'], [0, 1]) ){
            return 'faile';
        }else if( $data['status'] == 2){
            $rsps['status'] = 1;
        }
        $rsps['order_sn'] = $data['order_no'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = $data['msg'] ?? '';
        $rsps['result'] = "success";
        return $rsps;
    }

    /**
     * 代付验证签名信息
     * @see \Common\Lib\Pay\Driver::verify()
     */
    function verifyWithdrawal($params, $key)
    {
        $signRemote = $params['sign'];
        $signLocal = md5($this->pay_set['partner'] . trim($params['order_no']) . trim($params['trade_no']) . $this->pay_set['private_key']);
        if ($signRemote != $signLocal) {
            return false;
        }
        return true;
    }

}