<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class JoyPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['orderNo'] = $pay_arr['order_sn'];//订单编号
        $params['amount'] = $pay_arr['apply_price'];//金额（保留小数点后两位）
        $params['firstname'] = $pay_arr['username'];//客户名字

        $params['mobile'] = $pay_arr['phone'] ?: '0815648636';//客户手机号
        $params['email'] = $pay_arr['mail'] ??$pay_arr['username']."@gmail.com";//客户邮箱
        $params['surl'] = $pay_arr['call_domain'];//成功跳转地址
        $params['furl'] = $pay_arr['call_domain'];//失败跳转地址
        $params['remark'] = 'deposit';//产品说明
        $params['callbackUrl'] = $pay_arr['notify_url'];//回调地址（可空，如果为空已后台设置为准）

        $header = array(
            'X-SIGN: ' . $this->generateSign($params, $this->pay_set['private_key']),
            'X-SERVICE-CODE: ' . $this->pay_set['service_code'],
            'Content-Type: application/json;'
        );
        Log::write(['Submit_create', "JoyPay", $params,$header], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], json_encode($params),$header);
        Log::write(['Return_create', "JoyPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  !$res['msg'] || $res['msg'] != 'Success' || !isset($res['data']['linkUrl'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['linkUrl'];
        $pay_info['othr_number'] = $res['data']['platformOrderNo'];
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

        $params['orderNo'] = $pay_arr['order_sn'];//订单编号
        $params['amount'] = $pay_arr['amount'];//金额（保留小数点后两位）
        $params['bankAccount'] = $pay_arr['bank_number'];//银行卡号（银行卡支付时不能为空）
        $params['ifsc'] = $pay_arr['ifsc'];//Ifsc（银行卡支付时不能为空/11位第5位必须是0）
//        $params['upi'] = $this->pay_set['merchant_no'];//upi 账号（upi支付时不能为空）
        $params['beneficiaryName'] = $pay_arr['username'];//客户姓名
        $params['beneficiaryMobile'] = $pay_arr['phone'] ?: '0815648636';;//客户手机号(6,7,8,9开头10位数值)
        $params['beneficiaryEmail'] = $pay_arr['mail'] ??$pay_arr['username']."@gmail.com";//客户邮箱(非国类正确邮箱长度5-50)
        $params['paymentType'] = 0;//代 付 方 式 ： 0. 银 行 卡 1.upi
        $params['remark'] = 'payment';//付款说明
        $params['callbackUrl'] = $pay_arr['notify_url'];//回调地址（可空，如果为空已后台设置为准）

        $header = array(
            'X-SIGN: ' . $this->generateSign($params, $this->pay_set['private_key']),
            'X-SERVICE-CODE: ' . $this->pay_set['service_code'],
            'Content-Type: application/json;'
        );

        Log::write(['Submit_withdrawal', "JoyPay", $params,$header], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], json_encode($params),$header);
        Log::write(['Return_withdrawal', "JoyPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !$res['msg'] || $res['msg'] != 'Success') {
            throw new \Exception("渠道返回:" . $res['msg'] ?? '付款信息错误', 0);
        }
        return $res['data']['platformOrderNo'] ?? '';
    }

    //签名
    private function generateSign($data, $extra) {
        return $this->sign($this->getUrlStr($data), $extra);
    }

    //三方提供签名算法
    private function sign($data, $extra) {
        // 私钥
        $privateKeyBase64 = "REDACTED\n";
        // 签名
        openssl_sign($data, $signature, $privateKeyBase64, OPENSSL_ALGO_SHA512);
        return base64_encode($signature);
    }

    //三方提供签名算法
    private function getUrlStr($data) {
        ksort($data);
        $urlStr = [];
        foreach($data as $k => $v) {
            if ($k != 'sign' && $v !== '' && $v !== null) {
                $urlStr[] = $k.'='.$v;
            }
        }
        return join('&', $urlStr);
    }

    /**
     * 检验RSA签名
     *
     * @param array $transData 待签名数据
     * @param string $publicKey 公钥
     * @param string $sign 待验证签名
     * @return bool 检验结果
     */
    public function rsaVerify($transData, $publicKey, $sign) {
        $signStr = $this->getUrlStr($transData);
        // 私钥
        $publicKeyBase64 = "-----BEGIN PUBLIC KEY-----\n";
        $publicKeyBase64 .= wordwrap($publicKey, 64, "\n", true);
        $publicKeyBase64 .= "\n-----END PUBLIC KEY-----\n";
        $pay_public_key = openssl_get_publickey($publicKeyBase64);
        $result = openssl_verify($signStr, base64_decode($sign), $pay_public_key, OPENSSL_ALGO_SHA512);
        return ($result == 1);
        // -1:错误；0：签名错误；1：签名正确
    }

    //异步回调支付
    public function notify($data)
    {
        //TODO 三方判断失败为httpcode不等于200
        $header = request()->header();
        //获取请求头中的X-SIGN
        $sign = $header['x-sign'] ?: '';
        //获取请求头中的X-SERVICE-CODE
        $service_code = $header['x-service-code'] ?: '';
        if( $service_code != $this->pay_set['service_code'] || !$this->rsaVerify($data, $this->pay_set['public_key'], $sign) ){
            throw new \Exception("sign error", 500);
        }
        //状态 0. 支付失败 1. 支付成功 2. 待支付 3. 支付中
        if ($data['status'] != '1') {#其他状态直接返回告知三方成功，只处理支付成功状态
            return "success";
        }
        $rsps['order_sn'] = $data['orderNo'];
        $rsps['trade_sn'] = $data['platformOrderNo'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {

        //TODO 三方判断失败为httpcode不等于200
        $header = request()->header();
        //获取请求头中的X-SIGN
        $sign = $header['x-sign'] ?: '';
        //获取请求头中的X-SERVICE-CODE
        $service_code = $header['x-service-code'] ?: '';
        if( $service_code != $this->pay_set['service_code'] || !$this->rsaVerify($data, $this->pay_set['public_key'], $sign) ){
            throw new \Exception("sign error", 500);
        }
        //状态 状态 0. 支付失败 1. 支付成功 2. 待支付 3. 支付中
        if ( !in_array($data['status'], [0, 1])) {#其他状态直接返回告知三方成功，只处理支付成功状态
            return "success";
        }

        $rsps['status'] = 1;
        if ($data['status'] != 1) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderNo'];
        $rsps['othr_number'] = $data['platformOrderNo'];
        $rsps['msg'] = $data['errorMsg'] ??'';
        $rsps['result'] = "success";
        return $rsps;
    }

    //获取三方代收余额
    public function get_balance($type){
        $params['serviceCode'] = $this->pay_set['service_code'];
//        $header = array(
//            'X-SIGN: ' . $this->generateSign($params, $this->pay_set['private_key']),
//            'X-SERVICE-CODE: ' . $this->pay_set['service_code'],
//            'Content-Type: application/json;'
//        );
        $url =  $this->pay_set['balance_url'];
        Log::write(['get_balance_create', "JoyPay", $url], "payment");
        $res = PayRequest::curl_get($url, $params);
        Log::write(['get_balance_return', "JoyPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != '000') {
            if($type == 1){
                //抛出错误
                throw new \Exception('支付信息错误', 0);
            }else{
                return  'fail';
            }
        }
        return $res['data']['paymentAmount'] ?? '';
    }



}