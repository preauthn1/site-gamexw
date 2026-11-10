<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

//福盈国际
class FYGJPay
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
        $params['MerchantID'] = $this->pay_set['MerchantID'];//商户号
        $params['OrderID'] = $pay_arr['order_sn']; // 商户订单号
        $params['Date'] = date('YmdHis'); // 提交时间(yyyyMMddHHmmss)
        $params['NotifyUrl'] = $pay_arr['notify_url']; // 异步通知
        $params['CallBackUrl'] = $pay_arr['call_domain']; // 页面跳转地址(POST返回数据)
        $params['Amount'] = intval($pay_arr['apply_price']); // Amount 交易金额 否 是 订单金额(单位:元)
        $params['MerchantNumber'] = '1005';
        $params['UserName'] = $pay_arr['username'];
        $params['Sign'] = $this->generateSignature($params, $this->pay_set['sign_key']);
        $req = json_encode($params);
        Log::write(['Submit_create', "FYGJPay", $params], "payment");
        $header = array(
            'Content-Type: application/json;'
        );
        $res = PayRequest::curlS($this->pay_set['urls'],$req, $header);
        Log::write(['Return_create', "FYGJPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  !$res['Code'] || $res['Code'] != 'Success') {
            throw new \Exception($res['Msg'] ??lang('支付信息错误'), 0);
        }
        if (!isset($res['Url'])) {
            throw new \Exception($res['Msg'] ??lang('支付信息错误'), 0);
        }
        $pay_info['pay_url'] = $res['Url'];
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
        $params['MerchantID'] = $this->pay_set['MerchantID'];//平台分配商户ID
        $params['OrderID'] = $pay_arr['order_sn']; // OrderID 订单号 否 是 商户订单号(最大长度38,且全局唯一)
        $params['Date'] = date('YmdHis');//提交时间(yyyyMMddHHmmss)
        $params['NotifyUrl'] = $pay_arr['notify_url'];//NotifyUrl 异步通知地址 否 是 异步通知地址(POST返回数据)
        $params['Amount'] = intval($pay_arr['amount']);//Amount 交易金额 否 是 订单金额(单位:元)
        $params['MerchantNumber'] = 2002;
        $params['Id'] = $pay_arr['bank_number'];//1 cpf 2 email 3 phone(需加巴西区号 例如+55)
//        $params['Type'] = 1;//1 cpf 2 email 3 phone(需加巴西区号 例如+55) 4 EVP 5 CNPJ

        $params['Account'] = $pay_arr['bank_number'];
        if( $bank_name == 'CPF' ){
            $params['Type'] = 1;
        }else if( $bank_name == 'CNPJ' ){
            $params['Type'] = 5;
        }elseif( $bank_name == 'Email' ){
            $params['Type'] = 2;
        }elseif( $bank_name == 'Phone' ){
            $params['Account'] = '+55'. ($pay_arr['bank_number'] ?: $pay_arr['bank_number'] ??'0815648636');
            $params['Type'] = 3;
        }else{
            $params['Type'] = 4;
        }

        $params['Sign'] = $this->generateSignature($params, $this->pay_set['sign_key']);
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;'
        );
        Log::write(['Submit_withdrawal', "FYGJPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $req, $header);
        Log::write(['Return_withdrawal', "FYGJPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['Code'] != 'Success') {
            throw new \Exception($res['Msg'] ??lang('支付信息错误'), 0);
        }
        if (empty($res) || !$res['Code'] || $res['Code'] != 'Success') {
            throw new \Exception(lang("渠道返回:") . $res['Msg'], 0);
        }
        return $res['SysOrderID'] ?? '';
    }

    //生成签名
    private function generateSignature($params, $merchantKey) {
        unset($params['Id'], $params['UserName'], $params['Sign'], $params['Id'], $params['Type'], $params['Account']);
        if( isset($params['Remarks']) ){
            unset($params['Remarks']);
        }
        // 将参数按照 ASCII 码从小到大排序
        ksort($params);
        // 拼接参数成 URL 键值对格式的字符串
        $paramStr = '';
        foreach ($params as $key => $value) {
            $paramStr .= $key . '=' . $value . '&';
        }
        // 去掉末尾的 '&' 字符
        $paramStr = rtrim($paramStr, '&');
        // 在字符串末尾拼接商户密钥
        $paramStr .= $merchantKey;
        // 对字符串进行 MD5 计算，并转换为大写表示
        $signature = strtoupper(md5($paramStr));
        return $signature;
    }

    //异步回调支付
    public function notify($data)
    {
        $sign  = $this->generateSignature($data, $this->pay_set['sign_key']);
        if ($sign != $data['Sign']) {
            return "sign error";
        }
        $status = $data['status'] ?? $data['Status'];
        //订单状态(0:失败,1:成功 2:待支付)
        if ($status != 1) {
            return 'fail';
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $data['OrderID'];
        $rsps['trade_sn'] = $data['SysOrderID'];
        $rsps['result'] = 'success';
        $rsps['money'] = $data['Amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign  = $this->generateSignature($data, $this->pay_set['sign_key']);
        if ($sign != $data['Sign']) {
            return "sign error";
        }
        $rsps['status'] = 1;
        $status = $data['status'] ?? $data['Status'];
        if ($status != 1) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['OrderID'];
        $rsps['othr_number'] = '';
//        $rsps['othr_number'] = $data['SysOrderID'];#三方创建代付单没有返回改参数
        $rsps['msg'] = $data['Remarks'] ??'';
        $rsps['result'] = "success";
        return $rsps;
    }
}