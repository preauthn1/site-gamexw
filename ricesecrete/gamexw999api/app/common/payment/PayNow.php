<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class PayNow
{	
    private $Seller="";
    private $Authorization="";
    private $key="";
    private $domain="";
    private $payin_url="";
    private $payout_url="";
    private $upi_url="";

    public function __construct($pay_set,$domain){
        $this->Seller=$pay_set['seller'];
        $this->key=$pay_set['pay_key'];
        $this->Authorization="Basic ".$pay_set['pay_key'];
        if (isset($pay_set['test'])){
            $this->payin_url="https://stage.paynow8.com/payinapi/payin/deposit";
            $this->payout_url="https://stage.paynow8.com/payoutapi/Payout/Withdrawal";
            $this->upi_url="https://stage.paynow8.com/qrpayapi/BankBot/QRScanDeposit";
        }else{
            $this->payin_url="https://api.paynow8.com/payin/deposit";
            $this->payout_url="https://payoutapi.paynow8.com/Payout/Withdrawal";
            $this->upi_url="https://qrpayinapi.paynow8.com/BankBot/QRScanDeposit";;
        }
        $this->domain=$domain;
    }

    /**创建支付
     * pay_arr
    */ 
    public function create($pay_arr){
        $req['Seller']=$this->Seller;//商户号
        $req['Amount']=$pay_arr['apply_price'];//金额
        $req['Currency']="INR";//货币
        $req['ItemID']=$pay_arr['order_sn'];//商户单号(独特&不能重复使用)
        $req['ItemDescription']="football";//交易说明
        $req['ClientName']=$pay_arr['username'];//名字
        $req['ReturnURL']=$pay_arr['call_domain'];//交易成功后返回的页面
        $req['FailedReturnURL']=$pay_arr['call_domain'];//交易失败后返回的页面
        $req['HTTPPostURL']=$this->domain."/notify/PayNow";//回调网址
        Log::write(['Submit_create',"PayNow",$req], "payment");
        $header=array(
            "Authorization: ".$this->Authorization,
            "Content-Type: application/x-www-form-urlencoded",
        );
        $res=PayRequest::curlS($this->payin_url,$req,$header);
        Log::write(['Return_create',"PayNow",$res], "payment");
        $res=json_decode($res,true);
        if (!isset($res['redirect_to'])) {
            throw new \Exception("支付信息错误", 0);
        }
        $pay_info['pay_url']=str_replace('redirectlink=','',$res['redirect_to']);
        return $pay_info;
    }

    public function create_withdrawal($pay_arr){
        $req['merchant_code']=$this->Seller;//商户号
        $req['ref_id']=$pay_arr['order_sn'];//商户单号(独特&不能重复使用)
        $req['player_username']=$pay_arr['user_id'];//名字
        $req['player_ip']=$pay_arr['pay_ip'];//IP
        $req['currency_code']="INR";//货币
        $req['amount']=$pay_arr['amount'];//金额
        $req['ifsc']=$pay_arr['ifsc'];//
        $req['bank_code']=$pay_arr['bank_code'];//银行代码
        $req['beneficiary_account']=$pay_arr['bank_number'];//银行账号
        $req['beneficiary_name']=$pay_arr['account_name'];//收款人姓名
        
        //加密
        // 带有SHA256的hash_hmac() 配合"密钥"进行加密 ‘商户号 + 商户单号 + 名字 + IP + 货币 + 金额 + 银行代码 + 收款人银行号码 + 收款人名字’
        $sign_str = $req['merchant_code'].$req['ref_id'].$req['player_username'].$req['player_ip'].$req['currency_code'].$req['amount'].$req['bank_code'].$req['beneficiary_account'].$req['beneficiary_name'];
        $req['hash']= hash_hmac("sha256", $sign_str, $this->key);
        Log::write(['Submit_withdrawal',"PayNow",$req], "withdrawal");
        $req=json_encode($req);
        $header=array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($req)
        );
        $res=PayRequest::curlS($this->payout_url,$req,$header);
        Log::write(['Return_withdrawal',"PayNow",$res], "withdrawal");
        $res=json_decode($res,true);
        if (!isset($res['invoice_number'])) {
            throw new \Exception($res['message']??'代付信息错误', 0);
        }else{
            return $res['invoice_number'];
        }
    }

    //异步回调
    public function notify($post_data){
        $notify_data['contract'] = isset($post_data['contract'])?$post_data['contract']:'';//独特标识符
        $notify_data['transaction'] = isset($post_data['transaction'])?$post_data['transaction']:'';//三方单号
        $notify_data['status'] = isset($post_data['status'])?$post_data['status']:'';//[1 (Success/Accepted 成功), 2 (Rejected 失败), 3 (Approved 手动批准)]
        $notify_data['status_message'] = isset($post_data['status_message'])?$post_data['status_message']:'';//[“Success”, “Accepted”, “Rejected”, “ERROR”]
        $notify_data['ItemID'] = isset($post_data['ItemID'])?$post_data['ItemID']:'';//商户单号
        $notify_data['ItemDescription'] = isset($post_data['ItemDescription'])?$post_data['ItemDescription']:'';//商户提供交易说明
        $notify_data['Amount'] = isset($post_data['Amount'])?$post_data['Amount']:'';//金额
        $notify_data['Currency'] = isset($post_data['Currency'])?$post_data['Currency']:'';//交易货币
        $notify_data['ClientName'] = isset($post_data['ClientName'])?$post_data['ClientName']:'';//名字
        $notify_data['created_at'] = isset($post_data['created_at'])?$post_data['created_at']:'';//创建日期/时间
        $notify_data['updated_at'] = isset($post_data['updated_at'])?$post_data['updated_at']:'';//最新更新日期/时间
        $notify_sign = isset($post_data['signature2'])?$post_data['signature2']:'';//带有SHA256的hash_hmac() 加密 ‘三方单号 + 状态代码 + 货币 + 金额 + 创建于’ 配合商户密钥

        // 带有SHA256的hash_hmac() 加密 ‘三方单号 + 状态代码 + 货币 + 金额 + 创建于’ 配合商户密钥
        $sign_str = $notify_data['transaction'].$notify_data['status'].$notify_data['Currency'].$notify_data['Amount'].$notify_data['created_at'];
        $sign_str = hash_hmac("sha256", $sign_str, $this->key);

        if($sign_str != $notify_sign){
            return "sign error";
        }
        if ($notify_data['status']==2) {
                return "status error";
        }
        
        $rsps['order_sn'] = $notify_data['ItemID'];
        $rsps['trade_sn'] = $notify_data['transaction'];
        $rsps['result'] = "success";
        return $rsps;
    }

    //提现异步回调
    public function notify_withdrawal($notify_res){
        $notify_data['InvoiceNo'] = isset($notify_res['InvoiceNo'])?$notify_res['InvoiceNo']:'';//三方单号
        $notify_data['BeneficiaryAccount'] = isset($notify_res['BeneficiaryAccount'])?$notify_res['BeneficiaryAccount']:'';//收款人银行号码
        $notify_data['BeneficiaryBankCode'] = isset($notify_res['BeneficiaryBankCode'])?$notify_res['BeneficiaryBankCode']:'';//收款人银行代码
        $notify_data['BeneficiaryName'] = isset($notify_res['BeneficiaryName'])?$notify_res['BeneficiaryName']:'';//收款人名字
        $notify_data['ItemDescription'] = isset($notify_res['ItemDescription'])?$notify_res['ItemDescription']:'';//商户提供交易说明
        $notify_data['SubmissionDate'] = isset($notify_res['SubmissionDate'])?$notify_res['SubmissionDate']:'';//提交日期/时间
        $notify_data['Status'] = isset($notify_res['Status'])?$notify_res['Status']:'';//[0, 1, 2, 3]
        $notify_data['StatusMessage'] = isset($notify_res['StatusMessage'])?$notify_res['StatusMessage']:'';//状态信息
        $notify_data['RefId'] = isset($notify_res['RefId'])?$notify_res['RefId']:'';//商户单号
        $notify_data['MerchantCode'] = isset($notify_res['MerchantCode'])?$notify_res['MerchantCode']:'';//商户号
        $notify_data['Currency'] = isset($notify_res['Currency'])?$notify_res['Currency']:'';//交易货币
        $notify_data['Amount'] = isset($notify_res['Amount'])?$notify_res['Amount']:'';//金额
        $notify_data['PaymentDate'] = isset($notify_res['PaymentDate'])?$notify_res['PaymentDate']:'';//付款日期/时间
        $notify_sign = isset($notify_res['Signature'])?$notify_res['Signature']:'';
        // 带有SHA256的hash_hmac() 配合 "密钥"加密 ‘三方单号 + 状态 + 货币 + 金额 +付款日期/时间
        
        $sign_str = $notify_data['InvoiceNo'].$notify_data['Status'].$notify_data['Currency'].$notify_data['Amount'].$notify_data['PaymentDate'];
        $sign_str = hash_hmac("sha256", $sign_str, $this->key);
        
        $rsps = [];
        $rsps['status'] = 1;
        if($sign_str != $notify_sign){
            $rsps['status'] = 2;//取消
        }
        // [0, 1, 2, 3] ["Pending", "Success", "Reject", "Manual Approve"]
        if($notify_data['Status']==0){
            $rsps['status'] = 0;//待定
        }elseif($notify_data['Status']==2){
            $rsps['status'] = 2;//取消
        }
        
        $rsps['order_sn'] = $notify_data['RefId'];
        $rsps['othr_number'] = $notify_data['InvoiceNo'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }
}