<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class TpnbPay
{

    private $sign_key="";
    private $withdrawal_sign_key="";
    private $payType="";
    private $gateway = '';
    private $bank_code = '';
    private $loginname = '';
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->sign_key=$pay_set['sign_key'];
        $this->withdrawal_sign_key=$pay_set['withdrawal_sign_key'];
        $this->payType=$pay_set['payType'];
        $this->gateway = $pay_set['gateway'];
        $this->bank_code = $pay_set['bank_code'] ??'';
        $this->loginname = $pay_set['loginname'] ??'';
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['qrcode_type'] = 4;
        $params['merchantnum'] = $pay_arr['order_sn']; // 商户订单号
        $params['bzmoneyid'] = intval($pay_arr['apply_price']); // 订单金额
        $params['loginname'] = $this->loginname;   //登录名
        $params['merchanturl'] = $this->domain.'/notify/TpnbPay';// 异步通知地址
        $params['playername'] = $pay_arr['username'];
        $params['qudao_type'] = $this->payType;             //支付类型
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create',"TpnbPay",$params], "payment");
        $url= $this->gateway.'/app/selectGetQrcode';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"TpnbPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['systate'] != '0') {
            throw new \Exception('支付信息错误', 0);
        }

        $pay_info['pay_url'] = $res['data']['url'];
        return $pay_info;
    }


    //获取代付信息
    public function create_withdrawal($pay_arr){
        $params['dfusername'] = $this->loginname;   //登录名
        $params['money'] = intval($pay_arr['amount']); // 订单金额
        $params['orderno'] = $pay_arr['order_sn']; //订单号
        $params['skcardno'] = $pay_arr['bank_number']; // 银行卡号
        $params['skcardname'] = $pay_arr['account_name']; // 姓名
        $params['skbankid'] = $pay_arr['bank_code'] ? $pay_arr['bank_code']:$this->bank_code; // 收款银行类型
        $params['hdurli'] = $this->domain.'/withdrawal/notify/TpnbPay';// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->withdrawal_sign_key);
        Log::write(['Submit_withdrawal',"TpnbPay",$params], "withdrawal");
        $url= $this->gateway.'/app/DFPayFun';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"TpnbPay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['systate'] != '0') {
            throw new \Exception("渠道返回:".$res['msg'], 0);
        }
        return $res['data'];
    }

    private function generateSign($data, $signKey){
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value && $key != 'playername') {
                $str .= $value;
            }
        }
        $str .= '{'.$signKey.'}';
        return md5($str);
    }


    //异步回调支付
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        unset($data['sign']);//不参与签名


        $sign_str =$this->generateSign(['merchantnum' => $data['merchantnum'], 'turemoney' => $data['turemoney'], 'qrcode_type' => $data['qrcode_type'], 'merchanturl ' => $data['merchanturl'],'ptorder' => $data['ptorder'],'qudao_type' => $data['qudao_type']], $this->sign_key);
        if($sign_str != $notify_sign){
            return "sign error";
        }
        $rsps['order_sn'] = $data['merchantnum'];
        $rsps['trade_sn'] = $data['ptorder'];
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }


    //代付异步回调支付
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['orderstate'] != 1){
            $rsps['status'] = 2;
        }
        $sign_data = [
            'orderno'=>$notify_data['orderno'],
            'orderstate'=>$notify_data['orderstate'],
            'truedfmoney'=>$notify_data['truedfmoney'],
            'endtime'=>$notify_data['endtime'],
        ];
        $sign_str =$this->generateSign($sign_data, $this->withdrawal_sign_key);
        if($sign_str != $notify_sign){
            $rsps['status'] = 2;
        }
        $rsps['order_sn']=$notify_data['orderno'];
        $rsps['othr_number']="";
        $rsps['msg'] = "";
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

}