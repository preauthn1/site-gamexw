<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
use app\common\model\UserBankModel;


class OnlinePay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['transaction_id'] = $pay_arr['order_sn']; // 商户订单号
        $params['payment_method'] = $this->pay_set['payment_method'];
        $params['amount'] = intval($pay_arr['apply_price']); // 订单金额
        $params['name'] = UserBankModel::where(["uid"=>$pay_arr['user_id'],'bank_type'=>0])->order('id desc')->value('bank_hm') ??'xxxxx';
        $params['phone_number'] = $pay_arr['phone'];
        $params['email'] = $pay_arr['mail'] ??$pay_arr['username']."@in.com";
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['return_url'] = $pay_arr['call_domain']; // 重定向
        $params['description'] = 'orders'; // 商品描述
        $sign_data = [
            'amount'=> $params['amount'],
            'payment_method'=> $params['payment_method'],
            'transaction_id'=> $params['transaction_id'],
        ];
        $params['hash'] = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        $header = array(
            'Content-Type:application/x-www-form-urlencoded',
            'Authorization:Bearer '. $this->pay_set['token']
        );
        Log::write(['Submit_create', "OnlinePay", $params,$header], "payment");
        $res = PayRequest::curlS($this->pay_set['url'],$params,$header);
        Log::write(['Return_create', "OnlinePay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || isset($res['errors'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['data']['redirect_to'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['redirect_to'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['transaction_id'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] =  intval($pay_arr['amount']); // 订单金额
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['phone_number'] = $pay_arr['phone'];
        $params['account_number'] = $pay_arr['bank_number']; //银行卡号
        $params['name'] = $pay_arr['account_name']; //姓名
        $params['bank_code'] = $pay_arr['bank_code']; //银行编码
        $params['description'] = 'withdrawal orders'; // 商品描述
        $sign_data = [
            'account_number'=> $params['account_number'],
            'amount'=> $params['amount'],
            'bank_code'=> $params['bank_code'],
            'transaction_id'=> $params['transaction_id'],
        ];
        $params['hash'] = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        $header = array(
            'Content-Type:application/x-www-form-urlencoded',
            'Authorization:Bearer '. $this->pay_set['token']
        );
        Log::write(['Submit_withdrawal', "OnlinePay", $params,$header], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$params,$header);
        Log::write(['Return_withdrawal', "OnlinePay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || isset($res['errors'])) {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['data']['transaction_reference'] ??'';
    }

    private function generateSign($data, $signKey)
    {
        unset($data['hash']);
        ksort($data);
        $str = $this->pay_set['token'];
        $str .= http_build_query($data);
        $sign_str = strtr(base64_encode(hash_hmac("sha256", $str, $signKey, true)), '+/', '-_');
        return $sign_str;
    }


    //异步回调支付
    public function notify($data)
    {
        $data = $data['data'];
        $sign = isset($data['hash']) ? $data['hash'] : '';
        if ($data['status'] != 'completed') {
            return "faile";
        }

        $sign_data = [
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'request_amount' => $data['request_amount'],
            'status' => $data['status'],
            'transaction_id' => $data['transaction_id'],
            'transaction_reference' => $data['transaction_reference'],
        ];

        $sign_str = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['transaction_id'];
        $rsps['trade_sn'] = $data['transaction_reference'];
        $rsps['result'] = "OK";
        $rsps['money'] = $data['request_amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $data = $data['data'];
        $sign = isset($data['hash']) ? $data['hash'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != 'completed') {
            $rsps['status'] = 2;
        }

        $sign_data = [
            'account_number' => $data['account_number'],
            'amount' => $data['amount'],
            'bank_code' => $data['bank_code'],
            'status' => $data['status'],
            'transaction_id' => $data['transaction_id'],
            'transaction_reference' => $data['transaction_reference'],
        ];

        $sign_str = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['transaction_id'];
        $rsps['othr_number'] = $data['transaction_reference'];
        $rsps['msg'] = '';
        $rsps['result'] = "OK";
        return $rsps;
    }
}
