<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class WtPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['partner_id'] = $this->pay_set['partner_id'];//商户号
        $params['channel_id'] = $this->pay_set['channel_id'];//渠道ID，传0自动匹配可用渠道
        $params['pay_method'] = 'POST';
        $params['partner_order'] = $pay_arr['order_sn']; // 商户订单号
        $params['user_id'] = $pay_arr['user_id'];//商户会员ID
        $params['total_fee'] = intval($pay_arr['apply_price']*100); // 订单金额
        $params['back_url'] = $pay_arr['call_domain']; // 重定向
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['payextra_param'] = json_encode([
            'phone '=>$pay_arr['phone'],
            'name '=>$pay_arr['username'],
            'bankno '=>'',
            'month '=>'',
            'year '=>'',
            'cvv '=>'',
        ]);
        $params['exter_invoke_ip'] = $pay_arr['pay_ip'];     //用户id地址
        $params['sign_type'] = 'MD5';
        $params['country_shortname'] = $this->pay_set['country_shortname'];//国家标识
        $params['subject'] = 'this is a order'; // 描述
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "WtPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "WtPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '1001') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
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
        $params['partner_id'] = $this->pay_set['partner_id'];//商户号
        $params['channel_id'] = $this->pay_set['withdrawal_channel_id'];//渠道ID，传0自动匹配可用渠道
        $params['pay_method'] = 'POST';
        $params['partner_order'] = $pay_arr['order_sn']; // 商户订单号
        $params['user_id'] = $pay_arr['user_id'];//商户会员ID
        $params['total_fee'] = intval($pay_arr['amount']*100); // 订单金额
        $params['back_url'] = $pay_arr['notify_url'];
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['payextra_param'] = json_encode([
            'bank_name'=>$pay_arr['bankName'],
            'bank_no'=>$pay_arr['bank_number'],
            'bank_username'=>$pay_arr['account_name'],
            'phone'=>$pay_arr['phone'],
        ]);
        $params['exter_invoke_ip'] = $pay_arr['pay_ip'];     //用户id地址
        $params['sign_type'] = 'MD5';
        $params['country_shortname'] = $this->pay_set['country_shortname'];//国家标识
        $params['subject'] = 'this is a withdrawal order'; // 描述
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "WtPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "WtPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != '1001') {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey,$is_notify = 1)
    {
        unset($data["sign"]);
        if(isset($data['subject']))unset($data['subject']);
        if(isset($data['real_money']))unset($data['real_money']);
        if($is_notify == 1){
            ksort($data);
        }
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$signKey;
        return md5($str);
    }

    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['code'] != '1001') {
            return "faile";
        }
        $siga_data = [
            'partner_id'=>$data['partner_id'],
            'channel_id'=>$data['channel_id'],
            'partner_order'=>$data['partner_order'],
            'order_id'=>$data['order_id'],
            'user_id'=>$data['user_id'],
            'total_fee'=>$data['total_fee'],
            'success_time'=>$data['success_time'],
            'code'=>$data['code']
        ];
        $sign_str = $this->generateSign($siga_data, $this->pay_set['sign_key'],2);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['partner_order'];
        $rsps['trade_sn'] = $data['order_id'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['real_money'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['code'] != '1001') {
            $rsps['status'] = 2;
        }
        $siga_data = [
            'partner_id'=>$data['partner_id'],
            'channel_id'=>$data['channel_id'],
            'partner_order'=>$data['partner_order'],
            'order_id'=>$data['order_id'],
            'user_id'=>$data['user_id'],
            'total_fee'=>$data['total_fee'],
            'success_time'=>$data['success_time'],
            'code'=>$data['code']
        ];
        $sign_str = $this->generateSign($siga_data, $this->pay_set['sign_key'],2);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['partner_order'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}