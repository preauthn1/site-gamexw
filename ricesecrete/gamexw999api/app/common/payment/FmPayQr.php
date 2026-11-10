<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class FmPayQr
{	
    private $partner_id="";
    private $sign_key="";
    private $withdrawal_key="";
    private $bank_code="";
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->partner_id=$pay_set['seller'];
        $this->sign_key=$pay_set['pay_key'];
        $this->withdrawal_key=$pay_set['withdrawal_key'];
        $this->bank_code=$pay_set['bank_code'] ??'';
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['partner_id'] =$this->partner_id;//商户号
        $params['pay_type'] = '0001';// 支付类型：0001 固定值
        // $params['bank_code'] = $pay_arr['bank_code'];//银行代码
        $params['version'] = 'V1.0'; // 接口版本: API协议版本，当前值：V1.0 (大写)
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号: 商家网站生成的订单号，由商户保证其唯一性，由字 母、数字、下划线组成，字符长度不超过32位.
        $params['amount'] = $pay_arr['apply_price']; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['attach'] = 'json';// 是否返回json
        $params['notify_url'] = $this->domain.'/notify/FmPayQr';// 异步通知地址

        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create',"FmPayQr",$params], "payment");
        $url='https://gateway.fmpay.org/payment/gateway';
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_create',"FmPay",$res], "payment");
        $res=json_decode($res,true);
        if (!isset($res['payUrl'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']=$res['payUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['partner_id'] =$this->partner_id;//商户号
        $params['bank_code'] = $pay_arr['bank_code'] ?$pay_arr['bank_code']:$this->bank_code;//银行代码
        $params['account_no'] = $pay_arr['bank_number'];//银行账号
        $params['account_name'] = $pay_arr['account_name'];//收款人姓名
        $params['account_id'] = $pay_arr['ifsc'];//IFSC
        $params['version'] = 'V1.0'; // 接口版本: API协议版本，当前值：V1.0 (大写)
        $params['order_no'] = $pay_arr['order_sn']; // 商户订单号: 商家网站生成的订单号，由商户保证其唯一性，由字 母、数字、下划线组成，字符长度不超过32位.
        $params['amount'] = $pay_arr['amount']; // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['notify_url'] = $this->domain.'/withdrawal/notify/FmPayQr';// 异步通知地址

        $params['sign'] = $this->generateSign($params, $this->withdrawal_key);
        Log::write(['Submit_withdrawal',"FmPayQr",$params], "withdrawal");
        $url='https://gateway.fmpay.org/withdraw/gateway';
        $res=PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"FmPayQr",$res], "withdrawal");
        $res=json_decode($res,true);
        if (!isset($res['trade_no'])) {
            throw new \Exception('代付信息错误：'.(isset($res['message'])?$res['message']:''), 0);
        }
        return $res['trade_no'];
    }

    private function generateSign($data, $signKey){
        // 按照ASCII码升序排序
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= $signKey;
        return strtolower(md5($str));
    }


    //异步回调支付
    public function notify($post_data){
        $notify_data['code'] = isset($post_data['code'])?$post_data['code']:'';
        $notify_data['message'] = isset($post_data['message'])?$post_data['message']:'';
        $notify_data['order_no'] = isset($post_data['order_no'])?$post_data['order_no']:'';//商户订单号
        $notify_data['trade_no'] = isset($post_data['trade_no'])?$post_data['trade_no']:'';//系统生成的唯一单号
        $notify_data['amount'] = isset($post_data['amount'])?$post_data['amount']:'';
        $notify_data['partner_id'] = isset($post_data['partner_id'])?$post_data['partner_id']:'';
        $notify_data['attach'] = isset($post_data['attach'])?$post_data['attach']:'';
        $notify_sign = isset($post_data['sign'])?$post_data['sign']:'';
        if (empty($notify_data['partner_id'])) {
            unset($notify_data['partner_id']);//删除
        }
        if (empty($notify_data['attach'])) {
            unset($notify_data['attach']);//删除
        }
        
        if($notify_data['code'] != "00"){
            return "faile";
        }
        $sign_str =$this->generateSign($notify_data, $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            return "sign error";
        }
        
        $rsps['order_sn'] = $notify_data['order_no'];
        $rsps['trade_sn'] = $notify_data['trade_no'];
        $rsps['result'] = "ok";
        return $rsps;
    }

    //异步回调支付
    public function notify_withdrawal($post_data){
        $notify_data['code'] = isset($post_data['code'])?$post_data['code']:'';
        $notify_data['message'] = isset($post_data['message'])?$post_data['message']:'';
        $notify_data['order_no'] = isset($post_data['order_no'])?$post_data['order_no']:'';//商户订单号
        $notify_data['trade_no'] = isset($post_data['trade_no'])?$post_data['trade_no']:'';//系统生成的唯一单号
        $notify_data['amount'] = isset($post_data['amount'])?$post_data['amount']:'';
        $notify_data['partner_id'] = isset($post_data['partner_id'])?$post_data['partner_id']:'';
        $notify_data['attach'] = isset($post_data['attach'])?$post_data['attach']:'';
        $notify_sign = isset($post_data['sign'])?$post_data['sign']:'';
        $rsps = [];
        $rsps['status'] = 1;
        if (empty($notify_data['partner_id'])) {
            unset($notify_data['partner_id']);//删除
        }
        if (empty($notify_data['attach'])) {
            unset($notify_data['attach']);//删除
        }

        //returncode==00 是交易成功
        if($notify_data['code'] != "00"){
            $rsps['status'] = 2;
        }
        $sign_str =$this->generateSign($notify_data, $this->withdrawal_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            $rsps['status'] = 2;
        }
        
        $rsps['order_sn'] = $notify_data['order_no'];
        $rsps['othr_number'] = $notify_data['trade_no'];
        $rsps['msg'] = '';
        $rsps['result'] = "ok";
        return $rsps;
    }	
}