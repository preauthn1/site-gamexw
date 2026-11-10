<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class FbPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['gateway'] = $this->pay_set["gateway"];
        $params['amount'] = $pay_arr['apply_price']; // 订单金额:
        $params['device'] = 'mobile';
        $params['callback_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['merchant_order_time'] = $this->microtime_float(); // 时间
        $params['merchant_order_num'] = $pay_arr['order_sn']; // 订单号，唯一辨别订单的参数，不得重复
        $params['merchant_order_remark'] = 'create order';
        $params['uid'] = $pay_arr['user_id'];
        $params['user_ip']=$pay_arr['pay_ip']; //ip
        $params['return_format']='json';
        if($this->pay_set["gateway"] == 'bank'){
            $params['bank_code'] = $this->pay_set["bank_code"] ?? '';
        }
        $params_data['merchant_slug'] = $this->pay_set['merchant_slug']; // 商号帐号
        $params_data['data'] = $this->encryption($params); // 做签
        Log::write(['Submit_create',"FbPay",$params,$params_data], "payment");
        $req = json_encode($params_data);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res=PayRequest::curlS($this->pay_set["url"],$req,$header);
        Log::write(['Return_create',"FbPay",$res], "payment");
        $res=json_decode($res,true);
        if (empty($res) ||  $res['code'] != 0) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $order = $this->decryption($res['order']);
        if (!isset($order['navigate_url'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        Log::write(['Return_create',"FbPay",'解密数据',$order], "payment");
        $pay_info['pay_url']=$order['navigate_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['gateway'] = $this->pay_set["withdrawal_gateway"];
        $params['merchant_order_num'] = $pay_arr['order_sn']; // 商户订单号
        $params['uid'] = $pay_arr['user_id'];
        $params['amount'] = $pay_arr['amount']; // 订单金额:
        $params['callback_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['merchant_order_time'] = $this->microtime_float(); // 时间
        $params['merchant_order_remark'] = 'create_withdrawal order';
        $params['user_ip']=$pay_arr['pay_ip']; //ip
        $params['bank_code'] = $pay_arr['bank_code'];//银行代码
        $params['card_number'] = $pay_arr['bank_number'];//收款方账号
        $params['card_holder'] = $pay_arr['account_name'];//收款人姓名
        $params['province_code'] = 'beijing';
        $params['city_code'] = '100000';
        $params['area_code'] = '100000';
        $params_data['merchant_slug'] = $this->pay_set['merchant_slug']; // 商号帐号
        $params_data['data'] = $this->encryption($params); // 做签
        Log::write(['Submit_withdrawal',"FbPay",$params,$params_data], "withdrawal");
        $req = json_encode($params_data);
        $header = array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res=PayRequest::curlS($this->pay_set["withdrawal_url"],$req,$header);
        Log::write(['Return_withdrawal',"FbPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if (empty($res) ||  $res['code'] != 0) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        return '';
    }

    //异步回调处理
    public function notify($post_data){
        if ($post_data["code"]!=0) {
            return 'faile';
        }

        $order_str = $this->decryption($post_data["order"]);
        Log::write(['notify_create',"FbPay",'解密数据',$order_str], "payment");
        if (!in_array($order_str['status'],['success','success_done'])){
            return 'status error';
        }

        $rsps['order_sn'] = $post_data["merchant_order_num"];
        $rsps['trade_sn'] = '';
        $rsps['result'] = "success";
        $rsps['money'] = $order_str['amount'] ??0;
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $rsps['status'] = 1;
        if ($post_data["code"]!=0) {
            $rsps['status'] = 2;
        }

        $order_str = $this->decryption($post_data["order"]);
        Log::write(['notify_withdrawal',"FbPay",'解密数据',$order_str], "withdrawal");
        if (!in_array($order_str['status'],['success','success_done'])){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $post_data["merchant_order_num"];
        $rsps['othr_number'] = "";
        $rsps['msg'] = $post_data['msg']??'';
        $rsps['result'] = "success";
        return $rsps;        
    }

    function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    function encryption($plaintext)
    {
        $ciphertext_raw = openssl_encrypt(json_encode($plaintext), "AES-256-CBC", $this->pay_set['hash_key'], OPENSSL_RAW_DATA, $this->pay_set['hash_iv']);
        return base64_encode($ciphertext_raw);
    }

    function decryption($ciphertext_raw)
    {
        $plaintext = openssl_decrypt(base64_decode($ciphertext_raw), "AES-256-CBC", $this->pay_set['hash_key'], OPENSSL_RAW_DATA, $this->pay_set['hash_iv']);
        return  json_decode($plaintext, true);
    }

}