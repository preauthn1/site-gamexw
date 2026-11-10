<?php
namespace app\common\payment;
use app\common\FastGame\Base\Http;
use app\common\lib\Util;
use app\common\payment\PayRequest;
use think\Exception;
use think\facade\Log;

class HamBitBRLPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['externalOrderId'] = $pay_arr['order_sn'];#订单号
        $params['amount'] = $pay_arr['apply_price'];#收款金额，小数点不能超过2位（String）
        $params['channelType'] = $this->pay_set['channelType'];#支付类型 oneof=PIX，PIX_DYNAMIC BOLETO（String）
        $params['notifyUrl'] = $pay_arr['notify_url'];#回调地址
        $params['returnUrl'] = $pay_arr['call_domain'];#支付返回地址

        $signData = $params;
        $signData['access_key'] = $this->pay_set['access_key'];
        $signData['timestamp'] = time() * 1000;
        $signData['nonce'] = $this->generateUUID();

        $header = [
            "Content-Type: application/json;charset=utf-8",
            "access_key: ". $this->pay_set['access_key'],
            "timestamp: ". $signData['timestamp'],
            "nonce: ". $signData['nonce'],
            "sign: ". $this->generateSign($signData, $this->pay_set['private_key']),
        ];
        Log::write(['Submit_create', "HamBitBRLPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],json_encode($params), $header);
        Log::write(['Return_create', "HamBitBRLPay", $res], "payment");
        $res = json_decode($res, true);
        dd($res);
        if (empty($res) || !isset($res['code']) || $res['code'] != 200) {
            throw new \Exception($res['msgEn'] ?? '支付信息错误', 0);
        }
        if (!isset($res['data']['cashierUrl'])) {
            throw new \Exception($res['msgEn'] ?? '支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['cashierUrl'];
        $pay_info['othr_number'] = $res['data']['currencyOrderVo']['orderId'];
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
        $bank_name = strtoupper($bank_name);

        $params['externalOrderId'] = $pay_arr['order_sn'];//商户代付订单号
        $params['channelType'] = $this->pay_set['channelType'];//支付类型 oneof=PIX （String）
        $params['currencyAmount'] = $pay_arr['amount'];//代付金额 小数不能大于2位（String）
        $params['personIdType'] = $bank_name;//用户证件信息 oneof=CPF CNPJ（String）
        $params['personId'] = $pay_arr['bank_number'];//	用户证件号（String）
        $params['personName'] = $pay_arr['account_name'];//	用户姓名，max=255（String）
        $params['accountType'] = $bank_name;//	银行账号类型 oneof=CPF CNPJ PHONE EMAIL EVP（String）
        $params['accountId'] = $pay_arr['bank_number'];//	银行账号，参考accountType类型（String）

        $params['notifyUrl'] = $pay_arr['notify_url'];//回调地址


        $signData = $params;
        $signData['access_key'] = $this->pay_set['access_key'];
        $signData['timestamp'] = time() * 1000;
        $signData['nonce'] = $this->generateUUID();

        $header = [
            "Content-Type: application/json;charset=utf-8",
            "access_key: ". $this->pay_set['access_key'],
            "timestamp: ". $signData['timestamp'],
            "nonce: ". $signData['nonce'],
            "sign: ". $this->generateSign($signData, $this->pay_set['private_key']),
        ];

        Log::write(['Submit_withdrawal', "HamBitBRLPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],json_encode($params), $header);
        Log::write(['Return_withdrawal', "HamBitBRLPay", $res], "withdrawal");
        if (empty($res) || !isset($res['code']) || $res['code'] != 200) {
            throw new \Exception($res['msgEn'] ?? '支付信息错误', 0);
        }
        if (!isset($res['data']['orderId'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        return $res['data']['orderId'];
    }


    private function generateSign($data,$signKey){
        ksort($data);
        $string = Http::ToUrlParams($data);
        $hmac_sha1_base64 = hash_hmac('sha1', $string, $signKey, true);
        return base64_encode($hmac_sha1_base64);
    }

    private function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-4%03x-8%03x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    //异步回调支付
    public function notify($data)
    {
        $headers = request()->header();
        $sign = $headers['sign'];
        $signData = $data;
        $signData['access_key'] = $this->pay_set['access_key'];
        $signData['timestamp'] = $headers['timestamp'];
        $signData['nonce'] = $headers['nonce'];
        $_sign = $this->generateSign($signData, $this->pay_set['private_key']);
        if( $sign!= $_sign ){
            return 'faile';
        }
        //orderStatusCode	int64	订单状态 code 1-待支付 2- 支付成功
        if( $data['orderStatusCode'] != 2 ){
            return 'faile';
        }
        $rsps['order_sn'] = $data['externalOrderId'];
        $rsps['trade_sn'] = $data['orderId'];
        $rsps['result'] = '{"code": 200, "success": true}';
        $rsps['money'] = $data['orderAmount'] ?? 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $headers = request()->header();
        $sign = $headers['sign'];
        $signData = $data;
        $signData['access_key'] = $this->pay_set['access_key'];
        $signData['timestamp'] = $headers['timestamp'];
        $signData['nonce'] = $headers['nonce'];
        $_sign = $this->generateSign($signData, $this->pay_set['private_key']);
        if( $sign!= $_sign ){
            return 'faile';
        }
        //orderStatusCode 订单状态code 1-已受理 2-银行处理中 4-失败(银行未受理) 8-成功 16-失败
        if( $data['orderStatusCode'] == 8 ){
            //成功
            $rsps['status'] = 1;
        }elseif ( in_array($data['orderStatusCode'], [4,16]) ){
            $rsps['status'] = 2;
        }else{
            return 'faile';
        }

        $rsps['order_sn'] = $data['externalOrderId'];
        $rsps['othr_number'] = $data['orderId'];
        $rsps['msg'] = $data['errorMsgEn']??'';
        $rsps['result'] = '{"code": 200, "success": true}';
        return $rsps;
    }
}