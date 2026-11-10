<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class UnivePay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['Merchno'] = $this->pay_set["merchno"];//商户号
        $params['Amount'] = $pay_arr['apply_price'];//订单金额
        $params['Traceno'] = $pay_arr['order_sn'];//商户平台流水号
        $params['Pname'] = $pay_arr['username'] ?: 'payname';;//消费者姓名
        $params['Pemail'] = $pay_arr['mail'] ?: '123@gmail.com';//消费者邮箱
        $params['Phone'] = $pay_arr['phone'] ?: '0815648636';;//消费者手机号
        $params['CountryCode'] = $this->pay_set["countryCode"];//国家简写
        $params['Currency'] = $this->pay_set["currency"];//币种
        $params['Timeout'] = time() + 3600;//订单有效期
        $params['PayCode'] = $this->pay_set["payCode"];//交易编码
        $params['GoodsName'] = 'create order';//商品名称
        $params['NotifyUrl'] = $pay_arr['notify_url'];//后台回调通知地址
        $params['Signature'] = $this->generateSign($params, $this->pay_set["signKey"]);

        Log::write(['Submit_create',"UnivePay", $params], "payment");
        $res=PayRequest::curlS($this->pay_set["url"], http_build_query($params));
        Log::write(['Return_create',"UnivePay",$res], "payment");
        $res=json_decode($res,true);
        if (empty($res) ||  $res['status'] != '00') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        if (!isset($res['payUrl'])) {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url']=$res['payUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $b_code = explode('-',$pay_arr['bank_code']);
        if(isset($b_code[1])){
            $bank_codes = $b_code[0] ??'';
            $bank_name = $b_code[1] ??'';
        }else{
            $bank_codes = $this->pay_set['bank_code'] ?? $pay_arr['bank_code'];
            $bank_name = $pay_arr['bankName'];
        }
        //大写
        $bank_name = strtoupper($bank_name);
        $params['Merchno'] = $this->pay_set["merchno"];//商户号
        $params['Amount'] = $pay_arr['amount'];//订单金额

        //银行编码 是：ifsccode,巴西pix
        if( $this->pay_set['currency'] == 'INR' ){
            $params['BankCode'] = $pay_arr['ifsc'];//印度ifsc
        }else{
            $params['BankCode'] = $this->pay_set['bankcode'];
        }
        $params['BankName'] = $this->pay_set['bankName'];//银行名称 巴西pix
        $params['Account'] = $pay_arr['account_name'];//账户姓名
//        $params['CardNo'] = $bank_name == 'PHONE' ? '+55'.$pay_arr['bank_number']:$pay_arr['bank_number'];//银行卡号
        $params['CardNo'] = $pay_arr['bank_number'];//银行卡号
        $params['PaymentType'] = $this->pay_set['paymentType'];//出款方式 默认：UPI 巴西pix
        $params['Traceno'] = $pay_arr['order_sn'];//商户订单号
        $params['NotifyUrl'] = $pay_arr['notify_url'];//回调通知接收地址：次参数传空值降不发送回调通知
        $params['Currency'] = $this->pay_set['currency'];//币种

        if( $this->pay_set['currency'] == 'BRL' ){
            //巴西才有下面这几个参数
            $params['CustomerType'] = $this->pay_set['customerType'];//税号类型：CPF、CNPJ, 三方说固定CPF
            $params['CustomerTypeValue'] = $this->pay_set['customerTypeValue'];//对应的税号值, 三方说固定36900293840

            $params['AccountType'] = $bank_name;//CPF、CNPJ、PHONE、EMAIL 、EVP
            $params['AccountTypeValue'] = $params['CardNo'];//巴西必填:与CardNo保持一致
        }

        $params['Signature'] = $this->generateSign($params, $this->pay_set["signKey"]);//签名

        Log::write(['Submit_withdrawal',"UnivePay",$params], "withdrawal");
        $res=PayRequest::curlS($this->pay_set["withdrawal_url"],http_build_query($params));
        Log::write(['Return_withdrawal',"UnivePay",$res], "withdrawal");
        $res = urldecode($res);
        $res=json_decode($res,true);
        if (empty($res) ||  $res['Status'] == 'Refuse') {
            throw new \Exception($res['Remark'] ??'支付信息错误', 0);
        }
        return $res['SerialNo'] ?? '';
    }

    //异步回调处理
    public function notify($data){
        if ($data['Signature'] != $this->generateSign($data, $this->pay_set["signKey"])){
            return 'sign error';
        }
        if( $data['Status'] != 'SUCCESS' ){
            return 'pay error';
        }

        $rsps['order_sn'] = $data["Traceno"];
        // $rsps['trade_sn'] = $data['SerialNo'];
        $rsps['trade_sn'] = '';//充值没有返回三方订单，不加入该条件
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $order_str['Amount'] ??0;
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($data){
        if ($data['Signature'] != $this->generateSign($data, $this->pay_set["signKey"])){
            return 'sign error';
        }

        $rsps['status'] = 1;
        if ($data["Status"] != 'SUCCESS') {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data["Traceno"];
        $rsps['othr_number'] = $data['SerialNo'];
        $rsps['msg'] = $data['Remark']??'';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

    private function generateSign($data, $signKey){
        ksort($data);
        $str = '';
        unset($data['Signature']);
        foreach ($data as $key => $value) {
            if($value !== ''){
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= $signKey;
        return strtoupper(md5($str));
    }
}