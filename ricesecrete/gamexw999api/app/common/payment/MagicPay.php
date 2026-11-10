<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class MagicPay
{

    private $merchantId="";
    private $sign_key="";
    private $code="";
    private $url="";
    private $with_url="";

    private $cardtype="";

    private $balance_url="";

    private $get_with_balance="";
    public function __construct($pay_set,$domain){
        $this->merchantId = $pay_set['merchantId'];
        $this->sign_key = $pay_set['sign_key'];
        $this->code = $pay_set['code'];
        $this->cardtype = $pay_set['cardtype']; //卡类型 1银行卡  2UPI
        $this->url = $pay_set['url'];
        $this->with_url = $pay_set['with_url'];
        $this->balance_url = $pay_set['balance_url'];
        $this->get_with_balance = $pay_set['get_with_balance'];
    }

    //获取支付信息
    public function create($pay_arr){
        $params['mchid'] = $this->merchantId;//商户号
        $params['out_trade_no'] = $pay_arr['order_sn'];//订单号
        $params['money'] = $pay_arr['apply_price'];//订单金额
        $params['notifyurl'] = $pay_arr['notify_url'];;// 异步通知地址
        $params['code'] = $this->code;//通道编码
        $params['applydate'] = date('Y-m-d H:i:s');



        $params['sign'] = $this->generateSign($params, $this->sign_key);
        $params['client_ip'] = $pay_arr['pay_ip'];   //用户ip
        $params['returnurl'] = $pay_arr['call_domain'];   //前端重定向地址
        Log::write(['Submit_create',"MagicPay",$params], "payment");
        $header = array(
            'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
        );
        $res = PayRequest::curlS($this->url,$params, $header);
        Log::write(['Return_create',"MagicPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['status'] != 'success') {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['pay_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['mchid'] = $this->merchantId;//商户号
        $params['out_trade_no'] = $pay_arr['order_sn'];//订单号
        $params['money'] = $pay_arr['amount'];//订单金额
        $params['notifyurl'] = $pay_arr['notify_url'];;// 异步通知地址
        $params['bankname'] = $pay_arr['bankName'];   //开户行名称
        $params['subbranch'] = $pay_arr['ifsc'] ?? 'ifsc';   //支行名称
        $params['accountname'] = $pay_arr['account_name'];   //开户名
        $params['cardnumber'] = $pay_arr['bank_number'];   //银行卡号
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        $params['cardtype'] = $this->cardtype;   //卡类型 1银行卡 2UPI
        Log::write(['Submit_withdrawal',"MagicPay",$params], "payment");
        $header = array(
            'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
        );
        $res = PayRequest::curlS($this->with_url, $params, $header);
        Log::write(['Return_withdrawal',"MagicPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['status'] != 'success') {
            throw new \Exception('支付信息错误', 0);
        }
        return $res['out_trade_no'] ?? '';
    }

    private function generateSign($data, $signKey){
        $str = '';
        $data = array_filter($data);
        ksort($data);
        foreach ($data as $key => $value) {
            $value = trim($value);
            $str .= $key . '=' . $value . '&';
        }
        $str .= "key=".$signKey;
        Log::write(['sign_str',"MagicPay",$str], "payment");
        return strtoupper(md5($str));
    }


    //异步回调支付fmpay
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        if($data['refCode'] != 2){
            return "faile";
        }
        $sign_data = [
            'mchid'=>$data['mchid'],
            'out_trade_no'=>$data['out_trade_no'],
            'amount'=>$data['amount'],
            'transaction_id'=>$data['transaction_id'],
            'refCode'=>$data['refCode'],
            'refMsg'=>$data['refMsg'],
        ];
        $sign_str =$this->generateSign($sign_data, $this->sign_key);
        if($sign_str != $notify_sign){
            return "sign error";
        }

        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }


    //异步回调支付fmpay
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['sign']??'';
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['refCode'] != 3){
            $rsps['status'] = 2;
        }
        $sign_data = [
            'mchid'=>$notify_data['mchid'],
            'out_trade_no'=>$notify_data['out_trade_no'],
            'amount'=>$notify_data['amount'],
            'transaction_id'=>$notify_data['transaction_id'],
            'refCode'=>$notify_data['refCode'],
            'refMsg'=>$notify_data['refMsg'],
        ];
        $sign_str =$this->generateSign($sign_data, $this->sign_key);
        if($sign_str != $notify_sign){
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $notify_data['out_trade_no'];
        $rsps['othr_number']='';
        $rsps['msg'] = "";
        $rsps['result'] = "success";
        return $rsps;
    }


    //获取三方代收余额
    public function get_balance($type){
        $params['mchid'] = $this->merchantId;//商户号
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['get_balance_create',"MagicPay",$params], "payment");
        $header = array(
            'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
        );
        $res = PayRequest::curlS($this->balance_url, $params, $header);
        Log::write(['get_balance_return',"MagicPay",$res], "payment");
        $res = json_decode($res,true);

        if (empty($res) || $res['status'] != 'success') {
            if($type == 1){
                //抛出错误
                throw new \Exception('支付信息错误', 0);
            }else{
                return  'fail';
            }
        }
        return $res['balance'] ?? '';
    }

    //获取三方代付余额
    public function get_with_balance($type){
        $params['mchid'] = $this->merchantId;//商户号
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['get_with_balance_create',"MagicPay",$params], "payment");
        $header = array(
            'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
        );
        $res = PayRequest::curlS($this->get_with_balance, $params, $header);
        Log::write(['get_with_balance_return',"MagicPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['status'] != 'success') {
            if($type == 1){
                //抛出错误
                throw new \Exception('支付信息错误', 0);
            }else{
                return  'fail';
            }
        }
        return $res['balance'] ?? '';
    }
}