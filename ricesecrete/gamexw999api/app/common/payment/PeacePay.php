<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;
class PeacePay
{
    private $pay_set = [];
    public function __construct($pay_set,$domain){
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['merchantCode'] = $this->pay_set['merchant_code'];#商户号
        //代收订单类型，依据会员要打款的⽅式，可⽤的类型有：
        //C2C_DIRECT: 银⾏卡对卡 (⽹关) (会员使⽤银⾏卡转⼊)
        //C2C_UPI: UPI通⽤⽀付 (会员使⽤UPI转⼊)
        //C2C_UPI_QR: UPI扫码⽀付 (会员使⽤UPI钱包扫码转⼊)
        //C2C_UPI_INTENT: UPI唤醒模式 (UPI Intent Flow) (会员
        //使⽤UPI转⼊，收银台可直接点击付款后，唤醒UPI钱包⽀付)
        //C2C_UPI_COLLECT: UPI⽀付请求模式 (UPI CollectionFlow) (会员使⽤UPI转⼊，会员需输⼊⾃⼰的UPI地址后，会收到通知唤醒UPI钱包)
        $params['method'] = $this->pay_set['method']; #代收订单类型
        $params['signType'] = 'MD5';#签名类型
        $params['timestamp'] = date('YmdHis');#时间戳需使⽤UTC时区，格式如下：yyyyMMddHHmmss
        $params['merchantOrderNo'] = $pay_arr['order_sn'];#订单号
        $params['orderAmount'] = $pay_arr['apply_price'] * 100;#金额 若⾦额为₹100.23，请填⼊10023；若⾦额为₹600，请填⼊60000
        $params['accountName'] = 'test';#付款户名，⽬前⽤不到，请填固定假值或是留空
        $params['accountBank'] = '';#付款⽅银⾏，仅供银⾏卡对卡使⽤(C2C_DIRECT)。银⾏代码请参考附件的银⾏列表JSON。可填空值。
        $params['notifyUrl'] = $pay_arr['notify_url'];#此笔订单的回调地址。请参考「异步通知回调」说明
        $params['redirectUrl'] = $pay_arr['call_domain'];#此笔订单完成后的跳转地址。
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);#签名

//        $header = array(
//            'Content-Type: application/json;charset=utf-8'
//        );

        Log::write(['Submit_create',"PeacePay",$params], "payment");

        $res = PayRequest::curlS($this->pay_set['url'], $params);

        Log::write(['Return_create',"PeacePay",$res], "payment");

        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != "SUCCESS" || (!isset($res['depositUrl']) && !isset($res['depositQrUrl']) ) ) {
            throw new \Exception($res['message']??'支付信息错误', 0);
        }
        $pay_info['pay_url']= isset($res['depositQrUrl']) ? $res['depositQrUrl'] ?: $res['depositUrl'] : $res['depositUrl'];
        $pay_info['othr_number']= $res['platformOrderNo'];
        return $pay_info;
    }


    //获取转账信息

    /**
     * @throws \Exception
     */
    public function create_withdrawal($pay_arr){
        $b_code = explode('-',$pay_arr['bank_code']);
        if(isset($b_code[1])){
            $bank_codes = $b_code[0] ??'';
            $bank_name = $b_code[1] ??'';
        }else{
            $bank_codes = $this->pay_set['bank_code'] ?? $pay_arr['bank_code'];
            $bank_name = $pay_arr['bankName'];
        }

        $params['merchantCode'] = $this->pay_set['merchant_code'];#商户号
        //代收订单类型，依据会员要打款的⽅式，可⽤的类型有：
        //C2C_DIRECT: 银⾏卡对卡 (⽹关) (会员使⽤银⾏卡转⼊)
        //C2C_UPI: UPI通⽤⽀付 (会员使⽤UPI转⼊)
        $params['method'] = $this->pay_set['withdrawal_method']; #代收订单类型
        $params['signType'] = 'MD5';#签名类型
        $params['timestamp'] = date('YmdHis');#时间戳需使⽤UTC时区，格式如下：yyyyMMddHHmmss
        $params['merchantOrderNo'] = $pay_arr['order_sn'];#订单号
        $params['orderAmount'] = $pay_arr['amount'] * 100;#金额 若⾦额为₹100.23，请填⼊10023；若⾦额为₹600，请填⼊60000

        $params['accountName'] = $pay_arr['username'];#付款户名，⽬前⽤不到，请填固定假值或是留空
        $params['accountNo'] = $pay_arr['bank_number'];#付款户名，⽬前⽤不到，请填固定假值或是留空
        $params['accountBank'] = '';#付款⽅银⾏，仅供银⾏卡对卡使⽤(C2C_DIRECT)。银⾏代码请参考附件的银⾏列表JSON。可填空值。
        $params['accountBranch'] = $pay_arr['ifsc'];#C2C_DIRECT時請填收款⼈IFSC代码(必填)。C2C_UPI请填空值。
        $params['notifyUrl'] = $pay_arr['notify_url'];#此笔订单的回调地址。请参考「异步通知回调」说明

        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);#签名

//        $header = array(
//            'Content-Type: application/json;charset=utf-8'
//        );

        Log::write(['Submit_withdrawal',"PeacePay",$params], "withdrawal");

        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], $params);
        Log::write(['Return_withdrawal',"PeacePay",$res], "withdrawal");
        $res = json_decode($res,true);
        if (empty($res) || $res['code'] != "SUCCESS" ) {
            throw new \Exception($res['message']??'支付信息错误', 0);
        }
        return $res['platformOrderNo'] ?? '';
    }

    private function generateSign($data, $signKey){
        unset($data['sign']);
        ksort($data);//根据键值升序排列
        $str = implode('', $data);
        $str .= $signKey;
        return md5($str);
    }


    //异步回调支付
    public function notify($data){
        $sign = $data['sign']??'';
        unset($data['sign']);//不参与签名
        $sign_str =$this->generateSign($data, $this->pay_set['sign_key']);
        if($sign != $sign_str){
            return "sign error";
        }
        if( $data['orderStatus'] != 'COMPLETED' ){
            return  'fail';
        }

        $rsps['order_sn'] = $data['merchantOrderNo'];
        $rsps['trade_sn'] = $data['platformOrderNo'];
        $rsps['result'] = "SUCCESS";
        $rsps['money'] = $data['actualAmount'] ? $data['actualAmount'] / 100 : 0;
        return $rsps;
    }


    //异步回调支付fmpay
    public function notify_withdrawal($data){
        $sign = $data['sign'] ?? '';
        unset($data['sign']);//不参与签名
        $sign_str =$this->generateSign($data, $this->pay_set['sign_key']);
        if($sign != $sign_str){
            return "sign error";
        }

        //PENDING 排队中 PROCESSING 处理中 COMPLETED 完成 ERROR 错误 TIMEOUT 超时 CANCELED 取消 REJECTED 拒绝
        if( in_array($data['orderStatus'], ['PENDING', 'PROCESSING', 'TIMEOUT']) ){
            return "fail";
        }

        $rsps = [];
        $rsps['status'] = 1;
        if ($data['orderStatus'] != 'COMPLETED') {
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $data['merchantOrderNo'];
        $rsps['othr_number'] = $data['platformOrderNo'];
        $rsps['msg'] = $data['message'] ?? '';
        $rsps['result'] = "SUCCESS";
        return $rsps;
    }

}