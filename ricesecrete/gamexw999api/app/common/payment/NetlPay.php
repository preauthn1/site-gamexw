<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class NetlPay
{
    private $Mid = '';
    private $secretKey = '';
    private $payUrl = '';
    private $currency = '';
    private $requestfor = '';
    private $domain="";

    public function __construct($pay_set,$domain){
        $this->Mid = $pay_set['Mid'];
        $this->secretKey = $pay_set['key'];
        $this->payUrl = $pay_set['url'];
        $this->currency = $pay_set['currency'];
        $this->requestfor=$pay_set['requestfor']??"";
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['midcode'] = $this->Mid; // 商户ID
        $params['timestamp'] = time()*1000;
        $params['countrycode'] = 'IN';
        $params['userid'] = $pay_arr['user_id'];
        $params['currency'] = $this->currency;
        $params['amount'] = $pay_arr['apply_price']*1.00; // 订单金额:
        $params['mtxid'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['returnurl'] = $pay_arr['call_domain'];
        $params['callbackurl'] = $this->domain.'/notify/NetlPay';// 异步通知地址
        $params['requesttype'] = 'deposit';
        $params['requestfor'] = $this->requestfor;
        $params['encrypthash'] = md5($params['timestamp'].number_format($pay_arr['apply_price'],2,'.','').$this->Mid.$this->secretKey);
        Log::write(['Submit_create',"NetlPay",$params], "payment");
        $url=$this->payUrl.'/deposit';
        $res = PayRequest::curlS($url,json_encode($params),array('Content-Type: application/json'));
        Log::write(['Return_create',"NetlPay",$res], "payment");
        $res=json_decode($res,true);
        if (!isset($res['status']) || $res['status'] == 'DECLINED') {
            throw new \Exception($res['message']??"支付信息错误", 0);
        }
        $pay_info['pay_url']= $res['paymenturl'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['midcode'] = $this->Mid;
        $params['userid'] = $pay_arr['user_id'];
        $params['timestamp'] = time()*1000;
        $params['currency'] = $this->currency;
        $params['amount'] = $pay_arr['amount']*1.00; // 订单金额:
        $params['notifyurl'] = $this->domain.'/withdrawal/notify/NetlPay';// 异步通知地址
        $params['requesttype'] = 'withdrawal';
        $params['requestfor'] = $this->requestfor;
        $params['mtxid'] = $pay_arr['order_sn'];
        $params['upiaccount'] = $pay_arr['bank_number'];
        $params['encrypthash'] = md5($params['timestamp'].number_format($pay_arr['amount'],2,'.','').$this->Mid.$this->secretKey);
        Log::write(['Submit_withdrawal',"NetlPay",$params], "withdrawal");
        $url = $this->payUrl.'/withdrawal';
        $res = PayRequest::curlS($url, json_encode($params), array('Content-Type: application/json'));
        Log::write(['Return_withdrawal',"NetlPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if (!isset($res['status']) || $res['status'] == 'DECLINED') {
            throw new \Exception($res['message']??'代付信息错误', 0);
        }
        return $res['payoutid'];
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $txid = $notify_data['txid']??"";
        $hash = $notify_data['hash']??"";
        if($hash!=md5($this->Mid.$txid.$this->secretKey)){
            return 'error';
        }
        $status = $notify_data['status']??"";
        if (empty($status) || $status!='APPROVED') {
            return 'error';
        }
        
        $rsps['order_sn'] = $notify_data['mtxid'];
        $rsps['trade_sn'] = $notify_data['txid'];
        $rsps['result'] = "success";
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $rsps = [];
        $rsps['status'] = 1;
        $amount = $notify_data['amount']??0;
        $payoutid = $notify_data['payoutid']??"";
        $hash = $notify_data['hash']??"";
        if($hash!=md5($this->Mid.$payoutid.$amount.$this->secretKey)){
            $rsps['status'] = 0;
        }else{
            //PENDING， 是订单正在处理中 APPROVED， 是订单已经确认，可以上分  DECLINED， 是订单失败
            $status = $notify_data['status']??"";
            if (empty($status)) {
                $rsps['status'] = 0;
            }else if($status=='DECLINED'){
                $rsps['status'] = 2;
            }else if ($status=='APPROVED'){
                $rsps['status'] = 1;
            }else{
                $rsps['status'] = 0;
            }
        }
        
        $rsps['order_sn'] = $notify_data['mtxid'];
        $rsps['othr_number'] = $notify_data['payoutid'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}