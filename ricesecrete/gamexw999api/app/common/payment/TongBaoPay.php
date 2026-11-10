<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class TongBaoPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $paramArray = [
            "appId" => $this->pay_set['appId'], //商户号
            "totalFee" => $pay_arr['apply_price'], // 支付金额
            "apiOrderNo" => $pay_arr['order_sn'] ,  // 商户订单号
            "channelCode" => $this->pay_set['channelCode'],  //通道编码
            "notifyUrl" =>  $pay_arr['notify_url'],  // 回调地址
            "returnUrl" =>  $pay_arr['call_domain'],  // 返回地址
            "charset" =>  "utf-8" //编码
        ];

        $paramArray['sign'] = $this->generateSign($paramArray, $this->pay_set['apikey']); // 加密秘钥
        Log::write(['Submit_create', "TongBaoPay", $paramArray], "payment");
        $req = json_encode($paramArray);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . 'cx/api/unifiedOrder';
        $res = PayRequest::curlS($url , $req, $header);
        $res = json_decode($res, true);
        Log::write(['Return_create', "TongBaoPay", $res], "payment");
        if (empty($res) || $res['code'] != 2000 || !$res['success']) {
            throw new \Exception($res['message'], 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {

        $paramArray = [
            "appId" => $this->pay_set['appId'], //商户ID
            "totalFee" => $pay_arr['amount'], // 金额
            "apiOrderNo" => $pay_arr['order_sn'] ,  // 商户订单号
            "notifyUrl" =>  $pay_arr['notify_url'],	// 转账结果回调URL
            "charset" =>  'utf-8', //编码
            "bankcardNo" => $pay_arr['bank_number'], //收款账户号码
            "bankName" => $pay_arr['bankName'], //银行名称
            "ifsc" => $pay_arr['ifsc'], //ifsc
            "mode" => 'IMPS', //模式
            "name" => $pay_arr['account_name'], //收款人姓名
            "email" => $pay_arr['username']."@0s.com",// 收款人邮箱
            "phone" =>  $pay_arr['phone'] ??'0815648636', // 收款人手机号
        ];
        $paramArray['sign'] = $this->generateSign($paramArray, $this->pay_set['apikey']); // 加密秘钥

        Log::write(['Submit_withdrawal', "TongBaoPay", $paramArray], "withdrawal");
        $req = json_encode($paramArray);
        $header = array(
            "Content-Type:application/json"
        );
        $url = $this->pay_set['url'] . 'cx/api/payoutCreate';
        $res = PayRequest::curlS($url, $req, $header);
        $res = json_decode($res, true);
        Log::write(['Return_withdrawal', "TongBaoPay", $res], "withdrawal");
        if (empty($res) || $res['code'] != 2000 || !$res['success']) {
            throw new \Exception($res['message'], 0);
        }
        return $res['data']['apiOrderNo'] ?? '';
    }

    private static function generateSign($params, $mchKey)
    {
        if (!$params || !is_array($params))
            return false;

        ksort($params);
        $privateKeyHeader = "REDACTED";

        $privateKey = $privateKeyHeader . $privateKeyContent . $privateKeyEnd;


        $keyStr = '';
        foreach ($params as $key => $value) {
            if (empty($keyStr))
                $keyStr = $key . '=' . $value;
            else
                $keyStr .= '&' . $key . '=' . $value;
        }

        if (!$keyStr)
            return false;

        try {
            $key = openssl_get_privatekey($privateKey);
            openssl_sign($keyStr, $signature, $key, "SHA256");
            openssl_free_key($key);
            $sign = base64_encode($signature);
            return $sign ? $sign : false;
        } catch (\Exception $e) {

            return false;
        }
    }

    private static function verification($params, $returnSign, $Public_key){

        if (!$params || !is_array($params))
            return false;
        ksort($params);
        $publicKeyHeader = "-----BEGIN PUBLIC KEY-----\n";
        # TODO 公钥
        $publicKeyContent = wordwrap($Public_key, 64, "\n", true);
        $publicKeyEnd = "\n-----END PUBLIC KEY-----";

        $publicKey = $publicKeyHeader . $publicKeyContent . $publicKeyEnd;

        $keyStr = '';
        foreach ($params as $key => $value) {
            if (empty($keyStr))
                $keyStr = $key . '=' . $value;
            else
                $keyStr .= '&' . $key . '=' . $value;
        }

        if (!$keyStr)
            return false;

        try {
            $key = openssl_get_publickey($publicKey);

            $ok = openssl_verify($keyStr,base64_decode($returnSign), $key, 'SHA256');
            openssl_free_key($key);
            return $ok;
        } catch (\Exception $e) {
            return false;
        }
    }

    //异步回调支付
    public function notify($data)
    {
        $notify_sign = $data['sign']??'';
        if($data['status'] != 2){
            return 'fail';
        }
        unset($data['sign']);//不参与签名
        Log::write(['notify', "TongBaoPay", $data], "payment");
        $resSign = $this->verification($data, $notify_sign, $this->pay_set['public_key']); // 验签
        if ($resSign != $notify_sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['apiOrderNo'];
        $rsps['trade_sn'] = $data['orderNum'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['totalFee'] ?:0;
        return $rsps;
    }

    //异步回调支付
    public function notify_withdrawal($data)
    {
        $notify_sign = $data['sign']??'';
        unset($data['sign']);//不参与签名
        Log::write(['notify_withdrawal', "TongBaoPay", $data], "payment");
        $resSign = $this->verification($data, $notify_sign, $this->pay_set['public_key']); // 验签
        $rsps['status'] = 1;
        if ($resSign != $notify_sign) {
            $rsps['status'] = 2;
        }
        if($data['status'] != '2'){
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $data['apiOrderNo'];
        $rsps['othr_number'] = $data['orderNum'];
        $rsps['msg'] = $data['msg'] ?? '';
        $rsps['result'] = "success";
        return $rsps;
    }
}
