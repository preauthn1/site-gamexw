<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

//SimplyPay
class SimplyPay
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
        $params['appId'] = $this->pay_set['receiveAppId'];//商户号
        $params['merOrderNo'] = $pay_arr['order_sn'];//订单号
        $params['currency'] = $this->pay_set['currency'];//币种
        $params['amount'] = (string)($pay_arr['apply_price']);//Amount,转成字符串，防止无效小数点产生签名错误
        $params['returnUrl'] = $pay_arr['call_domain'];//页面跳转地址
        $params['notifyUrl'] = $pay_arr['notify_url'];//异步通知
        $params['extra'] = [
            'name' => $pay_arr['username'], //客户姓名
            'email' => $pay_arr['mail'] ?: '123@gmail.com', //邮箱
            'mobile' => $pay_arr['phone'] ??'0815648636', // 手机号
        ];

        $params['sign'] =$this->generateSignature($params, $this->pay_set['receiveKey']);//签名

        Log::write(['Submit_create', "BcPay", $params], "payment");
        $header = array(
            'Content-Type: application/json;'
        );
        $res = PayRequest::curlS($this->pay_set['urls'], json_encode($params), $header);
        Log::write(['Return_create', "SimplyPay", [$params, $header, $res]], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != 0) {
            throw new \Exception($res['msg'] ??lang('支付信息错误'), 0);
        }
        if (!isset($res['data']['params']['paymentLink'])) {
            throw new \Exception($res['msg'] ??lang('支付信息错误'), 0);
        }
        $pay_info['pay_url'] = $res['data']['params']['paymentLink'];
        $pay_info['othr_number'] = $res['data']['orderNo'];
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
        $params['appId'] = $this->pay_set['paymentAppId'];//平台分配商户ID
        $params['merOrderNo'] = $pay_arr['order_sn'];//订单号 否 是 商户订单号
        $params['currency'] = $this->pay_set['currency'];//金额币种
        $params['amount'] = (string)($pay_arr['amount']);//Amount,转成字符串，防止无效小数点产生签名错误
        $params['notifyUrl'] = $pay_arr['notify_url'];//异步通知地址
        $params['extra'] = [
            'payoutType' => $this->pay_set['payoutType'], // 代付类型 固定值[IFSC]
            'ifsc' => $pay_arr['ifsc'] ?: $bank_codes, // IFSC 银行编码
            'account' => $pay_arr['bank_number'], // 收款账号 代付类型为 IFSC 填银行收款账号
            'name' => $pay_arr['account_name'], // 客户姓名
            'email' => $pay_arr['mail'] ?: '123@gmail.com', // 邮箱
            'mobile' => $pay_arr['phone'] ??'0815648636', // 手机号
        ];//扩展信息
//        $params['attach'] = $pay_arr['notify_url'];//扩展信息
        $params['sign'] = $this->generateSignature($params, $this->pay_set['paymentKey']);//签名
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;'
        );
        Log::write(['Submit_withdrawal', "SimplyPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $req, $header);
        Log::write(['Return_withdrawal', "SimplyPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != '0') {
            throw new \Exception(lang("渠道返回:") . $res['error'] ??lang('支付信息错误'), 0);
        }
        return $res['data']['orderNo'] ?? '';
    }

    //生成签名
    private function generateSignature($params, $appKey = '') {
        unset($params['sign']);

        //Unicode进行排序
        if( isset($params['extra']) && is_array($params['extra']) ){
            $extra = $params['extra'];
            ksort($extra);
            $paramStr = '';
            foreach ($extra as $key => $value) {
                $paramStr .= $key . '=' . $value . '&';
            }
            // 去掉末尾的 '&' 字符
            $params['extra'] = rtrim($paramStr, '&');
        }
        ksort($params);
        // 拼接参数成 URL 键值对格式的字符串
        $paramStr = '';
        foreach ($params as $key => $value) {
            $paramStr .= $key . '=' . $value . '&';
        }
        $paramStr = $paramStr . 'key='.$appKey;
        // 去掉末尾的 '&' 字符
        // $paramStr = rtrim($paramStr, '&');
        // 对字符串进行 sha256 计算
        $signature = hash('sha256', $paramStr);
        return $signature;
    }

    //异步回调支付
    public function notify($data)
    {
        unset($data['extra']);
        $sign  = $this->generateSignature($data, $this->pay_set['receiveKey']);
        if ($sign != $data['sign']) {
            return "sign error";
        }
        //订单状态（0生成订单，1支付中，2支付未通知，3支付已通知，-1交易失败，-2交易过期，-3交易退还，-4交易异常）
        if ($data['orderStatus'] != 2) {
            return 'faile';
        }
        $rsps['order_sn'] = $data['merOrderNo'];
        $rsps['trade_sn'] = $data['orderNo'];
        $rsps['result'] = 'success';
        $rsps['money'] = $data['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign  = $this->generateSignature($data, $this->pay_set['paymentKey']);
        if ($sign != $data['sign']) {
            return "sign error";
        }
        //只处理成功
        if( in_array($data['orderStatus'], [0,1]) ){
            return 'faile';
        }
        //订单状态（0生成订单，1支付中，2支付未通知，3支付已通知，-1交易失败，-2交易过期，-3交易退还，-4交易异常）
        $rsps['status'] = 1;
        if ( !in_array($data['orderStatus'], [2, 3]) ) {
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $data['merOrderNo'];
        $rsps['othr_number'] = $data['orderNo'];
        $rsps['msg'] = $data['message'] ??'';
        $rsps['result'] = "success";
        return $rsps;
    }
}