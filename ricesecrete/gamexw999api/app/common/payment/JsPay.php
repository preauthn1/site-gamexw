<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class JsPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $time = time();
        $params['merchant_no'] = $this->pay_set['merchant_no'];//商户号
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['channel'] = $this->pay_set['channel'];//通道
        $params['currency'] = $this->pay_set['currency'];//货币
        $params['amount'] = intval($pay_arr['apply_price']); // 订单金额
        $params['return_url'] = $pay_arr['call_domain']; // 重定向
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知
        $req = json_encode($params);
        $header = array(
            'JSL-Timestamp: '.$time,
            'JSL-Signature: '.$this->Rsa_openssl($params,$this->pay_set['url'], $time,$this->pay_set['privatekey'],1),
            'Content-Type: application/json;'
        );
        Log::write(['Submit_create', "JsPay", $params,$header], "payment");
        $res = PayRequest::curlS($this->pay_set['urls'].$this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "JsPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  !$res['success'] || $res['code'] != '200') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['redirect_url'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['redirect_url'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $time = time();
        $params['merchant_no'] = $this->pay_set['merchant_no'];//商户号
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['channel'] = $this->pay_set['channel'];//通道
        $params['recv_name'] = $pay_arr['account_name']; // 姓名
        $params['recv_account'] = $pay_arr['bank_number']; //银行卡号
        $params['bank_code'] = $pay_arr['bank_code'] ??'PAYMAYA';//通道
        $params['currency'] = $this->pay_set['currency'];//货币
        $params['amount'] = intval($pay_arr['amount']); // 订单金额
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知
        $req = json_encode($params);
        $header = array(
            'JSL-Timestamp: '.$time,
            'JSL-Signature: '.$this->Rsa_openssl($params,$this->pay_set['withdrawal_url'], $time,$this->pay_set['privatekey'],1),
            'Content-Type: application/json;'
        );
        Log::write(['Submit_withdrawal', "JsPay", $params,$header], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['urls'].$this->pay_set['withdrawal_url'], $req,$header);
        Log::write(['Return_withdrawal', "JsPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !$res['success'] || $res['code'] != '200') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['data']['jsl_no'] ??'';
    }

    /* @description RSA公钥加密 私钥解密 *
     * @param string $data 待加解密数据 *
     * @param string $url 加密路径 *
     * @param string $time 时间戳 *
     * @param string $operate 操作类型 1:加密 2:验证
     * @return string 返回加密内容/解密内容
     */
    protected function Rsa_openssl($data,$url,$time,$keys,$operate = 1,$sign="") {
        if( $operate == 1){
            $private_key = "REDACTED";
            $sign_content = $url."POST".$time.json_encode($data,JSON_UNESCAPED_UNICODE);
            openssl_sign($sign_content, $key, $private_key, OPENSSL_ALGO_SHA256);
            return base64_encode($key);
        }
        if ($operate == 2) {
            $public_key = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($keys, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
            $sign_content = $url."POST".$time.json_encode($data,JSON_UNESCAPED_UNICODE);
            $result = false;
            $result = (openssl_verify($sign_content, base64_decode($sign), $public_key, OPENSSL_ALGO_SHA256) === 1);
            return $result;
        }
    }

    //异步回调支付
    public function notify($data)
    {
        if ($data['status'] != '2') {
            return "faile";
        }
        $time = strtotime($data['notify_time']);
        $sign = $this->Rsa_openssl($data,$this->pay_set['url'], $time,$this->pay_set['privatekey'],1);
        $sign_str = $this->Rsa_openssl($data,$this->pay_set['url'], $time,$this->pay_set['publickey'],2,$sign);
        if (!$sign_str) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_no'];
        $rsps['trade_sn'] = $data['jsl_no'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if ($data['status'] != '2') {
            $rsps['status'] = 2;
        }

        $time = strtotime($data['notify_time']);
        $sign = $this->Rsa_openssl($data,$this->pay_set['url'], $time,$this->pay_set['privatekey'],1);
        $sign_str = $this->Rsa_openssl($data,$this->pay_set['url'], $time,$this->pay_set['publickey'],2,$sign);
        if (!$sign_str) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_no'];
        $rsps['othr_number'] = $data['jsl_no'];
        $rsps['msg'] = $data['reason'] ??'';
        $rsps['result'] = "success";
        return $rsps;
    }
}