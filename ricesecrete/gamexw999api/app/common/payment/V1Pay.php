<?php
namespace app\common\payment;
use app\common\model\UserBankModel;
use app\common\payment\PayRequest;
use think\facade\Log;

class V1Pay
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
                $bankCode = str_replace('dp_','',$v['bank_code']);
            }
        }
        if( !$bankCode ){
            throw new \Exception('该银行卡不属于该渠道', 0);
        }

        $data = [
            'orderid' => $pay_arr['order_sn'],//商户订单号
            'amount' => intval($pay_arr['apply_price']),//金额
            //支付方式
            //1. 銀行轉帳
            //2. 超商三段條碼
            //3. IBON、FAMIPORT
            'type' => $this->pay_set['payment_type'],//类型
            'phone' => $pay_arr['phone'],//手机号
            'payername' => $userBankDetail['bank_hm'],//付款人姓名 Type 為2,3時必填
            'payerpostcode' => $bankCode,//付款人郵遞區號 Type 為2,3時必填
            'payeraddress' => $userBankDetail['bank_name'],//付款人地址 Type 為2,3時必填
            'payeremail' =>  $pay_arr['mail'] ?: $pay_arr['phone'] . '@gmail.com',//付款人地址 Type 為2,3時必填
            'url' => $pay_arr['notify_url'],//回調地址
        ];
        $params = $this->handlePostParams($data);

        $header = array(
            'Content-Type:application/x-www-form-urlencoded'
        );
        Log::write(['Submit_create', "V1Pay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], http_build_query($params),$header);
        Log::write(['Return_create', "V1Pay", $res, $params], "payment");
        $res = json_decode($res, true);
        if( !isset($res['data']) ){
            throw new \Exception(lang('支付信息错误'.'data not found'), 0);
        }
        //解密参数
        $responseData = $this->aes_decode($res['data'], $this->pay_set['api_key']);


        if (empty($responseData) || $responseData['code'] != 0 || !isset($responseData['cashier'])) {
            throw new \Exception($responseData['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $responseData['cashier'];
        $pay_info['othr_number'] = $responseData['system_bill_number'];
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

        $data = [
            'orderid' => $pay_arr['order_sn'],//商户订单号
            'amount' => intval($pay_arr['amount']),//金额
            'bank' => $bank_name,//银行名称
            'branch' => $bank_name,//分行名称
            'bank_code' => $bank_codes,//銀行代碼
            'account' => $pay_arr['bank_number'],//收款人帳號
            'receiver' => $pay_arr['account_name'],//收款人姓名
            'url' => $pay_arr['notify_url'],//回調地址
        ];

        $params = $this->handlePostParams($data);

        Log::write(['Submit_withdrawal', "V1Pay", $params], "withdrawal");
        $header = array(
            'Content-Type:application/x-www-form-urlencoded'
        );
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], http_build_query($params),$header);
        Log::write(['Return_withdrawal', "V1Pay", $res], "withdrawal");
        $res = json_decode($res, true);

        if (empty($res) || $res['code'] != 0) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        return $res['system_bill_number'];
    }

    //处理post参数
    private function handlePostParams($data){
        $time = time();
        $params = [
            'user_id' => $this->pay_set['user_id'],//商戶號 (userid 亦可)
            'api_key' => $this->pay_set['api_key'],//商戶私密金鑰
            'timestamp' => $time,//時間戳記
            'data' => $this->aes_encode(json_encode($data,true), $this->pay_set['api_key']),//AES 加密後的資料
        ];

        return [
            'user_id' => $this->pay_set['user_id'],
            'timestamp' => $time,
            'sign' => $this->generateSign($params),
            'data' => $params['data']
        ];
    }

    private function generateSign($data)
    {
        ksort($data);
        return strtolower(md5(strtolower(sha1(http_build_query($data)))));
    }

    //异步回调支付
    public function notify($data)
    {
        $sign = $data['sign'] ?? '';
        $data['api_key'] = $this->pay_set['api_key'];

        //status 等待、待付款、成功
        if ($data['status'] != '成功') {
            return "faile";
        }

        unset($data['sign']);
        $sign_str = $this->generateSign($data);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['orderid'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ?? 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = $data['sign'] ?? '';
        $data['api_key'] = $this->pay_set['api_key'];

        if( !in_array($data['status'], ['成功', '失敗']) ){
            return 'faile';
        }

        $sign_str = $this->generateSign($data);
        if ($sign_str != $sign) {
            return "sign error";
        }

        $rsps['status'] = 1;
        if( $data['status'] != '成功' ){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['orderid'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = $data['message'] ?? '';
        $rsps['result'] = "success";
        return $rsps;
    }

    //aes 加密
    private function aes_encode($tempStr,$aes_key){
        return strtoupper(bin2hex(openssl_encrypt($tempStr ,'AES-256-ECB' ,$aes_key ,OPENSSL_RAW_DATA)));
    }

    private function aes_decode($tempStr,$aes_key){
        return json_decode(openssl_decrypt(hex2bin($tempStr) ,'AES-256-ECB' ,$aes_key ,OPENSSL_RAW_DATA), true);
    }
}
