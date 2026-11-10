<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class QueenPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['merchant'] = $this->pay_set['merchant']; //商户号
        $params['payment_type'] = $this->pay_set['type']; //类型 1 银行卡 & 卡转卡 2扫码&网关
        $params['amount'] =  $pay_arr['apply_price']; // 订单金额 只能整型
        $params['order_id'] = $pay_arr['order_sn']; //订单号
        $params['bank_code'] = 'ICIC'; //
        $params['callback_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['return_url'] = $pay_arr['call_domain']; // 重定向

        $sign = $this->generateSign($params);

        Log::write(['Submit_create', "QueenPay", $params], "payment");
        $params['sign'] = $sign;
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . '/api/transfer';
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_create', "QueenPay", $res], "payment");
        $res = json_decode($res, true);

        if (empty($res) ||  $res['status'] != 1) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['redirect_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merchant'] = $this->pay_set['merchant']; //商户号
        $params['total_amount'] = $pay_arr['amount'];
        $params['callback_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['order_id'] = $pay_arr['order_sn']; //订单号
        $params['bank'] = $pay_arr['bank_code']; //银行代码
        $params['bank_card_name'] = $pay_arr['account_name']; //开户名
        $params['bank_card_account'] = $pay_arr['bank_number']; //收款人账号
        $params['bank_card_remark'] = $pay_arr['ifsc']; //IFSC Code
        $sign = $this->generateSign($params);
        Log::write(['Submit_create', "QueenPay", $params], "withdrawal");
        $params['sign'] = $sign;
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . '/api/daifu';
        $res = PayRequest::curlS($url, $req,$header);
        Log::write(['Return_withdrawal', "QueenPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != 1) {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['order'] ??'';
    }

    private function generateSign($data)
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
        $str .= 'key='. $this->pay_set['key'];
        Log::write(['SignStr', "QueenPay", $str], "payment");
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != '5') {
            return "faile";
        }
        unset($data['sign']);
        $sign_str = $this->generateSign($data);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_id'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ? $data['amount']:0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != '5') {
            $rsps['status'] = 2;
//            return "faile";
        }
        unset($data['sign']);
        $sign_str = $this->generateSign($data);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_id'];
        $rsps['othr_number']='';
        $rsps['msg'] = "";
        $rsps['result'] = "success";
        return $rsps;
    }


    //    //获取三方代收余额
    public function get_balance($type){
        $params['merchant'] = $this->pay_set['merchant']; //商户号
        $sign = $this->generateSign($params);

        Log::write(['Submit_create', "QueenPay", $params], "payment");
        $params['sign'] = $sign;
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['url'] . '/api/balance';
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_create', "QueenPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != 1) {
            if($type == 1){
                //抛出错误
                throw new \Exception('支付信息错误', 0);
            }else{
                return  'fail';
            }
        }
        return $res['balance'] ?? '';
    }
}
