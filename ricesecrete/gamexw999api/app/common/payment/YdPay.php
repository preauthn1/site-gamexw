<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class YdPay
{
    private $domain="";
    private $pay_memberid="";
    private $sign_key="";
    private $pay_bankcode="";
    public function __construct($pay_set,$domain){
        $this->pay_memberid=$pay_set['seller'];
        $this->sign_key=$pay_set['pay_key'];
        $this->pay_bankcode=$pay_set['pay_bankcode'] ??'904';
        $this->domain=$domain;
    }

    /** 创建支付
     * pay_arr
     */
    public function create($pay_arr){
        $request_arr = [
            "pay_memberid" =>$this->pay_memberid,
            "pay_orderid"  => $pay_arr['order_sn'],
            "pay_amount"   => $pay_arr['apply_price'],
            "pay_applydate" => date("Y-m-d H:i:s"),
            "pay_bankcode"  => $this->pay_bankcode,
            "pay_notifyurl" => $this->domain."/notify/YdPay",//异步回调
            "pay_callbackurl" =>$pay_arr['call_domain'],//同步回调
        ];
        ksort($request_arr);
        $md5str = "";
        foreach ($request_arr as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $sign_key = $this->sign_key;
        $gateway="https://ydpay789.com/Pay_Index.html";
        $sign = strtoupper(md5($md5str . "key=" . $sign_key));
        $request_arr['pay_md5sign'] = $sign;
        $request_arr['email'] = $pay_arr['user_id']."@test.in";
        $request_arr['customer_id'] = $pay_arr['user_id'];
        $request_arr['customer_name'] =$pay_arr['username'];
        $request_arr['customer_phone'] = $pay_arr['phone'];

        $sHtml = "<form id='paysubmit' name='paysubmit' action='" . $gateway . "' method='post'>";
        foreach ($request_arr as $key => $val) {
            $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
        }
        $sHtml .= "</form>";
        $sHtml .= "<script>document.forms['paysubmit'].submit();</script>";

        $pay_info['info']=$request_arr;
        $pay_info['form']=$sHtml;
        $pay_info['gateway']=$gateway;
        return $pay_info;
    }

    /** 转账信息
     * pay_arr
     */
    public function create_withdrawal($pay_arr){
        $request_data = [
            'mchid'=>$this->pay_memberid,//平台分配商户号
            'out_trade_no'=>$pay_arr['order_sn'],//	商户订单号
            'money' =>number_format($pay_arr['amount'],2,'.',''),//订单金额
            'ifsc'=>$pay_arr['ifsc'],//
            'bank_num'=>$pay_arr['bank_number'],//银行卡号
            'account_name' =>$pay_arr['account_name'],//开卡人姓名
            'customer_email'=>$pay_arr['user_id']."@test.in",//	用户邮箱
            'customer_mobile'=>empty($data['phone'])?"1234567890":$data['phone'],//用户手机
            'notify_url' =>$this->domain."/withdrawal/notify/YdPay",//异步回调
            'bank_name'=>$pay_arr['bankName'],//银行名称
            'currency'=>"INR"//货币类型
        ];
        ksort($request_data);
        $sign_key = $this->sign_key;
        $md5str = "";
        foreach ($request_data as $key => $val) {
            $md5str = $md5str . $key . "=" . $val . "&";
        }
        $request_data['pay_md5sign'] = strtoupper(md5($md5str . "key=".$sign_key ));
        Log::write(['Submit_withdrawal',"YdPay",$request_data], "withdrawal");
        $req_url = "https://ydpay789.com/Payment_Dfpay_add.html";
        $res = PayRequest::curlS($req_url, $request_data,["Content-type: application/x-www-form-urlencoded"]);
        Log::write(['Return_withdrawal',"YdPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if (isset($res['status']) && $res['status'] == "success") {
            return $res['transaction_id'];
        }else{
            throw new \Exception("代付信息错误", 0);
        }
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data['memberid'] = isset($post_data['memberid'])?$post_data['memberid']:'';
        $notify_data['orderid'] = isset($post_data['orderid'])?$post_data['orderid']:'';
        $notify_data['transaction_id'] = isset($post_data['transaction_id'])?$post_data['transaction_id']:'';
        $notify_data['amount'] = isset($post_data['amount'])?$post_data['amount']:'';
        $notify_data['datetime'] = isset($post_data['datetime'])?$post_data['datetime']:'';
        $notify_data['returncode'] = isset($post_data['returncode'])?$post_data['returncode']:'';
        $notify_sign = isset($post_data['sign'])?$post_data['sign']:'';
        //returncode==00 是交易成功
        if($notify_data['returncode'] != "00"){
            return "faile";
        }
        $sign_str ="";
        ksort($notify_data);
        foreach ($notify_data as $k=>$v){
            if(!empty($v)){
                $sign_str.=$k.'='.$v.'&';
            }
        }
        $sign_key = $this->sign_key;
        $sign_str .= 'key='.$sign_key;
        if(strtolower(md5($sign_str)) != strtolower($notify_sign)){
            return "sign error";
        }

        $rsps['order_sn'] = $notify_data['orderid'];
        $rsps['trade_sn'] = $notify_data['transaction_id'];
        $rsps['result'] = "ok";
        return $rsps;
    }

    //异步回调提现
    public function notify_withdrawal($notify_res){
        $notify_data['memberid'] = isset($notify_res['memberid'])?$notify_res['memberid']:'';
        $notify_data['orderid'] = isset($notify_res['orderid'])?$notify_res['orderid']:'';
        $notify_data['transaction_id'] = isset($notify_res['transaction_id'])?$notify_res['transaction_id']:'';
        $notify_data['amount'] = isset($notify_res['amount'])?$notify_res['amount']:'';
        $notify_data['datetime'] = isset($notify_res['datetime'])?$notify_res['datetime']:'';
        $notify_data['returncode'] = isset($notify_res['returncode'])?$notify_res['returncode']:'';
        $notify_data['status'] = isset($notify_res['status'])?$notify_res['status']:'';
        $notify_sign = isset($notify_res['sign'])?$notify_res['sign']:'';
        //returncode==00 是交易成功
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['returncode'] != "00"){
            $rsps['status'] = 2;
        }
        if ($notify_data['status']!='success') {
            $rsps['status'] = 2;
        }

        ksort($notify_data);
        $md5str = "";
        $sign_key = $this->sign_key;
        foreach ($notify_data as $key => $val) {
            if (!empty($val)) {
                $md5str .= $key . "=" . $val . "&";
            }
        }
        $sign_str =  strtoupper(md5($md5str . "key=" .$sign_key));
        if($notify_sign!=$sign_str){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $notify_data['orderid'];
        $rsps['othr_number'] = $notify_data['transaction_id'];
        $rsps['msg'] = '';
        $rsps['result'] = "ok";
        return $rsps;
    }
}