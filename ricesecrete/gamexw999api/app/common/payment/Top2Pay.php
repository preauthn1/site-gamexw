<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\Exception;
use think\facade\Log;
class Top2Pay
{

    private $pay_set = [];
    public function __construct($pay_set,$domain){
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchantCode'] = $this->pay_set['merchantCode'];#商户号
        $params['orderType'] = $this->pay_set['orderType'];#订单类型 0-法币(BRL)交易
        $params['orderNum'] = $pay_arr['order_sn'];#订单号
        $params['payMoney'] = $pay_arr['apply_price'];#金额
        $params['notifyUrl'] = $pay_arr['notify_url'];#回调地址
        $params['name'] = $pay_arr['username'];#名称
        $params['description'] = 'goods';#描述
        $params['sign'] = $this->createSign($params, $this->pay_set['private_key']);
        $header = array(
            'Content-Type: application/json',
        );
        Log::write(['Submit_create',"Top2Pay",$params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],json_encode($params),$header);
        Log::write(['Return_create',"Top2Pay",$res], "payment");
        $res = json_decode($res,true);
        if(!$res || !isset($res['platRespCode']) || $res['platRespCode'] != 'SUCCESS'){
            throw new Exception($res['platRespMessage'] ?? '支付信息错误', 0);
        }
        if ( !isset($res['url']) ) {
            throw new \Exception($res['platRespMessage'] ?? '支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['url'];
        $pay_info['othr_number'] = $res['platOrderNum'];
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

        $params['merchantCode'] = $this->pay_set['merchantCode'];//商户号
        $params['orderType'] = $this->pay_set['orderType'];#订单类型 0-法币(BRL)交易
        $params['method'] = $this->pay_set['method'];#默认传DISBURSEMENT
        $params['orderNum'] = $pay_arr['order_sn'];#订单号
        $params['money'] = $pay_arr['amount'];#金额
        $params['feeType'] = $this->pay_set['feeType'];#0：代付金额内扣除，1：手续费另计
        $params['name'] = $pay_arr['username'];//账户姓名
        $params['pixType'] = $bank_name;//PIX类型
        $params['pixAccount'] = ($bank_name == 'PHONE' ? '+55' : '').($pay_arr['bank_number'] ?: $pay_arr['bank_number'] ??'0815648636');//账户
        $params['taxNumber'] = $pay_arr['bank_number'];//CPF/CNPJ税号
        $params['notifyUrl'] = $pay_arr['notify_url'];//回调地址

        $params['sign'] = $this->createSign($params, $this->pay_set['private_key']);

        $header = [
            'Content-Type: application/json',
        ];
        Log::write(['Submit_withdrawal', "Top2Pay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],json_encode($params), $header);
        Log::write(['Return_withdrawal', "Top2Pay", $res], "withdrawal");
        $res = json_decode($res, true);
        if(!$res || !isset($res['platRespCode']) || $res['platRespCode'] != 'SUCCESS' || !isset($res['platOrderNum'])){
            throw new Exception($res['platRespMessage'] ?? '支付信息错误', 0);
        }
        if ( $res['status'] == 4 ) {
            throw new Exception($res['platRespMessage'] ?? '支付信息错误', 0);
        }
        return $res['platOrderNum'];
    }

    private function createSign($params, $key)
    {
        //按 ASCII 码排序
        ksort($params);
        // 拼接参数值
        $params_str = implode('', $params);

        $pivate_key = 'REDACTED';
        $pi_key = openssl_pkey_get_private($pivate_key);
        $crypto = '';
        foreach (str_split($params_str, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $pi_key);
            $crypto .= $encryptData;
        }

        return base64_encode($crypto);
    }

    function verifySign($data, $public_key)
    {
        $public_key = '-----BEGIN PUBLIC KEY-----'."\n".$public_key."\n".'-----END PUBLIC KEY-----';
        $data = base64_decode($data);
        $pu_key =  openssl_pkey_get_public($public_key);
        $crypto = '';
        foreach (str_split($data, 128) as $chunk) {
            openssl_public_decrypt($chunk, $decryptData, $pu_key);
            $crypto .= $decryptData;
        }

        return $crypto;
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = $data['platSign'];
        unset($data['platSign']);
        //验证签名
        $decryptSign = $this->verifySign($sign, $this->pay_set['public_key']);
        ksort($data);
        $params_str = '';
        foreach ($data as $val) {
            $params_str = $params_str . $val;
        }
        if($params_str != $decryptSign) {
            return 'faile';
        }
        if( $data['status'] != 'SUCCESS' ){
            return 'faile';
        }
        $rsps['order_sn'] = $data['orderNum'];
        $rsps['trade_sn'] = $data['platOrderNum'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['payMoney'] ?? 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = $data['platSign'];
        unset($data['platSign']);
        //验证签名
        $decryptSign = $this->verifySign($sign, $this->pay_set['public_key']);
        ksort($data);
        $params_str = '';
        foreach ($data as $val) {
            $params_str = $params_str . $val;
        }
        if($params_str != $decryptSign) {
            throw new Exception('faile');
        }

        if( $data['status'] == 2 ){
            $rsps['status'] = 1;
        }elseif ($data['status'] == 4){
            $rsps['status'] = 2;
        }else{
            throw new Exception('faile');
        }

        $rsps['order_sn'] = $data['orderNum'];
        $rsps['othr_number'] = $data['platOrderNum'];
        $rsps['msg'] = $data['statusMsg']??'';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}