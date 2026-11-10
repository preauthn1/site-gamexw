<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class WowPay
{

    private $appid="";
    private $sign_key="";
    private $withdrawal_key="";
    private $pay_type="";
    private $bank_code="";
    private $url="";
    private $withdrawal_url="";
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->appid = $pay_set['appid'];
        $this->sign_key = $pay_set['private_key'];
        $this->withdrawal_key = $pay_set['withdrawal_key'];
        $this->pay_type = $pay_set['pay_type'];
        $this->bank_code = $pay_set['bank_code'] ??'';
        $this->url = $pay_set['url'];
        $this->withdrawal_url = $pay_set['withdrawal_url'];
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['mch_id'] =$this->appid;//商户号
        $params['pay_type'] = $this->pay_type;// 支付类型：
        $params['version'] = '1.0'; // 接口版本: API协议版本，当前值：V1.0 (大写)
        $params['mch_order_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['trade_amount'] = $pay_arr['apply_price']; // 订单金额
        $params['order_date'] = $pay_arr['add_time']; // 时间
        $params['goods_name'] = $pay_arr['username']; // 商品名称
        if(!empty($this->bank_code)){
            $params['bank_code'] = $this->bank_code;
        }
        $params['notify_url'] = $this->domain.'/notify/WowPay';// 异步通知地址
        $params['page_url'] = $pay_arr['call_domain'];//同步通知地址
        $params['sign'] =$this->sign($params,$this->sign_key);
        $params['sign_type'] = "MD5"; // 签名类型-不参与签名
        Log::write(['Submit_create',"WowPay",$params], "payment");
        $res = PayRequest::curlS($this->url,$params);
        Log::write(['Return_create',"WowPay",$res], "payment");
        $res = json_decode($res,true);
        if($res['respCode'] != "SUCCESS"){
            throw new \Exception($res['tradeMsg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['payInfo'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['mch_id'] =$this->appid;//商户号
        $params['mch_transferId'] = $pay_arr['order_sn']; // 商户订单号
        $params['transfer_amount'] = $pay_arr['amount']; // 订单金额
        $params['apply_date'] = date("Y-m-d H:i:s"); // 时间
        $params['back_url'] = $this->domain.'/withdrawal/notify/WowPay';// 异步通知地址
        $params['bank_code'] = $pay_arr['bank_code'] ?$pay_arr['bank_code']:$this->bank_code; // 收款银行代码
        $params['receive_name'] = $pay_arr['account_name']; //
        $params['receive_account'] = $pay_arr['bank_number']; // 收款银行户名
        $params['receive_telephone'] = (substr($pay_arr['phone'],0,2) == '91') ? '65894513':$pay_arr['phone']; // 手机号
        $params['remark'] = $pay_arr['ifsc']; // ifsc
        $params['sign'] =$this->sign($params,$this->withdrawal_key);
        $params['sign_type'] = "MD5"; // 签名类型-不参与签名
        Log::write(['Submit_withdrawal',"WowPay",$params], "withdrawal");
        $res=PayRequest::curlS($this->withdrawal_url,$params);
        Log::write(['Return_withdrawal',"WowPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if (!isset($res['tradeNo'])) {
            if($res['errorMsg'] == "BALANCE_NOT_ENOUGH"){
                throw new \Exception('商户余额不足', 0);
            }else{
                throw new \Exception('渠道返回'.$res['errorMsg'], 0);
            }
        }
        return $res['tradeNo'];
    }

    // 签名验证
    private function sign($data,$key) {
        //去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a="";
        foreach ($data as $key1 => $val) {
            if ('sign' != $key1 && '' != $val && $key1 != 'receive_telephone') {
                $string_a .= $key1 . "=" . $val . "&";
            }
        }
        $string_a .= "key=" .$key;
        return md5($string_a);
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $notify_sign=$notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        unset($notify_data['signType']);//不参与签名
        if ($notify_data['tradeResult']!=1) {
            return "status error";
        }
        $sign = $this->sign($notify_data,$this->sign_key);
        if($sign != $notify_sign){
            return "sign error";
        }

        $rsps['order_sn'] = $notify_data['mchOrderNo'];
        $rsps['trade_sn'] = $notify_data['orderNo'];
        $rsps['result'] = "success";
        $rsps['money'] = isset($notify_data['amount'])?$notify_data['amount']:$notify_data['oriAmount'];
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign=$notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        unset($notify_data['signType']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if ($notify_data['tradeResult']!=1) {
            $rsps['status'] = 2;
        }

        $sign =$this->sign($notify_data,$this->withdrawal_key);
        if($sign != $notify_sign){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $notify_data['merTransferId'];
        $rsps['othr_number'] = $notify_data['tradeNo'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}