<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class HqPay1
{

    private $mer_id = "";
    private $sign_key = "";
    private $gateway = '';
    private $wallet_id = '';
    private $domain = "";
    public function __construct($pay_set,$domain){
        $this->mer_id = $pay_set['mer_id'];
        $this->sign_key = $pay_set['sign_key'];
        $this->gateway = $pay_set['gateway'];
        $this->wallet_id = $pay_set['wallet_id'] ??40;
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params = [
            'mer_id' => $this->mer_id, //商户id
            'timestamp' => date('Y-m-d H:i:s'), //订单时间
            'terminal' => 'H5',  //渠道类型：PC-网关  H5-快捷  ALI_CODE-支付宝扫码 ALI_PAY_WAP-支付宝H5  WEIXIN_CODE-微信扫码  WEIXIN_PAY_WAP-微信H5
            'version' => '01', //版本
            'amount' => $pay_arr['apply_price']*100, //收款金额 以 ‘分’ 为单位
            'backurl' => $pay_arr['call_domain'],  //支付成功同步回调地址
            'failUrl' => $pay_arr['call_domain'],  //支付失败同步回调地址
            'ServerUrl' => $this->domain.'/notify/HqPay1', //成功支付异步回调地址
            'businessnumber' => $pay_arr['order_sn'], //订单号 必须以数字或者字母组合，长度必须30位 保证不重复
            'goodsName' => $pay_arr['username'], //商品名称
            'IsCredit' => 0,  //可空 直连必填(0:非直联 1：直联)
            'BankCode' =>'',  //可空 直连必填(IPS 唯一标识指定支付银行的编号)
            'ProductType' => '',//可空 直连必填(1个人网银 2企业网银)
            'UserId' => '',//可空 商户客户唯一标识id
            'UserRealName' => $pay_arr['username'],//可空 付款人姓名
            'UserRealMobile' => $pay_arr['phone'],//可空 付款人手机号
            'UserRealBankCard' => '',//可空 付款人银行卡号
            'UserRealIdNumber' => '',//可空 付款人身份证号
        ];

        $params['sign'] = $this->generateSign($params, $this->sign_key);
        $params['sign_type'] = 'md5';
        Log::write(['Submit_create',"HqPay1",$params], "payment");
        $url = $this->gateway.'/customer.Pay';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_create',"HqPay1",$res], "payment");
        $res = json_decode($res,true);
        if (empty($res) || $res['result'] != "success" && $res['code'] != "MSG_OK") {
            throw new \Exception('支付信息错误', 0);
        }
        if(isset($res['data']) && $res['data']['status'] != '成功'){
            $msg = isset($res['data']['remark']) ? json_decode($res['data']['remark'],true):'';
            if($msg && $msg['platRespCode'] == 'FAIL'){
                throw new \Exception($msg['platRespMessage'], 0);
            }
        }
        $pay_info['pay_url']=$res['data']['trade_qrcode'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params = [
            'mer_id' => $this->mer_id, //商户编号
            'timestamp' => date('Y-m-d H:i:s'), //订单时间
            'terminal' => 'PC', //代付类型
            'version' => '01', //版本
            'amount' => $pay_arr['amount']*100, //代付金额 以‘分’为单位
            'businessnumber' => $pay_arr['order_sn'],  //订单号 必须以数字或者字母组合，长度必须30位
            'bankcardnumber' => $pay_arr['bank_number'], //银行卡号
            'bankcardname' => $pay_arr['account_name'], //卡号用户名称
            'bankname' => $pay_arr['bank_code'] ? $pay_arr['bank_code']:$pay_arr['bankName'], //开户名称
            'back_url'=> $this->domain.'/withdrawal/notify/HqPay1',//异步回调地址
            'cash_type' => 3,//.1=>对私取现(代付业务) 2=>对公取现(代付业务) 3=》易收付（收款取现）
            'wallet_id' => $this->wallet_id, //钱包ID(可在商户后台→账户中心→通道服务→易收付 查看)
        ];

        $params['sign'] = $this->generateSign($params, $this->sign_key);
        $params['sign_type'] = 'md5';
        Log::write(['Submit_withdrawal',"HqPay1",$params], "withdrawal");
        $url = $this->gateway.'/pay.bank.to';
        $res = PayRequest::curlS($url,$params);
        Log::write(['Return_withdrawal',"HqPay1",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['result'] != "success" && $res['code'] != "MSG_OK") {
            throw new \Exception("渠道返回:".$res['msg'], 0);
        }
        return "";
    }

    private function generateSign($data, $signKey){
        unset($data['sign'], $data['sign_type']);
        ksort($data);//根据键值升序排列
        $str_sign = '';
        foreach ($data as $key => $value) {
            $str_sign .= $key . '=' . $value . '&';
        }
        $str_sign .= $signKey;
        return strtoupper(md5($str_sign));
    }


    //异步回调支付fmpay
    public function notify($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        unset($data['sign']);//不参与签名
        if (isset($data['sign']) && isset($data['status'])) {
            return "faile";
        }
        $sign_str =$this->generateSign($data, $this->sign_key);
        if($notify_sign != $sign_str){
            return "sign error";
        }

        $rsps['order_sn'] = $data['businessnumber'];
        $rsps['trade_sn'] = "";
        $rsps['result'] = "success";
        return $rsps;
    }


    //异步回调支付fmpay
    public function notify_withdrawal($post_data){
        $data = $post_data;
        $notify_sign = $data['sign']??'';
        $rsps = [];
        $rsps['status'] = 1;
        unset($data['sign']);//不参与签名
        if (isset($data['sign']) && isset($data['status'])) {
            $rsps['status'] = 2;
        }
        $sign_str =$this->generateSign($data, $this->sign_key);
        if($notify_sign != $sign_str){
            $rsps['status'] = 2;
        }

        if ($data['status'] != '成功') {
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $data['businessnumber'];
        $rsps['othr_number'] = "";
        $rsps['msg'] = $data['remark'];
        $rsps['result'] = "success";
        return $rsps;
    }

}