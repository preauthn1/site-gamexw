<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class CrushPay
{
    private $merchant_id = "";
    private $sign_key = "";
    private $url = '';
    private $withdrawal_url = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->merchant_id = $pay_set['merchant_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->url = $pay_set['url'];
        $this->withdrawal_url = $pay_set['withdrawal_url'];
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['data'] = json_encode([
            'merchantOrderId'=>$pay_arr['order_sn'],    //订单号
            'orderAmount'=>intval($pay_arr['apply_price']),  //订单金额
            'payerName'=>$pay_arr['username'], //用户名
            'redirectUrl'=>$pay_arr['call_domain'], //重定向
            'notifyUrl'=>$this->domain . '/notify/CrushPay', //异步通知
            'sign'=>$this->generateSign(),
            'timestamp'=>time(),
        ],true);
        $data = json_encode($params,true);
        Log::write(['Submit_create', "CrushPay", $data], "payment");
        $header = array(
            'Content-Type: application/json',
            'Authorization: ' .$this->merchant_id
        );
        $res = PayRequest::curlS($this->url, $data,$header);
        Log::write(['Return_create', "CrushPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != 0000) {
            throw new \Exception($res['msg'], 0);
        }
        $pay_info['pay_url'] = $res['data']['payUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {

        $params['data'] = json_encode([
            'merchantOrderId'=>$pay_arr['order_sn'],    //订单号
            'orderAmount'=>intval($pay_arr['amount']),  //订单金额
            'payeeName'=>$pay_arr['account_name'], //收款人姓名
            'payeeBankAccount'=>$pay_arr['bank_number'], //银行账号
            'payeeBankName'=>$pay_arr['bankName'], //银行名称
            'notifyUrl'=>$this->domain . '/withdrawal/notify/CrushPay', //异步通知
            'sign'=>$this->generateSign(),
            'timestamp'=>time(),
        ],true);
        $data = json_encode($params,true);
        Log::write(['Submit_withdrawal', "CrushPay", $data], "withdrawal");
        $header = array(
            'Content-Type: application/json',
            'Authorization: ' .$this->merchant_id
        );
        $res = PayRequest::curlS($this->withdrawal_url, $data,$header);
        Log::write(['Return_withdrawal', "CrushPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != 0000) {
            throw new \Exception("渠道返回:" . $res['msg'], 0);
        }
        return $res['data']['orderId'];
    }

    private function generateSign(){
        $str = $this->merchant_id.time().$this->sign_key;
        return strtoupper(md5($str));
    }


    //异步回调支付
    public function notify($data)
    {
        $data = $this->decryptAes($data['data']);
        Log::write(['notify_create', "CrushPay",'解密数据', $data], "payment");
        if ($data['status'] != 'SUCCESS') {
            return "faile";
        }

        $rsps['order_sn'] = $data['merchantOrderId'];
        $rsps['trade_sn'] = $data['orderId'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = isset($data['payAmount'])?$data['payAmount']:0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        $data = $this->decryptAes($data['data']);
        Log::write(['notify_withdrawal', "CrushPay",'解密数据', $data], "withdrawal");
        if ($data['status'] != 'SUCCESS') {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['merchantOrderId'];
        $rsps['othr_number'] = $data['orderId'];
        $rsps['msg'] = '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    //解密
    public function decryptAes($aesSecret)
    {
        $str="";
        for($i=0;$i<strlen($aesSecret)-1;$i+=2){
            $str.=chr(hexdec($aesSecret[$i].$aesSecret[$i+1]));
        }
        $jsonData =  openssl_decrypt($str,'AES-128-CBC',$this->sign_key, OPENSSL_RAW_DATA,$this->sign_key);
        $data = json_decode($jsonData,true);
        return $data;
    }
}
