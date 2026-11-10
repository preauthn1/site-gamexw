<?php
namespace app\common\payment;
use think\facade\Log;

class WkPlussPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mer_no'] = $this->pay_set['mer_no'];#商户号
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['order_amount'] = $pay_arr['apply_price']; // 交易金额
        $params['payname'] = 'proname'; // 姓名
        $params['payemail'] = strexists($pay_arr['username'],'@') ? $pay_arr['username'] : $pay_arr['username'] . "@0s.com"; // 客户邮箱
        $params['payphone'] = isset($pay_arr['phone']) && $pay_arr['phone'] ? $pay_arr['phone'] : '12345678'; // 手机号
        $params['currency'] = $this->pay_set['currency']; // 交易币种
        $params['paytypecode'] = $this->pay_set['paytypecode']; // 支付类型编码
        $params['method'] = $this->pay_set['pay_method']; // 通信类型
        $params['returnurl'] = $pay_arr['notify_url']; // 交易结果接收地址
        $params['pageurl'] = $pay_arr['call_domain']; // 同步跳转地址
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);

        Log::write(['Submit_create', "WkPluss", $params], "payment");
        $header = array(
            'Content-Type: application/json'
        );
        $res = PayRequest::curlS($this->pay_set['url'],json_encode($params),$header);
        Log::write(['Return_create', "WkPluss", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != 'success') {
            throw new \Exception($res['status_mes'] ??'支付信息错误', 0);
        }
        if (!isset($res['order_data'])) {
            throw new \Exception($res['status_mes'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['order_data'];
        $pay_info['othr_number'] = $res['sys_no'];
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
        $params['mer_no'] = $this->pay_set['mer_no'];//商户号
        $params['order_no'] = $pay_arr['order_sn'];//订单号
        $params['method'] = $this->pay_set['withdrawal_method'];//通信类型
        $params['order_amount'] = $pay_arr['amount'];//申请出款的资金
        $params['currency'] = $this->pay_set['currency'];//币种
        $params['acc_code'] = $this->pay_set['currency'] == 'BRL' ? $this->pay_set['acc_code'] : $bank_codes;//收款银行编号
        $params['acc_name'] = 'proname';//收款人姓名
        $params['acc_no'] = $pay_arr['bank_code'] == 'PHONE' ? '+55'.$pay_arr['bank_number'] : $pay_arr['bank_number'];//收款人账号 巴西PIX填(CPF，CNPJ，PHONE，EMAIL，EVP )账号；
        $params['returnurl'] = $pay_arr['notify_url'];//交易结果接收地址
        $params['province'] = $params['acc_no'];//省份，巴西必须填写CPF（11位纯数字）
        $params['otherpara1'] = $bank_name;//巴西必须PIX填账号类型(CPF，CNPJ，PHONE，EMAIL，EVP )
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);

        Log::write(['Submit_withdrawal', "WkPluss", $params], "withdrawal");
        $header = array(
            'Content-Type: application/json'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], json_encode($params),$header);
        Log::write(['Return_withdrawal', "WkPluss", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != 'success') {
            throw new \Exception("渠道返回:" . $res['status_mes']??'支付信息错误', 0);
        }
        return $res['sys_no'];
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $str = '';
        foreach ($data as $k => $v){
            if( $v !== '' && $v !== null ){
                $str .= $k.'='.$v.'&';
            }
        }
        $str = substr($str, 0, -1);
        $str .= $signKey;
        return strtolower(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        if ($data['status'] != 'success') {
            return "faile";
        }
        $rsps['order_sn'] = $data['order_no'];
        $rsps['trade_sn'] = $data['sys_no'];
        $rsps['result'] = "ok";
        $rsps['money'] = $data['order_amount'];
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);

        $rsps['status'] = 1;
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }
        if ($data['result'] != 'success') {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_no'];
        $rsps['othr_number'] = $data['sys_no'];
        $rsps['msg'] = $data['ext_info']??'';
        $rsps['result'] = "ok";
        return $rsps;
    }
}