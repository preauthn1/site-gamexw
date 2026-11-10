<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
use think\facade\Request;

//福盈国际
class Top3Pay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params = [];
        $params['merchantCode'] = $this->pay_set['merchantCode'];//商户号
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['requestNo'] = time().rand(100, 999); // 请求流水号
        $params['version'] = 'v1';//版本编号
        $params['payType'] = 'COLLECTION';//交易类型：代收
        $params['title'] = 'recharge';//标题
        $params['payMethod'] = 'PAYLINK';//支付方式：PAYLINK 返回收银台、ORIGINAL 原始信息(默认)
        $params['payAmount'] = intval($pay_arr['apply_price']);//交易金额（菲律宾能带小数点，只能整型）
        $params['payProductCode'] = $this->pay_set['payProductCode'];//产品码(payProductCode)，巴西PIX
        $params['expireTime'] = 3600;//订单有效期（最大3600秒）
        $params['payCategory'] = $this->pay_set['payCategory'];//支持类目(payCategory)，巴西BANK
        $params['notifyUrl'] = $pay_arr['notify_url'];//异步通知
        $params['callbackUrl'] = $pay_arr['call_domain'];//页面跳转地址(POST返回数据)
        $params['signature'] = $this->generateSignature($params, $this->pay_set['private_key']);
        $timezone = env('DEFAULT_TIMEZONE', 'Asia/Shanghai');
        $header = array(
            "Content-Type: application/json;",
            "Accept-Timezone: $timezone;"
        );

        $req = json_encode($params);
        Log::write(['Submit_create', "Top3Pay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['urls'],$req, $header);
        Log::write(['Return_create', "Top3Pay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  !$res['code'] || $res['code'] != '0000') {
            throw new \Exception($res['msg'] ??lang('支付信息错误'), 0);
        }
        if (!isset($res['data']['paymentValue'])) {
            throw new \Exception($res['msg'] ??lang('支付信息错误'), 0);
        }
        $pay_info['pay_url'] = $res['data']['paymentValue'];
        $pay_info['othr_number'] = $res['data']['tradeNo'];
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

        $params = [];
        $params['merchantCode'] = $this->pay_set['merchantCode'];//商户号
        $params['outTradeNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['requestNo'] = time().rand(100, 999); // 请求流水号
        $params['version'] = 'v1';//版本编号
        $params['payType'] = 'PAYMENT';//交易类型：代收
        $params['title'] = 'withdrawal';//标题
        $params['payAmount'] = $pay_arr['amount'];//交易金额
        $params['payProductCode'] = $this->pay_set['payProductCode'];//产品码(payProductCode)，巴西PIX
        $params['payCategory'] = $this->pay_set['payCategory'];//支持类目(payCategory)，巴西BANK
        $params['accountNo'] = $bank_name == 'PHONE' ? '+55'. $pay_arr['bank_number'] : $pay_arr['bank_number'];//账户
        $params['accountType'] = $bank_name;//账户类型（点击查看：巴西、菲律宾、墨西哥、尼日利亚、澳洲）
        $params['notifyUrl'] = $pay_arr['notify_url'];//异步通知地址

        $params['signature'] = $this->generateSignature($params, $this->pay_set['private_key']);
        $req = json_encode($params);
        $timezone = env('DEFAULT_TIMEZONE', 'Asia/Shanghai');
        $header = array(
            "Content-Type: application/json;",
            "Accept-Timezone: $timezone;"
        );
        Log::write(['Submit_withdrawal', "Top3Pay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $req, $header);
        Log::write(['Return_withdrawal', "Top3Pay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != '0000' || !isset($res['data']['tradeNo'])) {
            throw new \Exception(lang("渠道返回:") . $res['msg'] ?? '支付信息错误', 0);
        }
        return $res['data']['tradeNo'];
    }

    //生成签名
    private function generateSignature($params, $privateKey) {
        $stringToBeSigned = $this->createStrParam($params);
        $privateKey       = chunk_split($privateKey, 64);
        $privateKey       = "REDACTED\n";
        $privateKey       = openssl_pkey_get_private($privateKey);
        openssl_sign($stringToBeSigned, $sign, $privateKey, 'RSA-SHA256');

        return base64_encode($sign);
    }

    protected function verifySignature(array $data, string $publicKey): bool{
        if (isset($data['signature'])) {
            $sign = base64_decode($data['signature']);
            unset($data['signature']);
        } else {
            return false;
        }
        ksort($data);
        $content = $this->createStrParam($data);

        // 获取用户公钥，并格式化
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" . wordwrap(trim($publicKey), 64, "\n", true)
            . "\n-----END PUBLIC KEY-----";

        $publicKey = openssl_pkey_get_public($publicKey);
        $result    = openssl_verify($content, $sign, $publicKey, 'RSA-SHA256');

        return (bool)$result;
    }

    protected function createStrParam(array $paramArr): string
    {
        $stringToBeSigned = '';
        ksort($paramArr);
        foreach ($paramArr as $k => $v) {
            if ($v === null) {
                continue;
            }
            $stringToBeSigned .= $k . '=' . $v . '&';
        }

        return trim($stringToBeSigned, "&");
    }

    //异步回调支付
    public function notify($data)
    {
        $data = $data['data'];

        if ( !$this->verifySignature($data, $this->pay_set['public_key']) ) {
            return "sign error";
        }
        //PAYMENT_PROGRESS	付款进行中：交易发起没有完成支付，等待用户付款
        //BECONFIRMED	待确认：向用户付款金额，等待确认是否对用户付款
        //AUDIT	待审核：受到风控或者金额受限等一些外部因数，需要进行审核
        //APPROED	审核通过待打款
        //PAYMENT_DONE	付款完成：收到用户的付款或已经向用户完成付款
        //PAYMENT_CLOSE	付款关闭：订单超时未支付，平台主动进行关闭处理
        //FAILED	交易失败：交易过程中发生失败，具体可查看失败原因
        //ABNORMAL	交易异常：银行方订单状态变化存在异常（如处理中到失败到成功）
        //REJECT	交易拒绝：向用户付款金额或提现时，确认拒绝付款
        //COLOSE	关闭：人工或者自动进行交易关闭
        //TRANS_MANUAL	转人工：订单处于异常状态，进行人工处理
        //WAIT_CONFIRMED	待确认：向对方进行转账，等待确认是否对对方转账
        //TRANSFER_FAILED	转账失败：转账过程中发生失败，具体可查看失败原因
        //TRANSFER_SUCCESS	转账成功：收到对方的转账或已经向对方完成转账
        //TRANSFERRING	转账中：转账发起没有完成转账
        if ($data['status'] != 'PAYMENT_DONE') {
            return 'fail';
        }
        $rsps['order_sn'] = $data['outTradeNo'];
        $rsps['trade_sn'] = $data['tradeNo'];
        $rsps['result'] = 'SUCCESS';
        $rsps['money'] = $data['payAmount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $oldData = $data;
        $data = $data['data'];

        if ( !$this->verifySignature($data, $this->pay_set['public_key']) ) {
            return "sign error";
        }
        //PAYMENT_PROGRESS	付款进行中：交易发起没有完成支付，等待用户付款
        //BECONFIRMED	待确认：向用户付款金额，等待确认是否对用户付款
        //AUDIT	待审核：受到风控或者金额受限等一些外部因数，需要进行审核
        //APPROED	审核通过待打款
        //PAYMENT_DONE	付款完成：收到用户的付款或已经向用户完成付款
        //PAYMENT_CLOSE	付款关闭：订单超时未支付，平台主动进行关闭处理
        //FAILED	交易失败：交易过程中发生失败，具体可查看失败原因
        //ABNORMAL	交易异常：银行方订单状态变化存在异常（如处理中到失败到成功）
        //REJECT	交易拒绝：向用户付款金额或提现时，确认拒绝付款
        //COLOSE	关闭：人工或者自动进行交易关闭

        //TRANS_MANUAL	转人工：订单处于异常状态，进行人工处理
        //WAIT_CONFIRMED	待确认：向对方进行转账，等待确认是否对对方转账
        //TRANSFER_FAILED	转账失败：转账过程中发生失败，具体可查看失败原因
        //TRANSFER_SUCCESS	转账成功：收到对方的转账或已经向对方完成转账
        //TRANSFERRING	转账中：转账发起没有完成转账
        //不是成功或失败的返回不处理
        if ( !in_array($data['status'], ['PAYMENT_CLOSE', 'PAYMENT_DONE', 'FAILED', 'ABNORMAL', 'REJECT', 'COLOSE', 'TRANSFER_FAILED', 'TRANSFER_SUCCESS'])) {
            return 'fail';
        }

        $rsps['status'] = 1;
        if ( !in_array($data['status'], ['TRANSFER_SUCCESS', 'PAYMENT_DONE']) ) {
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $data['outTradeNo'];
        $rsps['othr_number'] = $data['tradeNo'];
        $rsps['msg'] = $oldData['msg'] ??'';
        $rsps['result'] = 'SUCCESS';
        return $rsps;
    }

    //获取三方代收余额
//    public function get_balance($type){
//        $params = [];
//        $params['merchantCode'] = $this->pay_set['merchantCode'];//商户号
//        //BRA	巴西
//        //PHL	菲律宾
//        //MEX	墨西哥
//        //NGA	尼日利亚
//        //AUS	澳大利亚
//        $params['countryCode'] = $this->pay_set['merchantCode']; // 市场编码
//        $params['requestNo'] = time().rand(100, 999); // 请求流水号
//        $params['version'] = 'v1';//版本编号
//        $params['signature'] = $this->generateSignature($params, $this->pay_set['private_key']);
//        $req = json_encode($params);
//        $timezone = env('DEFAULT_TIMEZONE', 'Asia/Shanghai');
//        $header = array(
//            "Content-Type: application/json;",
//            "Accept-Timezone: $timezone;"
//        );
//        $url =  $this->pay_set['balance_url'];
//        Log::write(['get_balance_create', "Top3Pay", $url], "payment");
//        $res = PayRequest::curlS($this->pay_set['balance_url'], $req, $header);
//        Log::write(['get_balance_return', "Top3Pay", $res], "payment");
//        $res = json_decode($res, true);
//        if (empty($res) || $res['code'] != '0000') {
//            if($type == 1){
//                //抛出错误
//                throw new \Exception('支付信息错误', 0);
//            }else{
//                return  'fail';
//            }
//        }
//        return $res['data']['accountBalance'] ?? 0;
//    }
}