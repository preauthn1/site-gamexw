<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class CtbPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['desc'] = $this->pay_set['desc'];//请求唯一标识
        $params['charset'] = 'utf-8';//字符编码
        $params['content'] = json_encode([ //参数 json格式参数(包含付款人手机号，付款金额,商户唯一标识码)
            'phoneNumber'=>trim($pay_arr['qh'],"+").$pay_arr['phone'],//用户手机号,
            'amount'=>intval($pay_arr['apply_price']),
            'userPhoneNumber'=>$this->pay_set['userPhoneNumber'],
        ]);
        $params['sign'] = $this->RSA2($params);
        $params['sign_type'] = 'RSA2';//加密类型
        Log::write(['Submit_create', "CtbPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['url'],$req,$header);
        Log::write(['Return_create', "CtbPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['code']) || $res['code'] != '200') {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['url'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['url'];
        $pay_info['othr_number'] = $res['data']['receiptNo'] ??'';
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['desc'] = $this->pay_set['desc'];//请求唯一标识
        $params['charset'] = 'utf-8';//字符编码
        $params['content'] = json_encode([
            'tranceNo'=>$pay_arr['order_sn'], // 商户订单号
            'phoneNumber'=>trim($pay_arr['qh'],"+").$pay_arr['phone'], //付款手机号
            'amount'=>intval($pay_arr['amount']),   //付款金额
            'name'=>$pay_arr['account_name'], //姓名
            'userPhoneNumber'=>$this->pay_set['userPhoneNumber'], //商户唯一识别
        ]);
        $params['sign'] = $this->RSA2($params);
        $params['sign_type'] = 'RSA2';//加密类型
        Log::write(['Submit_withdrawal', "CtbPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Accept: application/json',
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $req,$header);
        Log::write(['Return_withdrawal', "CtbPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if(!isset($res['code'])){
            throw new \Exception("渠道返回:" . $res['error'], 0);
        }
        if (empty($res) ||  $res['code'] != '200') {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return '';
    }

    //RSA加密
    protected function RSA2($data)
    {
        //将请求的参数进行排序
        ksort($data);
        reset($data);
        $signStr = '';
        foreach ($data as $key => $value) {
            if ($key == 'sign' || $value == '') continue;
            $signStr .= $key . '=' . $value . '&';
        }
        $signStr = substr($signStr, 0, -1);
        $res = "REDACTED";
        $piKey = openssl_pkey_get_private($res);
        if ($piKey) {
            $res = openssl_get_privatekey($res);
            openssl_sign($signStr, $sign, $res, 'SHA256');
            $sign = base64_encode($sign);
            openssl_free_key($piKey);
            return $sign;
        }
    }

    //异步回调支付
    public function notify($data)
    {
        if(empty($data)){
            return "faile";
        }

        $rsps['order_sn'] = '';
        $rsps['trade_sn'] = $data['reference'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['paidIn'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if ($data['status'] != 'success') {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['tranceNo'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}