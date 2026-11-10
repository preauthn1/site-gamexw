<?php
namespace app\common\payment;
use app\common\FastGame\Base\Utils;
use app\common\payment\PayRequest;
use think\facade\Log;

class UniberseBsdPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //钱包id
    //WalletNameID	name
    //1	bKash
    //2	Rocket
    //3	Nagad
    //4	BRAC
    //5	SureCash
    //6	TAP
    //7	UPay
    //8	OKWallet
    //9	EasyPaisa
    //10	JazzCash
    //11	UPaisa
    //12	Nayapay
    //13	Paymax
    //14	DBBL
    //15	IBBL
    //16	CBL
    //17	BANK ASIA
    //18	EASTERN BANK
    //19	IFIC BANK
    //20	PRIME BANK
    //21	AB BANK
    //22	JazzCash Till ID
    //23	SEBL

    //获取支付信息
    public function create($pay_arr)
    {
        $data = [];
        $data['Amount'] = $pay_arr['apply_price'];#充值金额
        $data['Player'] = Utils::random(6);#用户名
        $data['WalletNameID'] = $this->get_wallet_id($this->pay_set['WalletNameID']);#钱包ID
        $data['NotifyURL'] = $pay_arr['notify_url'];#通知回调地址
        $data['VendorTranID'] = $pay_arr['order_sn'];#商户订单号

        $params = [];
        $params['data'] = $data;
        $params['sign'] = $this->generateSign($data, $this->pay_set['sign_key']);

        $headers = [
            'Content-Type: application/json',
            'NAME: ' . $this->pay_set['merchantname'],
            'APPID: ' . $this->pay_set['sign_key'],
        ];

        Log::write(['Submit_create', "UniberseBsdPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], json_encode($params), $headers);
        Log::write(['Return_create', "UniberseBsdPay", $res], "payment");
        $res = json_decode($res, true);

        if (empty($res) || $res['status'] != 1 || !isset($res['url'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['url'];
        $pay_info['othr_number'] = '';
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

        $data = [];
        $data['Amount'] = $pay_arr['amount'];#提现金额
        $data['Player'] = $pay_arr['username'];#用户名
        $data['PlayerWalletNum'] = $pay_arr['bank_number'];#账号
        $data['WalletNameID'] = $this->get_wallet_id($this->pay_set['WalletNameID']);#钱包ID
        $data['NotifyURL'] = $pay_arr['notify_url'];#通知地址
        $data['VendorTranID'] = $pay_arr['order_sn'];#商户订单号

        Log::write(['Submit_withdrawal', "UniberseBsdPayData",$data], "withdrawal");
        $params = [];
        $params['data'] = $data;
        $params['sign'] = $this->generateSign($data, $this->pay_set['sign_key']);
        $headers = [
            'Content-Type: application/json',
            'NAME: ' . $this->pay_set['merchantname'],
            'APPID: ' . $this->pay_set['sign_key'],
        ];

        Log::write(['Submit_withdrawal', "UniberseBsdPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], json_encode($params), $headers);
        Log::write(['Return_withdrawal', "UniberseBsdPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != 1 ) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        return '';
    }

    private function generateSign($data, $signKey)
    {
        unset($data["sign"]);
        ksort($data);
        $signStr = '';
        foreach ($data as $k => $v){
            if( $v !== '' ){
                $signStr.= $k . '=' . $v . '&';
            }
        }
        $signStr .= 'key=' . $signKey;
        return md5($signStr);
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['sign']) ? $data['sign'] : '';
        if ($data['status'] != 2) {
            return "faile";
        }

        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_id'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['amount'];
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        //status 2 成功 3 失败
        $sign = isset($data['sign']) ? $data['sign'] : '';
        $rsps['status'] = 1;
        if ($data['status'] != 2) {
            $rsps['status'] = 2;
        }
        $sign_str = $this->generateSign($data, $this->pay_set['sign_key']);
        if ($sign_str != $sign) {
            return "faile";
        }
        $rsps['order_sn'] = $data['order_id'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = $data['message']??'';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    //获取钱包id
    private function get_wallet_id($bank_code)
    {
        $bank_code = strtoupper($bank_code);
        $paymentServices = [
            'BKASH' => 1,
            'ROCKET' => 2,
            'NAGAD' => 3,
            'BRAC' => 4,
            'SureCash' => 5,
            'TAP' => 6,
            'UPAY' => 7,
            'OKWallet' => 8,
            'EasyPaisa' => 9,
            'JazzCash' => 10,
            'UPaisa' => 11,
            'Nayapay' => 12,
            'Paymax' => 13,
            'DBBL' => 14,
            'IBBL' => 15,
            'CBL' => 16,
            'BANK ASIA' => 17,
            'EASTERN BANK' => 18,
            'IFIC BANK' => 19,
            'PRIME BANK' => 20,
            'AB BANK' => 21,
            'JazzCash Till ID' => 22,
            'SEBL' => 23
        ];
        return $paymentServices[$bank_code] ?? 0;
    }
}
