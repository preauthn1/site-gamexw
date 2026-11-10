<?php
namespace app\common\payment;
use app\common\lib\Util;
use app\common\payment\PayRequest;
use think\facade\Log;
use app\api\redis\ApiRedis;

class M7Pay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['amount'] = $pay_arr['apply_price'];#金额
        $params['paymentCode'] = $this->pay_set['paymentCode'];#支付产品代码,缅甸支持（KBZPAY，WAVEPAY）
        $params['orderNo'] = $pay_arr['order_sn'];#订单号
        $params['orderTime'] = $this->getCurrentTime();#商户系统发起订单时间，格式为yyyyMMddHHmmss（20101231032055）
        $params['returnUrl'] = $pay_arr['call_domain'];#支付返回地址
        $params['notifyUrl'] = $pay_arr['notify_url'];#回调地址
        $params['memberCode'] = $pay_arr['user_id'];#提交订单的会员账号，可去识别化
        $params = $this->publicParams($params);

        Log::write(['Submit_create', "M7Pay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],$params);
        Log::write(['Return_create', "M7Pay", $res], "payment");
        $res = $this->publicResponse($res);
        if(!isset($res['code'])){
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (empty($res) || $res['code'] != '0000') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['resultUrl'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['resultUrl'];
        $pay_info['othr_number'] = $res['gbpOrderNo'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['amount'] = $pay_arr['amount'];//金额
        $params['bankCode'] = $pay_arr['bank_code'];
        $params['bankAccountNo'] = $pay_arr['bank_number'];
        $params['bankAccountName'] = $pay_arr['username'];
        $params['orderNo'] = $pay_arr['order_sn'];
        $params['orderTime'] = $this->getCurrentTime();#商户系统发起订单时间，格式为yyyyMMddHHmmss
        $params['notifyUrl'] = $pay_arr['notify_url'];
        $params['memberCode'] = $pay_arr['user_id'];
        $params = $this->publicParams($params);

        Log::write(['Submit_withdrawal', "M7Pay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$params);
        Log::write(['Return_withdrawal', "M7Pay", $res], "withdrawal");
        $res = $this->publicResponse($res);
        if (empty($res) ||  $res['code'] != '0000') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['gbpOrderNo'];
    }

    //通用params
    public function publicParams($datas = []){
        $params['version'] = '1.0';
        $params['merchantNo'] = $this->pay_set['merchantNo'];
        $params['request'] = base64_encode(json_encode($datas));
        $params['signMsg'] = $this->generateSign($params);
        return $params;
    }

    private function generateSign($data)
    {
        ksort($data);
        $str = '';
        foreach ($data as $k => $v){
            $str.= $k.':'.$v.'&';
        }
        $str .= 'key:'.$this->pay_set['sign_key'];
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $data = json_decode(base64_decode($data['content'], true), true);
        if (!isset($data['status']) || $data['status'] != 'SUCCESS') {
            return "faile";
        }

        $rsps['order_sn'] = $data['orderNo'];
        $rsps['trade_sn'] = $data['gbpOrderNo'];
        $rsps['result'] = "OK";
        $rsps['money'] = $data['amount'];
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $data = json_decode(base64_decode($data['content'], true), true);
        $rsps['status'] = 1;
        if (!isset($data['status']) || $data['status'] != 'SUCCESS') {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderNo'];
        // $rsps['othr_number'] = $data['gbpOrderNo'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }

    private function publicResponse($res){
        return json_decode(base64_decode(json_decode($res, true)['response']), true);
    }

    //获取当前时间
    private function getCurrentTime(){
        //该三方必需要
        date_default_timezone_set('Asia/Shanghai');
        $time = date('YmdHis');
        //还原时区
        $sys = (new ApiRedis())->systemInfo();
        date_default_timezone_set($sys['time_zone']);
        return $time;
    }
}