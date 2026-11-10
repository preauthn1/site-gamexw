<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class MyfunPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['amount'] = intval($pay_arr['apply_price']); // 订单金额
        $params['phone'] = $pay_arr['phone'];  //手机号
        $params['trans_id'] = $pay_arr['order_sn']; // 商户订单号
        Log::write(['Submit_create', "MyfunPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'token:'.$this->gettoken(),
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "MyfunPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != '200') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['datas'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['datas'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $phone = $pay_arr['bank_number'];
        $phone_num = substr($phone,0,1);
        if($phone_num == 0){
            $phone = substr_replace($phone, '', 0,1);
        }

        $phone_num2 = substr($phone,0,3);
        if($phone_num2 == 256){
            $phone = substr_replace($phone, '', 0,3);
        }

        $params['amount'] = intval($pay_arr['amount']); // 订单金额
        $params['phone'] = $phone;  //银行卡号
        $params['trans_id'] = $pay_arr['order_sn']; // 商户订单号
        Log::write(['Submit_withdrawal', "MyfunPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'token:'.$this->gettoken(),
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$req,$header);
        Log::write(['Return_withdrawal', "MyfunPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != '200') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return '';
    }

    private function gettoken()
    {
        return base64_encode($this->pay_set['api_key'].$this->pay_set['api_secret']);
    }


    //异步回调支付
    public function notify($data)
    {
        if ($data['code'] != '200') {
            return 'code error';
        }

        $rsps['order_sn'] = $data['data']['trans_id'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = ['code'=>200,'message'=>''];
        $rsps['money'] = $data['data']['amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if ($data['code'] != '200') {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['data']['trans_id'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = ['code'=>200,'message'=>''];
        return $rsps;
    }
}