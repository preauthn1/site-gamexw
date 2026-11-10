<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class HmPay
{
    private $merchant_id = "";
    private $sign_key = "";
    private $gateway = '';
    private $pay_bankcode = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->pay_bankcode = $pay_set['pay_bankcode'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['pay_memberid'] = $this->merchant_id;//商户号
        $params['pay_orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['pay_applydate'] = date('Y-m-d H:i:s',time());//提交时间
        $params['pay_bankcode'] = $this->pay_bankcode;//银行编码
        $params['pay_notifyurl'] = $this->domain . '/notify/HmPay';// 异步通知地址
        $params['pay_callbackurl'] = $pay_arr['call_domain']; // 重定向
        $params['pay_amount'] = $pay_arr['apply_price']; // 订单金额
        $params['pay_productname'] = 'this is a order'; // 描述
        $params['pay_md5sign'] = $this->generateSign($params, $this->sign_key);
        Log::write(['Submit_create', "HmPay", $params], "payment");
        $url = $this->gateway . '/Pay_Index.html';
        $sHtml = "<form id='paysubmit' name='paysubmit' action='" . $url . "' method='post'>";
        foreach ($params as $key => $val) {
            $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
        }
        $sHtml .= "</form>";
        $sHtml .= "<script>document.forms['paysubmit'].submit();</script>";

        $pay_info['info']=$params;
        $pay_info['form']=$sHtml;
        $pay_info['gateway']=$url;
        return $pay_info;
    }


    private function generateSign($data, $signKey)
    {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value && $key != 'pay_md5sign' && $key != 'pay_productname' && $key != 'type') {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= 'key='.$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['returncode'] != '00') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->sign_key);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderid'];
        $rsps['trade_sn'] = $data['transaction_id'];
        $rsps['result'] = "OK";
        $rsps['money'] = $data['true_amount'] ??$data['amount'];
        return $rsps;
    }

}
