<?php
namespace app\common\payment;
use app\common\model\UserBankModel;
use app\common\payment\PayRequest;
use think\facade\Log;

class SuperumPay
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

        $params['merchant_id'] = $this->pay_set['merchant_id'];//商戶號
        $params['payee_amount'] = intval($pay_arr['apply_price']);//收款金額
        $params['client_order_id'] = $pay_arr['order_sn']; // 商户订单号
        //702:ATM
        //701:信用卡
        //705:超商代碼
        //703:超商條碼
        $params['pay_mode_id'] = $this->pay_set['pay_mode_id']; // 收款渠道編碼
        $params['notify_uri'] = $pay_arr['call_domain']; // 取號通知地址（暂时不用）
        $params['callback_uri'] = $pay_arr['notify_url']; // 回調地址
        $params['sender_name'] = $userBankDetail['bank_hm']; // 轉帳會員姓名
        $params['sender_phone'] = $pay_arr['phone']; // 轉帳會員電話
        $params['sender_idnum'] = $pay_arr['phone']; // 轉帳會員身分證字號

        $params['signature'] = $this->generateSign($params, $this->pay_set['sign_key']);

        //渠道银行卡数据
        $channelBankData = json_decode($pay_arr['pay_data']['pay_bank'], true);
        $bankCode = '';
        foreach ($channelBankData as $v){
            if( $v['bank_name'] == $userBankDetail['bank_name'] ){
                $bankCode = str_replace('dp_','',$v['bank_code']);
            }
        }
        if( !$bankCode ){
            throw new \Exception('该银行卡不属于该渠道', 0);
        }
        $sendBank = [];
        $sendBank['bank_code'] = $bankCode;//銀行代碼
        $sendBank['bank_name'] = $userBankDetail['bank_name'];// 銀行名稱
        if( $userBankDetail['bank_open'] ){
            $sendBank['bank_branch'] = $userBankDetail['bank_open'];//分行名稱
        }
        $sendBank['bank_num'] = $userBankDetail['bank_number'];//銀行帳號

        $params['sender_bank'] = [];
        $params['sender_bank'][] = $sendBank;


        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        Log::write(['Submit_create', "SuperumPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], json_encode($params),$header);
        Log::write(['Return_create', "SuperumPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != '1' || !isset($res['cashier_uri'])) {
            throw new \Exception($res['err_msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['cashier_uri'];
//        $pay_info['othr_number'] = $res['order_id'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $b_code = explode('-',$pay_arr['bank_code']);
        if(isset($b_code[1])){
            $bank_codes = $b_code[0] ??'';
            $bank_name = $b_code[1] ??'';
        }else{
            $bank_codes = $this->pay_set['bank_code'] ?? $pay_arr['bank_code'];
            $bank_name = $pay_arr['bankName'];
        }
        $params['merchant_id'] = $this->pay_set['merchant_id'];//商戶號
        $params['payee_name'] = $pay_arr['account_name'];//收款人開戶名
        $params['payee_bank_code'] = $bank_codes;//收款人開戶銀行代碼
        $params['payee_bank'] = $bank_name;//收款人開戶銀行
        if(isset($pay_arr['bank_open']) && $pay_arr['bank_open']){
            $params['payee_branch'] = $pay_arr['bank_open'];//收款人開戶分行
        }
        $params['payee_number'] = $pay_arr['bank_number'];//收款人銀行卡號
        $params['payee_amount'] = intval($pay_arr['amount']);//付款金額
        $params['client_order_id'] = $pay_arr['order_sn'];//商戶訂單編號(不得重複)
        $params['callback_uri'] = $pay_arr['notify_url'];//回調地址

        $params['signature'] = $this->generateSign($params, $this->pay_set['sign_key']);
        Log::write(['Submit_withdrawal', "SuperumPay", $params], "withdrawal");
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], json_encode($params),$header);
        Log::write(['Return_withdrawal', "SuperumPay", $res], "withdrawal");
        $res = json_decode($res, true);
        // dd($params,$header,$res);
        if (empty($res) ||  $res['status'] != '1') {
            throw new \Exception("渠道返回:" . $res['err_msg']??'支付信息错误', 0);
        }
        return '';
//        return $res['order_id'];
    }

    private function generateSign($data, $signKey)
    {
        unset($data["signature"]);
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            // if($value != ''){
            $str .= $key . '=' . $value . '&';
            // }
        }
        $str .= 'hash_key=' . $signKey;
        $str = urlencode($str);
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['signature']) ? $data['signature'] : '';

        if ($data['state'] != '1009') {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['client_order_id'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        $rsps['money'] = $data['payee_amount'] ??0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = isset($data['signature']) ? $data['signature'] : '';

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }


        $rsps['status'] = 1;
        if( $data['state'] != '2009' ){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['client_order_id'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '无';
        $rsps['result'] = "success";
        return $rsps;
    }
}
