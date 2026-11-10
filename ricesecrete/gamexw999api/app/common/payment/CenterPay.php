<?php

namespace app\common\payment;

use think\facade\Log;


class CenterPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }


    //获取支付信息
    public function create($pay_arr)
    {
        $params['Direction'] = 0; // 0代收 1代付
//        $params['Player'] = $pay_arr['username']; // 姓名
        $params['Player'] = $pay_arr['rec_code']; // 姓名 不能携带特殊字符 更换为推广码
        $params['Amount'] = intval($pay_arr['apply_price']); //  订单金额
        $params['VendorTranID'] = $pay_arr['order_sn']; // 商户订单号
        $params['NotifyURL'] = $pay_arr['notify_url']; // 异步通知地址
        Log::write(['Submit_create', "CenterPay", $params, []], "payment");
        $params = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8',
            'Api-Token:'. $this->pay_set['Token'] //api-token
        );
        $res = PayRequest::curlS($this->pay_set['url'], $params, $header);
        Log::write(['Return_create', "CenterPay", $res], "payment");
        $res = json_decode($res, true);
        Log::write(['Return_create_array', "CenterPay", $res], "payment");
        if (empty($res) || !isset($res['ErrCode']) || $res['ErrCode'] != 0 || !isset($res['Result']['PaymentURL'])) {
            throw new \Exception($res['ErrText'] ?? '支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['Result']['PaymentURL'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['Direction'] = 1; // 0代收 1代付
        $params['Player'] = $pay_arr['rec_code']; // 姓名
        $params['Amount'] = intval($pay_arr['amount']); // 订单金额
        $params['PlayerWalletNum'] = $pay_arr['bank_number']; // 银行卡号
        $params['VendorTranID'] = $pay_arr['order_sn']; // 商户订单号
        $params['NotifyURL'] = $pay_arr['notify_url']; // 异步通知地址
        $params['WalletNameID'] = intval($pay_arr['bank_code']); // 钱包类型  1 bKash 2 Rocket 3 Nagad 6 TAP 7 UPay  8 OKWallet
        Log::write(['Submit_withdrawal', "CenterPay", $params, []], "withdrawal");
        $params = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8',
            'Api-Token:'. $this->pay_set['Token'] //api-token
        );
        $res = PayRequest::curlS($this->pay_set['url'],$params, $header);
        Log::write(['Return_withdrawal', "CenterPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['ErrCode']) || $res['ErrCode'] != 0 || !isset($res['Result']['PaymentID'])) {
            throw new \Exception($res['ErrText'] ?? '支付信息错误', 0);
        }
        return $res['Result']['PaymentID '] ?? '';
    }

    //获取签名
    private function generateSign($params, $signKey)
    {
        $signStr = $params['tranId'] . $params['amount'] . $params['player'] . $params['approved']  . $signKey;
        return md5($signStr);
    }



    //异步回调支付
    public function notify($data)
    {
        if($data['approved'] == 0){ //未通过
            return "faile";
        }
        Log::write(['notify', "CenterPay", $data], "payment");
        $res = $this->generateSign($data, $this->pay_set['Secret']); // 加密秘钥
        if ($res != $data['sign']) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['tranId'];
        $rsps['trade_sn'] = '';//支付未返回三方订单号，不能赋值
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ? $data['amount'] : 0;
        return $rsps;
    }


    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if($data['approved'] == 0){ //未通过
//            return "faile";
            $rsps['status'] = 2;
        }
        Log::write(['notify_withdrawal', "CenterPay", $data], "payment");
        $res = $this->generateSign($data, $this->pay_set['Secret']); // 加密秘钥
        if ($res != $data['sign']) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['tranId'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = "";
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }


}