<?php
namespace app\common\payment;
use app\common\lib\Util;
use app\common\payment\PayRequest;
use think\facade\Log;

class SpeedlyPay1
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $timeStamp = $this->getMillisecond();
        $merchant_no = $this->pay_set['mch_id'];
        $data['country'] = $this->pay_set['country']; //国家代码
        $data['currency'] = $this->pay_set['currency']; //国家代码
        $data['payment_method_id'] = $this->pay_set['payment_method_id']; //支付方式
        $data['payment_method_flow'] = $this->pay_set['payment_method_flow']; //支付流程
        $data['order_id'] = $pay_arr['order_sn']; //订单号
        $data['amount'] =  sprintf("%.2f",$pay_arr['apply_price']); // 订单金额 整数带小数点
        $data['notification_url'] = $pay_arr['notify_url']; // 异步通知地址
        $data['success_redirect_url'] = $pay_arr['call_domain']; // 重定向
        $data['timestamp'] = $timeStamp; //时间戳
        $data['signature'] = $this->generateSign($data, $this->pay_set['pay_key']);

        $payer['name'] = $pay_arr['username']; //付款方名字
        $data['payer'] = $payer;

        $params['merchant_no'] = $merchant_no;
        $params['data'] = $data;


        Log::write(['Submit_create', "SpeedlyPay1", $params], "payment");
        $d = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8',
            'ApiVersion: 1.0',
            'AppId: '. $this->pay_set['appId'],
            'Noncestr: '.Util::random(8),//随机字符串
            'Timestamp: '.$timeStamp//随机字符串,
        );
        $url = $this->pay_set['pay_url'];
        $res = PayRequest::curlS($url, $d, $header);
        Log::write(['Return_create', "SpeedlyPay1", $res], "payment");
        $res = json_decode($res, true);

        if (empty($res) ||  $res['state'] != 'ok') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['redirect_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {

        $timeStamp = $this->getMillisecond();
        $merchant_no = $this->pay_set['mch_id'];
        $data['country'] = $this->pay_set['country']; //国家代码
        $data['currency'] = $this->pay_set['currency']; //国家代码
        $data['order_id'] = $pay_arr['order_sn']; //订单号
        $data['amount'] =  number_format(intval($pay_arr['amount']), 2, '.', ''); // 订单金额 整数带小数点
        $data['notification_url'] = $pay_arr['notify_url']; // 异步通知地址
        $data['timestamp'] = $timeStamp; //时间戳
        $data['signature'] = $this->generateSign($data, $this->pay_set['with_key']);

        $payee['name'] = $pay_arr['username']; //收款方名字
        $payee['account'] = $pay_arr['bank_number']; //收款方账号
        $payee['account_type'] = 'BANK'; //账户类型
        $payee['bank_code'] = strtoupper(substr($pay_arr['ifsc']??$pay_arr['bank_code'], 0, 4)); //银行编码
        $payee['bank_branch'] = $pay_arr['ifsc']??$pay_arr['bank_code']; //IFSC Code

        $data['payee'] = $payee;
        $params['merchant_no'] = $merchant_no;
        $params['data'] = $data;

        Log::write(['withdrawal_create', "SpeedlyPay1", $params], "payment");
        $d = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8',
            'ApiVersion: 1.0',
            'AppId: '. $this->pay_set['appId'],
            'Noncestr: '.Util::random(8),//随机字符串
            'Timestamp: '.$timeStamp//随机字符串,
        );
        $url = $this->pay_set['with_pay'];

        $res = PayRequest::curlS($url, $d, $header);
        Log::write(['Return_withdrawal', "SpeedlyPay1", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['state'] != 'ok') {
            throw new \Exception("渠道返回:" . $res['errorMsg'], 0);
        }
        return $res['order_id'] ??'';
    }

    private function generateSign($data, $signKey)
    {
        $data = array_filter($data);
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if($value != ''){
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= 'key='.$signKey;
        Log::write(['SignStr', "SpeedlyPay1", $str], "payment");
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $data_decode = $data;
        $sign = $data_decode['signature'] ?? '';
        if ($data_decode['status_code'] != '100') {
            return "faile";
        }
        unset($data_decode['signature']);
        $sign_str = $this->generateSign($data_decode, $this->pay_set['pay_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data_decode['order_id'];
        $rsps['trade_sn'] = $data_decode['payment_id'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data_decode['amount'] ?: 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        $data_decode = $data;
        $sign = $data_decode['signature'] ?? '';
        if ($data_decode['status_code'] != '100') {
            $rsps['status'] = 2;
        }
        unset($data_decode['signature']);
        $sign_str = $this->generateSign($data_decode, $this->pay_set['with_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data_decode['order_id'];
        $rsps['othr_number'] = $data_decode['settlement_id'];
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    function getMillisecond(){
        $sysTimeZone= date_default_timezone_get();//先取出系统所在服务器的时区
        date_default_timezone_set('UTC');//设置时区为UTC时区
        list($s1,$s2)=explode(' ',microtime());
        $timestamp = (float)sprintf('%.0f',(floatval($s1)+floatval($s2))*1000);
         date_default_timezone_set( $sysTimeZone ); //设置时区为服务器时区
         return $timestamp;
        }
}
