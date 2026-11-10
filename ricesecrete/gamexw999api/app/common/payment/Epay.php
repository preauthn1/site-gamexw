<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class Epay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mch_id'] = (int)$this->pay_set['mch_id'];//商户号
        $params['pay_code'] = (int)$this->pay_set['pay_code'];//通道编码
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['price'] = intval($pay_arr['apply_price']*100); // 订单金额
        $params['app_id'] = (int)$this->pay_set['app_id'];//应用id
        $params['user_ip'] = strval($pay_arr['pay_ip']);
        $params['user_id'] = strval($pay_arr['user_id']);
        $params['pay_notice_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['pay_jump_url'] = $pay_arr['call_domain']; // 重定向
        $params['time'] = time();
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "Epay", $params], "payment");
        $req = json_encode($params,JSON_UNESCAPED_UNICODE);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "Epay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != '0') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['pay_url'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['pay_url'];
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
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['mch_account'] = $this->pay_set['mch_account'];//商户号
        $params['amount'] = intval($pay_arr['amount']*10000); // 订单金额
        if( $bank_name == 'PHONE' ){
            $params['account_no'] = '+55'.$pay_arr['bank_number']; //银行卡号
        }else{
            $params['account_no'] = $pay_arr['bank_number']; //银行卡号
        }
        $params['account_name'] = $pay_arr['account_name']; //姓名
        $params['bank_code'] = $bank_codes; //银行编码
        $params['account_type'] = 1003;
        if( $bank_name == 'CPF' ){
            $params['bank_province'] = $pay_arr['bank_number'];
            $params['account_type'] = 1007;
        }elseif( $bank_name == 'PIX' ){
            $params['bank_province'] = $params['account_no'];
        }elseif( $bank_name == 'CNPJ' ){
            $params['account_type'] = 1026;
            $params['bank_province'] = $params['account_no'];
        }else{
            $params['bank_province'] = $pay_arr['username'];
        }
        $params['bank_city'] = $pay_arr['username'];
        $params['bank_name'] = $bank_name;//银行名称
        $params['call_back_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['pay_type'] = '1';
        $params['sign'] = $this->generateSign($params, $this->pay_set['withdrawal_sign_key'],2);
        Log::write(['Submit_withdrawal', "Epay", $params], "withdrawal");
        $req = json_encode($params,JSON_UNESCAPED_UNICODE);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $req,$header);
        Log::write(['Return_withdrawal', "Epay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['ret'] != '200') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['mch_order_no'];
    }

    private function generateSign($data, $signKey,$ste = 1)
    {
        unset($data["sign"],$data["order_cost"],$data["user_ip"],$data["user_id"],$data['attach_param']);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$signKey;
        if($ste == 1){
            $str = strtoupper($str);
            $sign = md5($str);
        }else{
            $sign = strtoupper(md5($str));
        }
        return $sign;
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['code'] != '1') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
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

        $sign_str = $this->generateSign($data, $this->pay_set['withdrawal_sign_key'],2);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_no'];
        $rsps['othr_number'] = $data['mch_order_no'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}