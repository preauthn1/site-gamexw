<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class OlgPay
{
    private $Secret_Keys = "";
    private $Publishable_Keys = "";
    private $url = '';
    private $withdrawal_url = '';
    private $mch_wallet = '';
    private $mch_fiat = '';
    private $country = '';
    private $domain = "";

    public function __construct($pay_set, $domain)
    {
        $this->Secret_Keys = $pay_set['Secret_Keys'];
        $this->Publishable_Keys = $pay_set['Publishable_Keys'];
        $this->url = $pay_set['url'];
        $this->withdrawal_url = $pay_set['withdrawal_url'];
        $this->mch_wallet = $pay_set['mch_wallet'];
        $this->country = $pay_set['country'];
        $this->mch_fiat = $pay_set['mch_fiat'] ??'USD';
        $this->domain = $domain;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mch_transferId'] = $pay_arr['order_sn'];//订单号
        $params['mch_amount'] = intval($pay_arr['apply_price']); // 订单金额
        $params['mch_fiat'] = $this->mch_fiat;//商家平台支付货币
        $params['mch_Callback'] =  $this->domain.'/notify/OlgPay';// 异步通知地址
        $params['mch_wallet'] = $this->mch_wallet;//商户钱包地址
        $params['mch_userid'] = $pay_arr['user_id'];//用户id
        $params['mch_surl'] = $pay_arr['call_domain'];//重定向
        $params['mch_furl'] = $pay_arr['call_domain'];//失败重定向
        $sign_data = [
            'mch_amount'=>$params['mch_amount'],
            'mch_transferId'=>$params['mch_transferId'],
            'mch_wallet'=>$params['mch_wallet'],
            'mch_userid'=>$params['mch_userid'],
            'mch_surl'=>$params['mch_surl'],
            'mch_furl'=>$params['mch_furl'],
        ];
        $params['mch_sign'] = $this->generateSign($sign_data, $this->Secret_Keys);
        Log::write(['Submit_create', "OlgPay", $params], "payment");
        $req = json_encode($params);
        $header = array(
            'Authorization: ' . $this->Publishable_Keys,
            'Content-Type: application/json',
            'cache-control: no-cache',
        );
        $res = PayRequest::curlS($this->url, $req,$header);
        Log::write(['Return_create', "OlgPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['status'] != '200') {
            throw new \Exception('支付信息错误', 0);
        }
        if (!isset($res['url']) || !isset($res['status'])) {
            throw new \Exception('支付信息错误', 0);
        }

        $sHtml = "<form id='paysubmit' name='paysubmit' action='" . $res['url'] . "' method='post'>";
        foreach ($res as $key => $val) {
            if($key != 'url' && $key != 'status'){
                $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
            }
        }
        $sHtml .= "</form>";
        $sHtml .= "<script>document.forms['paysubmit'].submit();</script>";

        $pay_info['info']=$params;
        $pay_info['form']=$sHtml;
        $pay_info['gateway']=$res['url'];
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr){
        $params['transferId'] = $pay_arr['order_sn']; // 商户订单号
        $params['country'] = $this->country;// 国家代码
        $params['beneficiaryName'] = $pay_arr['account_name'];// 收款人姓名
        $params['swiftBic'] = $pay_arr['bank_code'];// 银行代码
        $params['accountNumber'] = $pay_arr['bank_number'];// 银行账号
        $params['beneficiaryAddress'] = $pay_arr['address'];// 收款人地址
        $params['destAmount'] = intval($pay_arr['amount']); // 订单金额
        $params['payoutCountry'] = 'EU'; // 付款目的国家标识
        $params['mch_Callback'] = $this->domain.'/withdrawal/notify/OlgPay';// 异步通知地址
        $sign_data = [
            'transferId'=>$params['transferId'],
            'destAmount'=>$params['destAmount']
        ];
        $params['Signature'] = $this->generateSign($sign_data, $this->Secret_Keys);
        Log::write(['Submit_withdrawal',"OlgPay",$params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Authorization: ' . $this->Publishable_Keys,
            'Content-Type: application/json',
            'cache-control: no-cache',
        );
        $res=PayRequest::curlS($this->withdrawal_url,$req,$header);
        Log::write(['Return_withdrawal',"OlgPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if(isset($res['Error'])){
            throw new \Exception($res['Error'] ??'代付信息错误', 0);
        }
        if(empty($res) || $res['status'] != '200'){
            throw new \Exception($res['message'] ??'代付信息错误', 0);
        }
        return '';
    }


    private function generateSign($data, $signKey)
    {
        $str = 'apiKey='.$this->Publishable_Keys.'&';
        foreach ($data as $key => $value) {
            if ('mch_sign' != $key && '' != $value && 'mch_fiat' != $key) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str = trim($str,'&');
        return base64_encode(hash_hmac("sha256", $str, $signKey));
    }


    //异步回调支付
    public function notify($data)
    {
        $sign = isset($data['mch_sign']) ? $data['mch_sign'] : '';
        unset($data['mch_sign']);
        if ($data['mch_status'] != '1') {
            return "faile";
        }
        $sign_data = [
            'mch_amount'=>$data['mch_amount'],
            'mch_transferId'=>$data['mch_transferId'],
            'mch_userid'=>$data['mch_userid'],
        ];

        $sign_str = $this->generateSign($sign_data, $this->Secret_Keys);
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['mch_transferId'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = ['isSuccess'=>true];
        $rsps['money'] = $data['mch_amount'] ??0;
        return $rsps;
    }

    //异步回调支付
    public function notify_withdrawal($data){
        $sign = isset($data['mch_sign'])?$data['mch_sign']:'';
        unset($data['mch_sign']);
        $rsps['status'] = 1;
        if ($data['mch_status'] != '1') {
            $rsps['status'] = 2;
        }

        $sign_data = [
            'mch_amount'=>$data['mch_amount'],
            'mch_transferId'=>$data['mch_transferId'],
            'mch_userid'=>$data['mch_userid'],
        ];

        $sign_str =$this->generateSign($sign_data, $this->sign_key);
        if($sign_str != $sign){
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['mch_transferId'];
        $rsps['othr_number'] = '';
        $rsps['msg'] = '';
        $rsps['result'] = ['isSuccess'=>1];
        return $rsps;
    }
}

