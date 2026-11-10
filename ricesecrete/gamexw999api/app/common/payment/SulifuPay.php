<?php
namespace app\common\payment;
use app\common\model\UserBankModel;
use app\common\payment\PayRequest;
use think\facade\Log;

class SulifuPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        if( !isset($pay_arr['bank_id']) || !$pay_arr['bank_id'] ){
            throw new \Exception('该渠道必须选择银行卡', 0);
        }

        //获取银行卡数据
        $userBankDetail = UserBankModel::where('id', '=', $pay_arr['bank_id'])->find();
        if( !$userBankDetail ){
            throw new \Exception('该渠道必须选择银行卡', 0);
        }
        //渠道银行卡数据
        $channelBankData = json_decode($pay_arr['pay_data']['pay_bank'], true);
        $bankCode = '';
        foreach ($channelBankData as $v){
            if( $v['bank_name'] == $userBankDetail['bank_name'] ){
                $bankCode = $v['bank_code'];
            }
        }
        if( !$bankCode ){
            throw new \Exception('该银行卡不属于该渠道', 0);
        }
        $params['merNo'] = $this->pay_set['merNo'];//商户号
        $params['tradeNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['cType'] = $this->pay_set['cType'];
        $params['bankCode'] = '';
        $params['orderAmount'] = $pay_arr['apply_price']; // 订单金额
        $params['playerName'] = $userBankDetail['bank_hm'];
        $params['supermarketCode'] = $this->pay_set['supermarketCode'];
        $params['playerPayAcc'] = str_replace('dp_', '', $bankCode) . '-' . $userBankDetail['bank_number'];
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['returnUrl'] = $pay_arr['call_domain']; // 重定向
        $sign_data = [
            'merNo' => $params['merNo'],
            'tradeNo' => $params['tradeNo'],
            'orderAmount' => $params['orderAmount'],
        ];
        $params['sign'] = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        Log::write(['Submit_create', "SulifuPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], $params);
        Log::write(['Return_create', "SulifuPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['Success'] != '1') {
            throw new \Exception($res['Message'] ??'支付信息错误', 0);
        }
        if (!isset($res['PayPage'])) {
            throw new \Exception($res['Message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['PayPage'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['merNo'] = $this->pay_set['merNo'];//商户号
        $params['tradeNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['cType'] = $this->pay_set['withdrawal_cType'];
        $params['bankCode'] = $pay_arr['bank_code']; //银行编码
        $params['bankCardNo'] = $pay_arr['bank_number']; //银行卡号
        $params['orderAmount'] = $pay_arr['amount']; // 订单金额
        $params['accountName'] = $pay_arr['account_name']; //姓名
        $params['openProvince'] = '1';
        $params['openCity'] = '1';
        $params['notifyUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $sign_data = [
            'merNo' => $params['merNo'],
            'tradeNo' => $params['tradeNo'],
            'bankCode' => $params['bankCode'],
            'orderAmount' => $params['orderAmount'],
        ];
        $params['sign'] = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "SulifuPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'],$params);
        Log::write(['Return_withdrawal', "SulifuPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['Success'] != '1') {
            throw new \Exception("渠道返回:" . $res['Message'], 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('' != $value) {
                $str .= $value;
            }
        }
        $str .= $signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['tradeStatus'] != '1') {
            return "faile";
        }
        $sign_data = [
            'tradeNo' => $data['tradeNo'],
            'topupAmount' => $data['topupAmount'],
        ];
        $sign_str = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['tradeNo'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['topupAmount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['tradeStatus'] != '1') {
            $rsps['status'] = 2;
        }
        $sign_data = [
            'tradeNo' => $data['tradeNo'],
            'orderAmount' => $data['orderAmount'],
        ];
        $sign_str = $this->generateSign($sign_data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['tradeNo'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = $data['message'] ??'';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }
}
