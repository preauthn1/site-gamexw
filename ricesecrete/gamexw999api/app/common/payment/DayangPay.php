<?php

namespace app\common\payment;

use think\facade\Log;

class DayangPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
        //参数说明
        //url 代收下单地址
        //withdrawal_url 代付下单地址
        //secret 密钥
        //client_key 商户标识。由大洋支付颁发
        //channel_id 代收通道 1003-巴西代收(PIX)
        //withdrawal_channel_id 代付渠道 5003-巴西代付(PIX)

    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['amount'] = $pay_arr['apply_price'];#金额
        $params['out_trade_no'] = $pay_arr['order_sn'];#订单号
        $params['notify_url'] = $pay_arr['notify_url'];#回调地址
        $params['channel_id'] = $this->pay_set['channel_id'];//代收渠道 1003-巴西代收(PIX)
        $params = $this->publicParams($params);

        Log::write(['Submit_create', "DayangPay", $params], "payment");
        $headers = [
            "Accept: application/json",
        ];
        $res = PayRequest::curlS($this->pay_set['url'], $params, $headers);
        Log::write(['Return_create', "DayangPay", $res], "payment");
        $res = $this->publicResponse($res);
        if (!isset($res['payment_url'])) {
            throw new \Exception($res['message'] ?? '支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['payment_url'];
        $pay_info['othr_number'] = $res['trade_no'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['amount'] = $pay_arr['amount'];//金额
        $params['payee_account'] = $pay_arr['bank_number'];
        $params['payee_name'] = $pay_arr['username'];
        $params['out_transfer_no'] = $pay_arr['order_sn'];
        $params['notify_url'] = $pay_arr['notify_url'];
        $params['channel_id'] = $this->pay_set['withdrawal_channel_id'];//代付渠道 5003-巴西代付(PIX)
        $extra = [
            'account_type' => $pay_arr['bank_code'],
            'id_number' => $pay_arr['bank_open']
        ];
        $params['extra'] = json_encode($extra);
        $params = $this->publicParams($params);

        Log::write(['Submit_withdrawal', "DayangPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal', "DayangPay", $res], "withdrawal");
        $res = $this->publicResponse($res);
        if (empty($res) || (!isset($res['transfer_no']))) {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['transfer_no'];
    }

    //通用params
    public function publicParams($datas = [])
    {
        $datas['client_key'] = $this->pay_set['client_key'];
        $datas['signature'] = $this->generateSign($datas);
        return $datas;
    }

    /**
     * Create a string to sign given parameters.
     *
     * @param array $parameters
     * @return string
     */
    function generateSign($parameters)
    {
        ksort($parameters);
        $string = "";
        foreach ($parameters as $key => $value) {
            $string .= $key . "=" . $value . "&";
        }
        $stringToSign = substr($string, 0, -1);
        return hash_hmac('sha256', $stringToSign, $this->pay_set['secret']);
    }

    //异步回调支付
    public function notify($data)
    {
        if (!isset($data['status']) || $data['status'] != 1) {
            return "faile";
        }
        $rsps['order_sn'] = $data['out_trade_no'];
        $rsps['trade_sn'] = $data['trade_no'];
        $rsps['result'] = ['code' => 'SUCCESS'];
        $rsps['money'] = $data['amount'];
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        if (!isset($data['status']) || $data['status'] != 1) {
            $rsps['status'] = 2;
            $result = ['code' => 'ERROR'];
            $message = $data['message'];
        } else {
            $result = ['code' => 'SUCCESS'];
            $message = '';
        }
        $rsps['order_sn'] = $data['out_transfer_no'];
        $rsps['othr_number'] = $data['transfer_no'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = $message;
        $rsps['result'] = $result;
        return $rsps;
    }

    private function publicResponse($res)
    {
        return json_decode($res, true);
    }
}