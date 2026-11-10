<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class Brspay
{

    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }
    //获取支付信息
    public function create($pay_arr)
    {
        $params['pay_memberid'] =  $this->pay_set['memberid'];//商户号
        $params['pay_orderid'] = $pay_arr['order_sn']; // 商户订单号
        $params['pay_applydate'] = date('Y-m-d H:i:s',time());//提交时间
        $params['pay_notifyurl'] = $pay_arr['notify_url'];//异步通知
        $params['pay_amount'] = $pay_arr['apply_price']; // 订单金额
        $params['pay_callbackurl'] = $pay_arr['call_domain']; // 重定向
        $params['pay_username'] = $pay_arr['username'];// 姓名
        $params['pay_useremail'] = $pay_arr['username'] . "@0s.com";// 邮箱
        $params['pay_userphone'] = isset($pay_arr['phone']) ? $pay_arr['phone'] : '12345678'; // 手机号
        $params['pay_type'] = 'CPF';//支付类型
        $params['pay_value'] = 'xx';//支付类型值
        $params['pay_md5sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "Brspay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "Brspay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != '1') {
            throw new \Exception($res['msg'] ?? '支付信息错误', 0);
        }
        if (!isset($res['data']['pay_url'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['pay_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['memberid'] = $this->pay_set['memberid'];//商户号
        $params['out_trade_no'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = $pay_arr['amount']; // 订单金额
        $params['notifyurl'] = $pay_arr['notify_url'];//异步通知
        $params['pix_type'] = $pay_arr['bankName'];// 银行名称
        $params['pix_key'] = strtoupper($pay_arr['bankName']) == 'PHONE' ? '+55'.$pay_arr['bank_number']: $pay_arr['bank_number']; // PIX账号
        $params['pay_md5sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "Brspay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "Brspay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != '1') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['data']['transaction_id'] ??'';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"],$data["pay_username"],$data["pay_useremail"],$data["pay_userphone"],$data["pay_type"],$data["pay_value"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= "&key=".$signKey;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        unset($data['sign']);
        if ($data['returncode'] != '1') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderid'];
        $rsps['trade_sn'] = $data['transaction_id'];
        $rsps['result'] = "OK";
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['refCode'] != '1') {
            $rsps['status'] = 2;
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['othr_number'] = $data['transaction_id'];
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }
}
