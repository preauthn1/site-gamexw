<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class EpayNew
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['trade_no'] = (int)$this->pay_set['mch_id'];//商户号
        $params['app_id'] = (int)$this->pay_set['app_id'];//应用id
        $params['pay_code'] = 2010;//产品编码
        $params['price'] = intval($pay_arr['apply_price']*100); // 订单金额
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['success_url'] = $pay_arr['call_domain']; // 重定向
        $params['fail_url'] = $pay_arr['call_domain']; // 重定向
        $params['pay_notice_url'] = $pay_arr['notify_url']; // 异步通知地址
//        $params['user_id'] = strval($pay_arr['user_id']); //付款人cpf
//        $params['user_ip'] = strval($pay_arr['pay_ip']); //付款IP
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);

        Log::write(['Submit_create', "EpayNew", $params], "payment");
        $req = json_encode($params,JSON_UNESCAPED_UNICODE);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "EpayNew", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != 200) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['pay_url'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['pay_url'];
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
        $params['trade_no'] = (int)$this->pay_set['mch_id'];//商户号
        $params['order_no'] = $pay_arr['order_sn'];//商户订单号
        $params['app_id'] = (int)$this->pay_set['app_id'];//应用id
        $params['price'] = intval($pay_arr['amount']*100); // 订单金额
        if( $bank_name == 'PHONE' ){
            $params['account_no'] = '+55'.$pay_arr['bank_number']; //银行卡号
        }else{
            $params['account_no'] = $pay_arr['bank_number']; //银行卡号
        }
        $params['account_type'] = $bank_name; //账号类型:CPF,CNPJ,EMAIL,PHONE
        $params['account_name'] = $pay_arr['account_name']; //姓名
        $params['bank_code'] = $this->pay_set['bank_code']; //固定值:PIX
        $params['pay_notice_url'] = $pay_arr['notify_url']; // 异步通知地址
        if( $bank_name == 'CPF' ){
            $params['pay_code'] = 2014;
        }elseif ( $bank_name == 'CNPJ' ){
            $params['pay_code'] = 2016;
        }else{
            $params['pay_code'] = 2015;
        }

        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "EpayNew", $params], "withdrawal");
        $req = json_encode($params,JSON_UNESCAPED_UNICODE);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $req,$header);
        Log::write(['Return_withdrawal', "EpayNew", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != '200') {
            throw new \Exception("渠道返回:" . $res['msg']??'支付信息错误', 0);
        }
        return $res['dis_order_no'];
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        foreach ($data as $k => $v){
            if( empty($v) ){
                unset($data[$k]);
            }
        }
        ksort($data);
        // 拼接参数成 URL 键值对格式的字符串
        $paramStr = '';
        foreach ($data as $key => $value) {
            $paramStr .= $key . '=' . $value . '&';
        }
        $paramStr = $paramStr . 'key='.$signKey;
        return md5($paramStr);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        if ($data['status'] != 2) {
            return 'payment error';
        }
        $rsps['order_sn'] = $data['order_no'];
        $rsps['trade_sn'] = $data['dis_order_no'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['real_price'] ? $data['real_price']/100 : 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != '2') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return 'sign error';
        }

        $rsps['order_sn'] = $data['order_no'];
        $rsps['othr_number'] = $data['dis_order_no'];
        $rsps['msg'] = $data['remark'] ?? '';
        $rsps['result'] = "success";
        return $rsps;
    }
}