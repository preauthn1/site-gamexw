<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class VgPay
{
    private $domain="";
    private $sign_key="";
    private $businessId="";
    private $pay_url="";
    private $coin="";
    private $notify_type="";

    public function __construct($pay_set,$domain){
        $this->businessId=$pay_set['businessId'];
        $this->sign_key=$pay_set['sign_key'];
        $this->pay_url=$pay_set['pay_url'];
        $this->coin=$pay_set['coin'];
        $this->notify_type=$pay_set['notify_type'] ??0;
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['businessId'] = $this->businessId;
        $params['outOrderNo'] = $pay_arr['order_sn']; // 订单号
        $params['paymentUserId'] =$pay_arr['user_id'];  // userid
        $params['coin'] = $this->coin; // 币种标识(BTC、ETH、ERC20_USDT、TRC20_USDT)
        $params['amount'] =  $pay_arr['apply_price']; // 支付数量,取值范围：[0.00001, ∞ ]
        $params['orderType'] = 1; // 订单类型(0、消费 1、充值)
        $params['timeStamp']=time();
        $params['callBackUrl']=$this->domain.'/notify/VgPay';//回调地址(注：优先回调该参数callBackUrl,没有填写时，用商家设置的回调地址回调)
        Log::write(['Submit_create',"VgPay",$params], "payment");
        $query = $this->joinMap($params);
        $url = $this->pay_url.'/api/v3/Payment?'.$query;
        
        $res=PayRequest::curlS($url,[]);
        Log::write(['Return_create',"VgPay",$res], "payment");
        $res=json_decode($res,true);
        if ($res['code'] != 200 || $res['isSuccess'] != true) {
            throw new \Exception('支付信息错误', 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $data = $res['data'];
        $pay_info['pay_url']=$data['paymentUrl']."&lang=en";
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['businessId'] = $this->businessId;
        $params['outWithdrawalNo'] = $pay_arr['order_sn']; // 订单号
        $params['withdrawalUserId'] =$pay_arr['user_id'];  // userid
        $params['coin'] = $this->coin; // 币种标识(BTC、ETH、ERC20_USDT、TRC20_USDT)
        $params['amount'] =  $pay_arr['amount']; // 支付数量,取值范围：[0.00001, ∞ ]
        $params['address'] = $pay_arr['address']; // 订单类型(0、消费 1、充值)
        $params['timeStamp']=time();
        $params['callBackUrl']=$this->domain.'/withdrawal/notify/VgPay';//回调地址(注：优先回调该参数callBackUrl,没有填写时，用商家设置的回调地址回调)
        Log::write(['Submit_withdrawal',"VgPay",$params], "withdrawal");
        $res=PayRequest::curlS($this->pay_url.'/api/v3/Withdrawal?'.$this->joinMap($params),[]);
        Log::write(['Return_withdrawal',"VgPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if ($res['code'] != 200 || $res['isSuccess'] != true) {
            throw new \Exception($res['msg']??'支付信息错误', 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $data = $res['data'];
        return $data['withdrawalNo'];
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['mac'];
        unset($notify_data['mac']);
        if ($sign!=$this->get_sign($notify_data)){
            return 'faile2';
        }
        
        $status = $notify_data["status"];
        if ($status==4 || $status==5){
            $rsps['order_sn'] = $notify_data['outOrderNo'];
            $rsps['trade_sn'] = $notify_data['orderNo'];
            $rsps['money'] = isset($notify_data['actualPaymentAmount'])?$notify_data['actualPaymentAmount']:$notify_data['amount'];
            if($this->notify_type == 1){
                $rsps['result'] = ['isSuccess'=>'true','message'=>'success'];
            }else{
                $rsps['result'] = "success";
            }
            return $rsps;
        }else{
            return 'faile1';
        }
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['mac'];
        $rsps = [];
        $rsps['status'] = 1;
        unset($notify_data['mac']);
        if ($sign!=$this->get_sign($notify_data)){
            $rsps['status'] = 2;
        }else{
            $status = $notify_data["status"];
            if($status != 5 ){
                $rsps['status'] = 2;
            }
        }
        $outWithdrawalNo = isset($notify_data["outWithdrawalNo"]) ? $notify_data["outWithdrawalNo"]: "";
        $withdrawalNo = isset($notify_data["withdrawalNo"]) ? $notify_data["withdrawalNo"]: "";
        
        $rsps['order_sn'] = $outWithdrawalNo;
        $rsps['othr_number'] = $withdrawalNo;
        $rsps['msg'] = '';
        if($this->notify_type == 1){
            $rsps['result'] = ['isSuccess'=>'true','message'=>'success'];
        }else{
            $rsps['result'] = "success";
        }
        return $rsps;
    }

    private function joinMap($data){
        //签名步骤一：按字典序排序参数
        ksort($data);
        $str="";
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('mac' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "mac=".$this->get_sign($data);
        return $str;
    }
    //获取签名
    private function get_sign($data){
        //签名步骤一：按字典序排序参数
        ksort($data);
        $str="";
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('mac' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= "secretKey=".$this->sign_key;
        return md5($str);
    }
}