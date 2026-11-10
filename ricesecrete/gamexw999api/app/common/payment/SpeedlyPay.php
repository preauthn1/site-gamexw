<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class SpeedlyPay
{

    private $merchantId="";
    private $sign_key="";
    private $payType="";
    private $gateway="";
    private $accountType="";
    private $domain="";
    public function __construct($pay_set,$domain){
        $this->merchantId = $pay_set['merchantId'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->payType = $pay_set['payType'];
        $this->accountType = $pay_set['accountType']??1;
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchantId'] = $this->merchantId;//商户号
        $params['payType'] = $this->payType;//⽀付⽅式
        $params['orderId'] = $pay_arr['order_sn'];//订单号
        $params['amount'] = $pay_arr['apply_price'];//订单金额
        $params['redirectURL'] = $pay_arr['call_domain'];   //前端重定向地址
        $params['notifyUrl'] = $this->domain.'/notify/SpeedlyPay';// 异步通知地址
        $sign_data = [
            'payType'=>$params['payType'],
            'merchantId'=>$params['merchantId'],
            'amount'=>$params['amount'],
            'orderId'=>$params['orderId'],
            'notifyUrl'=>$params['notifyUrl'],
        ];
        $params['sign'] = $this->generateSign($sign_data, $this->sign_key);
        Log::write(['Submit_create',"SpeedlyPay",$params], "payment");
        $url = $this->gateway.'/pay/recharge/order';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"SpeedlyPay",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['status'] != 0) {
            throw new \Exception('支付信息错误', 0);
        }

        $pay_info['pay_url']=$res['data']['payUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['merchantId'] = $this->merchantId;//商户号
        $params['orderId'] = $pay_arr['order_sn'];//订单号
        $params['amount'] = $pay_arr['amount'];//订单金额
        $params['name'] = $pay_arr['account_name'];   //收款人
        $params['accountNumber'] = $pay_arr['bank_number'];   //收款账号
        $params['accountType'] = $this->accountType;   //账号类型
        if ($this->accountType== 4){
            if (strtoupper($pay_arr['bankName']) == 'PHONE'){
                $pixKey = $pay_arr['bank_number'];
                if (str_starts_with($pixKey, "+")) {
                    $pixKey = substr($pixKey,1);
                }
                if (strlen($pixKey)>11 && str_starts_with($pixKey,"55")){
                    $pixKey = substr($pixKey,2);
                }
                $pay_arr['bank_number'] = "55".$pixKey;
                $params['accountNumber'] = $pay_arr['bank_number'];
            }
            $params['idCard'] = $pay_arr['bank_number'];
            $params['bankName'] = "";
            $params['bankNumber'] = "";
        }else{
            $params['idCard'] = $pay_arr['ifsc'];//cpf编码
            $params['bankName'] = $pay_arr['bankName']; // 银行名称
            $params['bankNumber'] = $pay_arr['bank_number'];//银行编码
        }
        $params['notifyUrl'] =  $this->domain.'/withdrawal/notify/SpeedlyPay';// 异步通知地址
        $sign_data = [
            'idCard'=>$params['idCard'],
            'merchantId'=>$params['merchantId'],
            'bankNumber'=>$params['bankNumber'],
            'amount'=>$params['amount'],
            'orderId'=>$params['orderId'],
            'accountNumber'=>$params['accountNumber'],
        ];
        $params['sign'] = $this->generateSign($sign_data, $this->sign_key);
        Log::write(['Submit_withdrawal',"SpeedlyPay",$params], "withdrawal");
        $url = $this->gateway.'/api/withdrawal/order/add';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"SpeedlyPay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['status'] !== 0) {
            throw new \Exception("渠道返回:".$res['message'], 0);
        }

        return '';
    }

    private function generateSign($data, $signKey){
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            $str .= $key . '=' . $value . '&';
        }
        $str .= "key=".$signKey;
        return md5($str);
    }


    //异步回调支付fmpay
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        if($data['orderStatus'] != 1){
            return "faile";
        }
        $sign_data = [
            'merchantId'=>$data['merchantId'],
            'amount'=>$data['amount'],
            'orderId'=>$data['orderId'],
            'orderStatus'=>$data['orderStatus'],
        ];
        $sign_str =$this->generateSign($sign_data, $this->sign_key);
        if($sign_str != $notify_sign){
            return "sign error";
        }

        $rsps['order_sn'] = $data['orderId'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }


    //异步回调支付fmpay
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $notify_sign = $notify_data['data']['sign']??'';
        $rsps = [];
        $rsps['status'] = 1;
        if($notify_data['status'] != 0){
            $rsps['status'] = 2;
        }
        if($notify_data['data']['transferStatus'] != 2){
            $rsps['status'] = 2;
        }
        $sign_data = [
            'amount'=>$notify_data['data']['amount'],
            'orderId'=>$notify_data['data']['orderId'],
            'transferStatus'=>$notify_data['data']['transferStatus']
        ];
        $sign_str =$this->generateSign($sign_data, $this->sign_key);
        if($sign_str != $notify_sign){
            $rsps['status'] = 2;
        }
        $rsps['order_sn']=$notify_data['data']['orderId'];
        $rsps['othr_number']='';
        $rsps['msg'] = "";
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}