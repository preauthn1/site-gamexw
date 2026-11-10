<?php
namespace app\common\payment;
use app\common\lib\Util;
use app\common\payment\PayRequest;
use think\Exception;
use think\facade\Log;

class HkPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchantCode'] = $this->pay_set['merchant_code'];#商户号
        $params['orderNumber'] = $pay_arr['order_sn'];#订单号
        $params['amount'] = intval($pay_arr['apply_price'] * 100);#金额
        $params['email'] = $pay_arr['mail'] ?: '123@gmail.com';#客户邮箱
        $params['phone'] = $pay_arr['phone'] ??'0815648636';#客户手机
        $params['userName'] = 'hkame';#用户名称
        if( isset($this->pay_set['walletNumber']) ){
            $params['walletNumber'] = $this->pay_set['walletNumber'];#钱包账号08821900000 (钱包支付必填参数)
        }
        //IND_CASH	IND Cashier
        //IND_QR	IND qrcode
        //IND_ALL	IND all disbursement
        $params['channelCode'] = $this->pay_set['pay_channel_code'];#渠道编码
        $params['clientIp'] = Util::get_ip();#客户端IP
        $params['returnUrl'] = $pay_arr['call_domain'];#返回地址
        $params['callbackUrl'] = $pay_arr['notify_url'];#异步通知地址

        //计算签名MD5(amount+callbackUrl +channelCode+merchantCode + orderNumber + md5_key)
        $params['sign'] = md5($params['amount'].$params['callbackUrl'].$params['channelCode'].$params['merchantCode'].$params['orderNumber'].$this->pay_set['md5_key']);

        $header = [
            "Content-Type: application/json",
        ];
        Log::write(['Submit_create', "HkPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],json_encode($params), $header);
        Log::write(['Return_create', "HkPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != '000') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['paymentUrl'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['paymentUrl'];
//        $pay_info['othr_number'] = $res['gp_order_no'];
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

        $params['merchantCode'] = $this->pay_set['merchant_code'];#商户号
        $params['orderNumber'] = $pay_arr['order_sn'];//商户代付订单号
        $params['amount'] = intval($pay_arr['amount'] * 100);//金额
        $params['bankAccount'] = $pay_arr['bank_number'];#银行账号
        $params['bankCode'] = $this->pay_set['pay_bank_code'];#PERMATA
        $params['email'] = $pay_arr['mail'] ?: '123@gmail.com';#客户邮箱
        $params['phone'] = $pay_arr['phone'] ??'0815648636';#客户手机
        $params['userName'] = $pay_arr['account_name'] ?: 'hkame';//账户姓名
        $params['channelCode'] = $this->pay_set['channel_code'] ?? 'ALL';//账户姓名
        $params['clientIp'] = Util::get_ip();//客户端IP
        $params['ifscCode'] = $pay_arr['ifsc'];#收款⼈银⾏ifsc
        $params['returnUrl'] = '';#返回地址
        $params['callbackUrl'] = $pay_arr['notify_url'];#异步通知地址
        //SHA256(amount + bankAccount + bankCode + callbackUrl + channelCode + merchantCode + orderNumber + sha256_key)
        $params['sign'] = hash('sha256', $params['amount'].$params['bankAccount'].$params['bankCode'].$params['callbackUrl'].$params['channelCode'].$params['merchantCode'].$params['orderNumber'].$this->pay_set['sha256_key']);

        $header = [
            "Content-Type: application/json",
        ];
        Log::write(['Submit_withdrawal', "HkPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],json_encode($params), $header);
        Log::write(['Return_withdrawal', "HkPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != '000') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        return '';
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = $data['sign'];
        //MD5(amount + channelCode + email+ merchantCode + orderNumber+ phone + userName + md5_key)
        $sign_str = md5($data['amount'].$data['channelCode'].$data['email'].$data['merchantCode'].$data['orderNumber'].$data['phone'].$data['userName'].$this->pay_set['md5_key']);
        if( $sign_str != $sign ){
            return 'sign error';
        }
        if( $data['status'] != 1 ){
            return 'faile';
        }
        $rsps['order_sn'] = $data['orderNumber'];
        $rsps['trade_sn'] = '';
//        $rsps['trade_sn'] = $data['gp_order_no'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ? $data['amount'] / 100: 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = $data['sign'];
        //	SHA256(amount + bankAccount+ bankCode + channelCode + email+ merchantCode + orderNumber+ phone + userName + sha256_key)
        $sign_str = hash('sha256', $data['amount'].$data['bankAccount'].$data['bankCode'].$data['channelCode'].$data['email'].$data['merchantCode'].$data['orderNumber'].$data['phone'].$data['userName'].$this->pay_set['sha256_key']);
        if( $sign != $sign_str ){
            return 'faile';
        }
        $rsps['status'] = 1;
        if( $data['status'] != 1 ){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderNumber'];
        $rsps['othr_number'] = '';
//        $rsps['othr_number'] = $data['gp_order_no'];
        $rsps['msg'] = $data['message'] ?? '';
        $rsps['result'] = "success";
        return $rsps;
    }


//    //获取三方代收余额
//    public function get_balance($type){
//        $params['merchantCode'] = $this->pay_set['merchant_code'];#商户号
//        $params['timestamp'] = time(); //时间戳
//        $params['sign'] = md5($params['merchantCode'].$params['timestamp'].$this->pay_set['md5_key']);
//        $header = [
//            "Content-Type: application/json",
//        ];
//        Log::write(['get_balance_create', "HkPay", $this->pay_set['balance_url'], $params], "payment");
//        $res = PayRequest::curl_get($this->pay_set['balance_url'], $params, $header);
//        Log::write(['get_balance_return', "HkPay", $res], "payment");
//        $res = json_decode($res, true);
//        if (empty($res) || $res['code'] != '000') {
//            if($type == 1){
//                //抛出错误
//                throw new \Exception('支付信息错误', 0);
//            }else{
//                return  'fail';
//            }
//        }
//        return $res['canUseAmount'] ?? '';
//    }
}