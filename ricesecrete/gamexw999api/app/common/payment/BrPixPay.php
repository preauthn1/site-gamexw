<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class BrPixPay
{
    private $merchantId = '';  //商户ID
    private $key ='';  //商户的Keyd在商户后台可以获取
    private $domain='';

    public function __construct($pay_set,$domain){
        $this->merchantId = $pay_set['merchantId'];
        $this->key = $pay_set['key'];
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchantId'] = $this->merchantId;
        $params['tradeNo'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['payAmount'] = $pay_arr['apply_price']; // 订单金额:
        $params['payWay'] = $pay_arr['payWay'];
        $params['currency'] = 'BRL';
        $params['resultType'] = 'json';
        $params['sign'] = $this->sign($params);
        Log::write(['Submit_create',"BrPixPay",$params], "payment");
        $url='https://api.brazilpix.com/pay/gateway';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"BrPixPay",$res], "payment");
        $res=json_decode($res,true);
        if ($res['code'] != 1) {
            throw new \Exception('支付信息错误', 0);
        }
        if (!isset($res['url'])) {
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']= $res['url'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['merchantId'] = $this->merchantId;
        $params['tradeNo'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['payAmount'] = $pay_arr['amount']; // 订单金额:
        $params['currency'] = 'BRL';
        $params['payWay'] = $pay_arr['pay_way'];//支付类型 1bank 2pix
        $params['sign'] = $this->sign($params);
        if ($params['payWay']=='pix'){
            $params['pixType'] = $pay_arr['pixType']?strtolower($pay_arr['pixType']):"";
            if ('phone'==$params['pixType']){
                $pixKey = isset($pay_arr['pixKey'])?$pay_arr['pixKey']:"";
                if (str_starts_with($pixKey, "+")) {
                    $pixKey = substr($pixKey,1);
                }
                if (strlen($pixKey)>11 && str_starts_with($pixKey,"55")){
                    $pixKey = substr($pixKey,2);
                }
                $params['pixKey'] = urlencode("+55".$pixKey);
            }else{
                $params['pixKey'] = $pay_arr['pixKey'];
            }
            $params['pixCity'] = "sao.paulo";
        } else {
            $params['bankAG'] = $pay_arr['bank_code'];//银行代码
            $params['bankCC'] = $pay_arr['account_name'];//收款方账号
            $params['bankName'] = $pay_arr['bank_number'];//收款方账号
        }
        $params['payeeName']=$pay_arr['payeeName'];
        $params['payeeCPF']=$pay_arr['payeeCPF'];
        Log::write(['Submit_withdrawal',"BrPixPay",$params], "withdrawal");
        $url = 'https://api.brazilpix.com/remit/gateway';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal',"BrPixPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if ($res['code'] != 1) {
            throw new \Exception($res['msg']??'代付信息错误', 0);
        }
        return "";//第三方未返回订单号  用自己的
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['sign'];
        unset($notify_data['sign']);
        if ($sign!=$this->sign($notify_data)){
            return 'error';
        }
        if (!isset($notify_data["oStatus"]) || $notify_data["oStatus"]!=2) {
            return 'error';
        }
        if(!isset($notify_data['tradeNo']) || empty($notify_data['tradeNo'])) {
            return 'error';
        }
        
        $rsps['order_sn'] = $notify_data['tradeNo'];
        $rsps['trade_sn'] = $notify_data['payId'];
        $rsps['result'] = "success";
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $sign = $notify_data['sign'];
        unset($notify_data['sign']);
        $rsps = [];
        $rsps['status'] = 1;
        if ($sign!=$this->sign($notify_data)){
            $rsps['status'] = 0;
        }else{
            $oStatus = $notify_data["oStatus"];
            if ($oStatus==3){
                $rsps['status'] = 1;
            }else if($oStatus==4||$oStatus==5){
                $rsps['status'] = 2;
            }else {
                $rsps['status'] = 0;
            }
        }
        $tradeNo = isset($notify_data["tradeNo"]) ? $notify_data["tradeNo"]: "";        
        $rsps['order_sn'] = $tradeNo;
        $rsps['othr_number'] = $tradeNo;
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }

    // 签名验证
    private function sign($data) {
        //去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $str="";
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('msg' != $key && 'sign' != $key && '' != $value) {
                $str .= $value;
            }
        }
        $str = $str .$this->key;
        return md5($str);
    }
}