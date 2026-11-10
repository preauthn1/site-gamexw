<?php

namespace app\common\payment;

use think\facade\Log;

class GaayPay
{
    private $domain = "";
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params = array(
            "merchNo" => $this->pay_set['merchNo'],//商户号
            "orderNo" => $pay_arr['order_sn'],//订单编号
            "amount" => $pay_arr['apply_price'],//订单金额
            "currency" => $this->pay_set['currency'],//币种
        );
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        $header = [
            "Content-Type: application/json;charset=UTF-8",
        ];
        Log::write(['Submit_create', "GaayPay", $params, []], "payment");
        //网页
        $res = PayRequest::curlS($this->pay_set['url'], json_encode($params), $header);
        Log::write(['Return_create', "GaayPay", $res], "payment");
        $res = json_decode($res, true);

        if (empty($res) || !isset($res['code']) || $res['code'] != 0 || !isset($res['data']['code_url'])) {
            throw new \Exception($res['msg'] ?? '支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['code_url'];
        $pay_info['othr_number'] = '';
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
        $params = array(
            "merchNo" => $this->pay_set['merchNo'],//商户号
            "orderNo" => $pay_arr['order_sn'],//订单编号
            "amount" => $pay_arr['amount'], // 订单金额,
            "currency" => $this->pay_set['currency'],//币种
            "acctName" => $pay_arr['account_name'],//持卡人姓名
            "acctCode" => $pay_arr['ifsc'] ?: $bank_codes,//银行编码/IFSC/GCASH/PAYMAYA
            "acctNo" => $pay_arr['bank_number'],//银行账号/GCASH/PAYMAYA/UPI等账号
            "mobile" => $pay_arr['phone'] ?: '0815648636',//收款人手机
        );
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        $header = [
            "Content-Type: application/json;charset=UTF-8",
        ];
        Log::write(['Submit_withdrawal', "GaayPay", $params, $header], "payment");
        //网页
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], json_encode($params), $header);
        Log::write(['Return_withdrawal', "GaayPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != 0) {
            throw new \Exception("渠道返回:" . $res['msg'] ?? '代付信息错误', 0);
        }
        return '';
    }

    private function generateSign($params, $signKey)
    {
        // 对参数按键名排序
        ksort($params);
        $str = '';
        foreach ($params as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' !== $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str = rtrim($str, '&');
        return md5($str.$signKey);
    }


    //异步回调支付
    public function notify($data)
    {
        //{
        //	"code":0,
        //“msg”:”success”,
        //    "data":{
        //"realAmount":"100.00", //实际支付金额
        //        "amount":"100.00",
        //        "businessNo":"9999999", //平台订单号 UTR
        //        "orderNo":"11111",
        //        "merchNo":"tom",
        //        "sign":"2ff91e80776eaee5d68fc0cf4b4faae1",
        //        "orderState":"1"
        //    }
        //}
        if( !isset($data['code']) || $data['code'] != 0 ){
            return "faile";
        }
        $data = $data['data'];
        if( $data['orderState'] != 1 ){
            return "faile";
        }

        $sign = isset($data['sign']) ? $data['sign'] : '';
        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }

        $rsps['order_sn'] = $data['orderNo'];
        $rsps['trade_sn'] = '';#创建订单没有三方订单号
        $rsps['result'] = "ok";
        $amount = $data['realAmount'] ?? 0;
        $rsps['money'] = $amount;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        //{
        //    "code":0,
        //“msg”:”success”,
        //    "data":{
        //        "amount":"100.00",
        //        "businessNo":"8888888",   //平台订单号，仅成功订单才有
        //        "orderNo":"22222",
        //        "merchNo":"tom",
        //        "sign":"d6a7d013c500bf7addcc336cba5b5bb9",
        //        "orderState":"1",
        //“msg”:”accountNo error”          //失败原因，仅在失败订单出现
        //    }
        //}
        if( !isset($data['code']) || $data['code'] != 0 ){
            return "faile";
        }
        $data = $data['data'];

        $sign = isset($data['sign']) ? $data['sign'] : '';
        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        //代付只处理成功和失败
        if( !in_array($data['orderState'], [1, 2]) ){
            return "faile";
        }
        $rsps['status'] = 1;
        if ($data['orderState'] != 1) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderNo'];
        $rsps['othr_number'] = '';//代付没有返回三方订单号
        $rsps['msg'] = $data['msg'] ?? '';
        $rsps['result'] = "ok";
        return $rsps;
    }


//    //获取三方代收余额
//    public function get_balance($type){
//        $params = array(
//            "merchNo" => $this->pay_set['merchNo'],//商户号
//            "timestamp" => time(),//
//            "currency" => $this->pay_set['currency'],//币种
//        );
//        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
//        $header = [
//            "Content-Type: application/json;charset=UTF-8",
//        ];
//        Log::write(['get_balance_create', "GaayPay", $this->pay_set['balance_url'], $params], "payment");
//        $res = PayRequest::curl_get($this->pay_set['balance_url'], $params, $header);
//        Log::write(['get_balance_return', "GaayPay", $res], "payment");
//        $res = json_decode($res, true);
//        if (empty($res) || $res['code'] != '0') {
//            if($type == 1){
//                //抛出错误
//                throw new \Exception('支付信息错误', 0);
//            }else{
//                return  'fail';
//            }
//        }
//        return $res['balance']['balance'] ?? '';
//    }
}