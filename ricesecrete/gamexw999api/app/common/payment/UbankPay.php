<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
use app\common\model\UserWithdrawalModel;
class UbankPay
{

    private $appId="";
    private $sign_key="";
    private $payType="";
    private $withdrawal_paytype="";
    private $url="";
    private $withdrawal_url="";
    private $domain="";
    private $channel="";

    public function __construct($pay_set,$domain){
        $this->appId=$pay_set['appId'];
        $this->sign_key=$pay_set['sign_key'];
        $this->payType=$pay_set['payType'];
        $this->withdrawal_paytype=$pay_set['withdrawal_paytype'];
        $this->url = $pay_set['url'];
        $this->withdrawal_url=$pay_set['withdrawal_url'];
        $this->channel=$pay_set['channel'] ??'';
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['appId'] = $this->appId;
        $params['appOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['orderAmt'] =  sprintf("%.2f",$pay_arr['apply_price']); // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['payId'] = $this->payType;             //支付类型 101 卡转卡 101 卡转卡 201 卡转GCash 202 GCash转GCash
        $params['notifyURL'] = $this->domain.'/notify/UbankPay';// 异步通知地址
        $params['jumpURL'] = $pay_arr['call_domain'];
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create',"UbankPay",$params], "payment");
        $res = PayRequest::curlS($this->url,$params);
        Log::write(['Return_create',"UbankPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != '0000') {
            throw new \Exception($res['msg']??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['payUrl'];
        return $pay_info;
    }


    //获取代付信息
    public function create_withdrawal($pay_arr){
        $params['appId'] = $this->appId;
        $params['appOrderNo'] = $pay_arr['order_sn']; //订单号
        $params['orderAmt'] = sprintf("%.2f",$pay_arr['amount']); // 订单金额: 以元为单位，精确到小数点后两位.例如:12.00、14.01等
        $params['payId'] = $this->withdrawal_paytype;      // 类型
        $params['accNo'] = $pay_arr['bank_number']; // 银行卡号
        $params['accName'] = $pay_arr['account_name']; // 姓名
        $params['bankName'] =  '';
        $params['notifyURL'] = $this->domain.'/withdrawal/notify/UbankPay';// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_withdrawal',"UbankPay",$params], "withdrawal");
        $res = PayRequest::curlS($this->withdrawal_url,$params);
        Log::write(['Return_withdrawal',"UbankPay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] !== '0000') {
            throw new \Exception("渠道返回:".$res['msg'], 0);
        }
        return $res['data']['orderNo'];
    }

    private function generateSign($data, $signKey){
        ksort($data);
        $str = '';
        unset($data['sign'],$data['notifyURL'],$data['jumpURL']);
        foreach ($data as $key => $value) {
            $value = trim($value);
            $str .= $key . '=' . $value . '&';
        }
        $str .= "key=".$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        unset($data['sign']);//不参与签名
        if($data['orderStatus'] != '00'){
            return "faile";
        }

        $data = [
            'appId' => $this->appId,
            'appOrderNo' => $data['appOrderNo'],
            'orderAmt' => $data['orderAmt'],
            'orderNo' => $data['orderNo'],
            'orderStatus' => $data['orderStatus'],
            'orderTime' => $data['orderTime'],
            'payAmt' => $data['payAmt']
        ];
        $sign_str =$this->generateSign($data, $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            return "sign error";
        }
        $rsps['order_sn'] = $data['appOrderNo'];
        $rsps['trade_sn'] = $data['orderNo'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = isset($data['payAmt'])?$data['payAmt']:$data['orderAmt'];
        return $rsps;
    }


    //代付异步回调支付
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        unset($notify_data['sign']);//不参与签名
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['orderStatus'] != '02'){
            $rsps['status'] = 2;
        }
        $data_sign = [
            "appId" => $this->appId,
            "appOrderNo" => $notify_data['appOrderNo'],
            "orderNo" => $notify_data['orderNo'],
            "orderTime" => $notify_data['orderTime'],
            "orderAmt" => $notify_data['orderAmt'],
            "orderFee" => $notify_data['orderFee'],
            "orderStatus" => $notify_data['orderStatus'],
        ];
        $sign_str =$this->generateSign($data_sign, $this->sign_key);
        if(strtolower($sign_str) != strtolower($notify_sign)){
            $rsps['status'] = 2;
        }
        $rsps['order_sn']=$notify_data['appOrderNo'];
        $rsps['othr_number']=$notify_data['orderNo'];
        $rsps['msg'] = "";
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    //代付订单查询
    public function getorder($post_data){
        $code = '0000';$msg = 'success';
        $str = $this->generateSign($post_data,$this->sign_key);
        if($post_data['sign'] != $str){
            $code = '1';$msg = 'sign error';
        }
        $order = UserWithdrawalModel::alias("a")->where([['a.order_sn','=',$post_data['appOrderNo']],['a.pay_type','=',$this->channel]])
            ->field("a.arrival_price,"."(select bank_number from dq_user_bank where id=a.bank_id limit 1) as bank_number")
            ->find();
        if(!$order){
            $code = '2';$msg = 'order does not exist';
        }
        $data = [
            'code'=>$code,
            'msg'=>$msg,
            'orderNo'=>$code == 0000 ? $post_data['appOrderNo']:'',
            'accNo'=>$code == 0000 ? $order['bank_number']:'',
            'amt'=>$code == 0000 ? $order['arrival_price']:'',
        ];
        return json_encode($data);
    }

}