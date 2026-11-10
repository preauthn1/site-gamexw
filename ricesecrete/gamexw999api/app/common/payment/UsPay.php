<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class UsPay
{
    private $merchant = "";
    private $sign_key = "";
    private $gateway = '';
    private $type = '';
    private $withdrawal_gateway = '';
    private $coin_type = '';
    private $domain = "";
    public function __construct($pay_set,$domain){
        $this->merchant = $pay_set['merchant'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->type = $pay_set['type'];
        $this->coin_type = $pay_set['coin_type'];
        $this->withdrawal_gateway = $pay_set['withdrawal_gateway'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['type'] = $this->type;// 交易类型: ERC20 or TRC20
        $params['merchant_money'] = sprintf("%.2f",$pay_arr['apply_price']); // 订单金额
        $params['order_id'] = $pay_arr['order_sn']; // 商户订单号
        $params['merchant'] = $this->merchant;//商户号
        $params['account'] = $pay_arr['user_id'];//用戶編號
        $params['merchant_amount_cny'] = sprintf("%.2f",$pay_arr['apply_price']);//订单总额
        $params['rt_url'] = $this->domain.'/notify/UsPay';// 异步通知地址
        $sigdata = [
            'type'=>$params['type'],
            'merchant'=>$params['merchant'],
            'merchant_money'=>$params['merchant_money'].$this->sign_key,
            'rt_url'=>$params['rt_url'],
            'account'=>$params['account'],
            'order_id'=>$params['order_id'],
            'merchant_amount_cny'=>$params['merchant_amount_cny'],
        ];
        $params['sign'] = $this->generateSign($sigdata);
        Log::write(['Submit_create',"UsPay",$params], "payment");
        $res = PayRequest::curlS($this->gateway,$params);
        Log::write(['Return_create',"UsPay",$res], "payment");
        $res = json_decode($res,true);
        if(empty($res) || $res['code'] != '100'){
            throw new \Exception('支付信息错误', 0);
        }
        $pay_info['pay_url']=$res['url']."&lan=EN";
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['merchant'] = $this->merchant;//商户号
        $params['order_id'] = $pay_arr['order_sn']; // 商户订单号
        $params['add'] =  $pay_arr['address']; // 出金至地址
        $params['coin_type'] = $this->coin_type;// 交易类型: ERC20 or TRC20
        $params['amount'] = sprintf("%.2f",$pay_arr['amount']); // 申请金额
        $params['rt_url'] = $this->domain.'/withdrawal/notify/UsPay';// 异步通知地址
        $sigdata = [
            'merchant'=>$params['merchant'],
            'order_id'=>$params['order_id'],
            'coin_type'=>$params['coin_type'],
            'add'=>$params['add'],
            'amount'=>$params['amount'],
            'rt_url'=>$params['rt_url'].$this->sign_key,
        ];
        $params['sign'] = $this->generateSign($sigdata);
        Log::write(['Submit_withdrawal',"UsPay",$params], "withdrawal");
        $res=PayRequest::curlS($this->withdrawal_gateway,$params);
        Log::write(['Return_withdrawal',"UsPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if(empty($res) || $res['code'] != '100'){
            throw new \Exception('渠道返回:'.$res['status'], 0);
        }
        return $res['trade_id'];
    }

    private function generateSign($data){
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str = trim($str,'&');
        return md5($str);
    }



    //异步回调支付
    public function notify($data){
        $sign = isset($data['sign'])?$data['sign']:'';
        unset($data['sign']);
        if($data['status'] != 'success'){
            return "faile";
        }

       
        if(isset($data['merchant_amount_cny'])){
            $md5str = $this->sign_key.'account='.$data['account'].'&pay_amount='.$data['pay_amount'].'&order='.$data['order'].'&blocktime='.$data['blocktime'].$this->sign_key.'&merchant='.$data['merchant'].'&type='.$data['type']."&merchant_amount_cny=".$data['merchant_amount_cny'];
        }else{
            $md5str = $this->sign_key.'account='.$data['account'].'&pay_amount='.$data['pay_amount'].'&order='.$data['order'].'&blocktime='.$data['blocktime'].$this->sign_key.'&merchant='.$data['merchant'].'&type='.$data['type'];
        }
        $sign_str = md5($md5str);
        if($sign_str != $sign){
            return "sign error";
        }
        $rsps['order_sn'] = $data['order'];
        $rsps['trade_sn'] = '';
        $rsps['money'] = $data['pay_amount'];
        $rsps['result'] = "success";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data){
        $sign = isset($data['sign'])?$data['sign']:'';
        unset($data['sign']);
        $rsps['status'] = 1;
        if($data['txid'] == 'tradeouterror'){
            $rsps['status'] = 2;
        }
        $sign_str = md5($data['order_id'].$data['trade_id'].$data['txid'].$this->sign_key);
        if($sign_str != $sign){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['order_id'];
        $rsps['othr_number'] = $data['trade_id'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}