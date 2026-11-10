<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class StarsPay
{
    private $merchant_no = "";
    private $sign_key = "";
    private $pay_type = '';
    private $withdrawal_pay_type = '';
    private $withdrawal_type = '';
    private $gateway = '';
    private $currency_type = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_no = $pay_set['merchant_no'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->pay_type = $pay_set['pay_type'];
        $this->withdrawal_pay_type = $pay_set['withdrawal_pay_type'];
        $this->withdrawal_type = isset($pay_set['withdrawal_type'])?$pay_set['withdrawal_type']:'usdt';
        $this->currency_type = $pay_set['currency_type'] ??'';
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchant_no'] = $this->merchant_no;//商户号
        $params_data = [
            'merchant_ref'=>$pay_arr['order_sn'],
            'product'=>$this->pay_type,
            'amount'=>sprintf("%.2f",$pay_arr['apply_price'])
        ];
        if($this->pay_type == 'TRC20Buy'){
            $params_data['extra'] = ['fiat_currency'=>$this->currency_type];
        }
        $params['params'] = json_encode($params_data);//业务参数
        $params['sign_type'] = 'MD5';//
        $params['timestamp'] = time();//时间戳
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "StarsPay", $params], "payment");
        $url = $this->gateway . '/api/gateway/pay';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_create', "StarsPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != '200') {
            throw new \Exception($res['message'], 0);
        }
        $data = json_decode($res['params'],true);
        $pay_info['pay_url'] = $data['payurl'];
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

        $params['merchant_no'] = $this->merchant_no;//商户号
        $params_data = [
            'merchant_ref'=>$pay_arr['order_sn'],
            'product'=>$this->withdrawal_pay_type,
            'amount'=>$pay_arr['amount']
        ];
        if($this->withdrawal_type == 'usdt'){
            $params_data['extra'] = ['address'=>$pay_arr['address']];
        }else if($this->withdrawal_type == 'ngn'){
            $params_data['extra'] = [
                'account_name'=>$pay_arr['account_name'],
                'account_no'=>$pay_arr['bank_number'],
                'bank_code'=>$pay_arr['ifsc'] ?: $bank_codes,
            ];
        }
        $params['params'] = json_encode($params_data);//业务参数
        $params['sign_type'] = 'MD5';//
        $params['timestamp'] = time();//时间戳
        $params['sign'] = $this->generateSign($params, $this->sign_key);

        $url = $this->gateway . '/api/gateway/withdraw';
        Log::write(['Submit_withdrawal', "StarsPay", $params], "withdrawal");
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal', "StarsPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != '200') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        $data = json_decode($res['params'],true);
        return $data['system_ref'];
    }

    private function generateSign($data, $signKey){
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $value;
            }
        }
        $str .= $signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $data2 = json_decode($data['params'],true);
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if($data2['status'] != 1){
            return "faile";
        }
        $sign_data = [
            'merchant_no'=>$data['merchant_no'],
            'params'=> $data['params'],
            'sign_type'=>'MD5',
            'timestamp'=>$data['timestamp']
        ];
        $sign_str = $this->generateSign($sign_data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data2['merchant_ref'];
        $rsps['trade_sn'] = $data2['system_ref'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data2['pay_amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $data2 = json_decode($data['params'],true);
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        $rsps['status'] = 1;
        if($data2['status'] != 1){
            $rsps['status'] = 2;
        }
        $sign_data = [
            'merchant_no'=>$data['merchant_no'],
            'params'=> $data['params'],
            'sign_type'=>'MD5',
            'timestamp'=>$data['timestamp']
        ];
        $sign_str = $this->generateSign($sign_data, $this->sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data2['merchant_ref'];
        $rsps['othr_number'] = $data2['system_ref'];
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}
