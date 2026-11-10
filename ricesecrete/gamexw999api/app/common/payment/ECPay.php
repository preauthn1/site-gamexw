<?php

namespace app\common\payment;

use think\facade\Log;


class ECPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }


    //获取支付信息
    public function create($pay_arr)
    {
        $username = $this->pay_set['username'];
        $wallet = $this->pay_set['wallet'];
        $amount = $pay_arr['apply_price'];
        $number = $pay_arr['order_sn'];
        $params = [
            "e_wallet_name" => $wallet, // Nagad , bKash or Rocket
            "amount" => $amount,
            "api_key" => $this->pay_set['key'],
        ];
//        Log::write(['Submit_param', "ECPay", $params, []], "payment");
        $sign = $this->generateSign($params, $this->pay_set['secret']);
        $url = $this->pay_set['url'] . "/{$username}/{$wallet}/0/{$amount}/{$number}/{$sign}";
        Log::write(['Submit_create', "ECPay", $url, []], "payment");
//        $res = PayRequest::curlS($url, [], []);
//        Log::write(['Return_create', "ECPay", $res], "payment");
//        $res = json_decode($res, true);
//        Log::write(['Return_create_array', "ECPay", $res], "payment");
//        if (empty($res) || !isset($res['ErrCode']) || $res['ErrCode'] != 0 || !isset($res['Result']['PaymentURL'])) {
//            throw new \Exception($res['ErrText'] ?? '支付信息错误', 0);
//        }
        $pay_info['pay_url'] = $url;
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['api_key'] = $this->pay_set['key']; // 0代收 1代付
        $params['user_account_no'] = $pay_arr['bank_number']; // 姓名
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['e_wallet_name'] = $pay_arr['bank_code']; // 钱包名称
        $params['partner_transection_id'] = $pay_arr['order_sn']; // 商户订单号

        $sign_arr = [
            'amount' => $params['amount'],
            'api_key' => $params['api_key'],
            'e_wallet_name' => $params['e_wallet_name'],
            'user_account_no' => $params['user_account_no'],
        ];
        $params['sign'] = $this->generateSign($sign_arr, $this->pay_set['secret']);
        Log::write(['Submit_withdrawal', "CenterPay", $params, $sign_arr], "withdrawal");
        $params = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8',
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$params, $header);
        Log::write(['Return_withdrawal', "CenterPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['id']) || $res['id'] == 0) {
            throw new \Exception($res['message'] ?? '支付信息错误', 0);
        }
        return $res['id '] ?? '';
    }

    //获取签名
    private function generateSign($params, $signKey)
    {
        ksort($params);
        $string_to_hash = json_encode($params);
        $hash = hash("sha256", $string_to_hash);
        $hmac = hash_hmac('sha256', $hash, $signKey);
        $combined = $hmac . time();
        $sign = base64_encode($combined);
        return $sign;
    }

    private function CheckSign($params, $signKey, $signMd5)
    {
        ksort($params);
        $string_to_hash = json_encode($params);
        $hash = hash("sha256", $string_to_hash);
        $hmac = hash_hmac('sha256', $hash, $signKey);

        $timestamp = time();
        $timestamp_length = strlen($timestamp);
        $decoded = base64_decode($signMd5);
        $request_hash = substr($decoded, 0, -$timestamp_length);
        $sign_timestamp = substr($decoded, -$timestamp_length);
        if(hash_equals($request_hash, $hmac)){
            return true;
        }
        return false;
    }

    //异步回调支付
    public function notify($data)
    {
        if($data['status'] != 'Complete'){ //未通过
            return "faile";
        }
        $sign_arr = [
            "amount" => $data['amount'],
            "api_key" => $this->pay_set['key'],
            "e_wallet_name" => $data['e_wallet_name'], // Nagad , bKash or Rocket
            "id" => $data['id'],
            "transaction_type" => $data['transaction_type'],
            "user_account_no" => $data['user_account_no'],
        ];
        Log::write(['notify', "CenterPay", $data, $sign_arr], "payment");
        $res = $this->CheckSign($sign_arr, $this->pay_set['secret'], $data['sign']); // 加密秘钥
        if (!$res) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['partner_transection_id'];
        $rsps['trade_sn'] = $data['txn_id'];//支付未返回三方订单号，不能赋值
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ?? 0;
        return $rsps;
    }


    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if($data['status'] != 'Complete'){ //未通过
            $rsps['status'] = 2;
        }
        $sign_arr = [
            "amount" => $data['amount'],
            "api_key" => $this->pay_set['key'],
            "e_wallet_name" => $data['e_wallet_name'], // Nagad , bKash or Rocket
            "id" => $data['id'],
            "transaction_type" => $data['transaction_type'],
            "user_account_no" => $data['user_account_no'],
        ];
        Log::write(['notify_withdrawal', "CenterPay", $data, $sign_arr], "payment");
        $res = $this->CheckSign($sign_arr, $this->pay_set['secret'], $data['sign']); // 加密秘钥
        if (!$res) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['partner_transection_id'];
        $rsps['othr_number'] = $data['txn_id'];//支付未返回三方订单号，不能赋值
        $rsps['msg'] = "";
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }


}