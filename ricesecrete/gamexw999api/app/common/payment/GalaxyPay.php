<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class GalaxyPay
{
    private $merchant_id = "";
    private $paytype = "";
    private $sign_key = "";
    private $gateway = '';
    private $ccy_no = '';
    private $countryCode = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->paytype = $pay_set['paytype'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->ccy_no = $pay_set['ccy_no'];
        $this->countryCode = $pay_set['countryCode'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['version'] = '1.0.0';
        $params['memberid'] = $this->merchant_id;//商户号
        $params['orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['apply_price']*100; // 订单金额
        $params['orderdatetime'] = date('Y-m-d H:i:s',time());//提交时间
        $params['notifyurl'] = $this->domain . '/notify/GalaxyPay';// 异步通知地址
        $params['callbackurl'] = $pay_arr['call_domain']; // 重定向
        $params['paytype'] = $this->paytype;//支付通道分类编码
        $params['signmethod'] = 'md5'; // 加密方式，固定用”md5”
        $params['ccy_no'] = $this->ccy_no; //币种编码
        $params['countryCode'] = $this->countryCode; // 国家编码
        $params['pname'] = $pay_arr['username'];// 付款人姓名
        $params['pemail'] = $pay_arr['username'] . "@0s.com";// 邮箱
        $params['phone'] = $pay_arr['phone'] ?? '15698456131'; // 手机号
        $sign_data = [
            'amount'=>$params['amount'],
            'memberid'=>$params['memberid'],
            'notifyurl'=>$params['notifyurl'],
            'orderdatetime'=>$params['orderdatetime'],
            'orderid'=>$params['orderid'],
            'paytype'=>$params['paytype'],
            'signmethod'=>$params['signmethod'],
            'version'=>$params['version'],
        ];
        $params['sign'] = $this->generateSign($sign_data, $this->sign_key);
        Log::write(['Submit_create', "GalaxyPay", $params], "payment");
        $url = $this->gateway . '/pay';
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_create', "GalaxyPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != 'SUCCESS') {
            throw new \Exception($res['err_msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['order_data'])) {
            throw new \Exception($res['err_msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['order_data'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {

        $params['version'] = 'v1.0.0';
        $params['memberid'] = $this->merchant_id;//商户号
        $params['orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['amount']*100; // 订单金额
        $params['bankaccountname'] = $pay_arr['account_name'];// 收款人姓名
        $params['bankcard'] = $pay_arr['bank_number'];// 银行账号
        $params['phonenumber'] = $pay_arr['phone'] ?? '15698456131'; // 手机号
        $params['bankname'] = $pay_arr['bank_code'];//银行编码
        $params['bankcode'] = 'BANK';
        $params['orderdatetime'] = date('Y-m-d H:i:s',time());//提交时间
        $params['signmethod'] = 'md5'; // 加密方式，固定用”md5”
        $params['remarks'] = 'this is a withdrawal_order'; // 描述
        $params['notifyurl'] = $this->domain . '/withdrawal/notify/GalaxyPay';// 异步通知地址
        $params['sign'] = $this->generateSign($params, $this->sign_key);
        $url = $this->gateway . '/settlement';
        Log::write(['Submit_withdrawal', "GalaxyPay", $params], "withdrawal");
        $res = PayRequest::curlS($url, $params);
        Log::write(['Return_withdrawal', "GalaxyPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != 'SUCCESS') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['order_no'];
    }

    private function generateSign($data, $signKey)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str = trim($str,'&');
        $str .= $signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['status'] != 'success') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderid'];
        $rsps['trade_sn'] = $data['sysorderid'];
        $rsps['result'] = "OK";
        $rsps['money'] = $data['true_amount'] ? $data['true_amount']/100 : $data['amount']/100;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        $rsps['status'] = 1;
        if ($data['status'] != '1' && $data['status'] != '2') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderid'];
        $rsps['othr_number'] = $data['sysorderid'];
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }
}
