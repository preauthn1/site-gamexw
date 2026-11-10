<?php
namespace app\common\payment;
use app\common\FastGame\Base\Utils;
use app\common\payment\PayRequest;
use think\facade\Log;
use think\facade\Request;

//HkpPay
class HkpPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params = [];
        $params['merchantId'] = $this->pay_set['merchantId'];//商户ID
        $params['merchantOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = intval($pay_arr['apply_price'] * 100);//交易金额,单位分
        $params['payType'] = $this->pay_set['payType'];//产品编码
        $params['currency'] = $this->pay_set['currency'];//币种
        $params['content'] = 'deposit';//订单内容
        if( isset($this->pay_set['bankCode']) ){
            $params['bankCode'] = $this->pay_set['bankCode'];//银行编码, 越南网银, 印尼网银 支付方式可以支持的银行编码
        }
        $Request = Request::param(false);
        if(isset($Request['pay_cpf']) && empty($Request['pay_cpf'])){
            throw new \Exception('CPF不得为空', 0);
        }
        if(isset($Request['pay_name']) && empty($Request['pay_name'])){
            throw new \Exception('名称不得为空', 0);
        }
        //巴西是否i传cpf和名字
        if( isset($Request['pay_cpf']) ){
            $params['kycPayerIdNo'] = $Request['pay_cpf'];//KYC付款人ID, 巴西个人传CPF(纯数字), 巴西公司传CNPJ(纯数字)
        }
        if( isset($Request['pay_name']) ){
            $params['kycPayerName'] = $Request['pay_name'];//KYC付款人名称, 巴西个人传个人姓名, 巴西公司传公司名称
        }
        $params['clientIp'] = Utils::get_ip(); // 用户Ip
        $params['callback'] = $pay_arr['notify_url'];//异步通知
        $params['redirect'] = $pay_arr['call_domain'];//页面跳转地址


        $params['sign'] = $this->generateSignature($params, $this->pay_set['private_key']);

        $header = array(
            'Content-type: application/x-www-form-urlencoded',
        );

        Log::write(['Submit_create', "HkpPay", $params], "payment");
        $res = PayRequest::curlS($this->pay_set['urls'], http_build_query($params), $header);
        Log::write(['Return_create', "HkpPay", $res], "payment");
        $res = json_decode($res, true);

        if (empty($res) || !isset($res['success']) || !$res['success'] ) {
            throw new \Exception($res['message'] ??lang('支付信息错误'), 0);
        }
        if (!isset($res['data']['payUrl'])) {
            throw new \Exception($res['message'] ?? lang('支付信息错误'), 0);
        }
        $pay_info['pay_url'] = $res['data']['payUrl'];
        $pay_info['othr_number'] = $res['data']['orderNo'];
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
        $bank_name = strtoupper($bank_name);

        $params = [];
        $params['merchantId'] = $this->pay_set['merchantId'];//商户ID
        $params['merchantOrderNo'] = $pay_arr['order_sn']; // 商户订单号
        $params['amount'] = intval($pay_arr['amount'] * 100);//交易金额,单位分
        $params['currency'] = $this->pay_set['currency'];//币种

        $params['accountName'] = $pay_arr['account_name'];//账户名
        $params['accountMobile'] = $pay_arr['phone'] ??'0815648636';//手机号
        $params['accountEmail'] = $pay_arr['mail'] ?? $pay_arr['username']."@gmail.com";//客户邮箱
        if( $params['currency'] == 'BRL' ){
            //巴西才有下面参数
            $params['accountType'] = 'PIX_' . $bank_name;//账户类型
            $params['accountNo'] = $bank_name == 'PHONE' ? '+55'. $pay_arr['bank_number'] : $pay_arr['bank_number'];//账户
            $params['cpf'] = $pay_arr['bank_number'];//账户名
        }
        $params['callback'] = $pay_arr['notify_url'];//异步通知地址

        $params['sign'] = $this->generateSignature($params, $this->pay_set['private_key']);

        $header = array(
            'Content-type: application/x-www-form-urlencoded',
        );
        Log::write(['Submit_withdrawal', "HkpPay", $params], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], http_build_query($params), $header);
        Log::write(['Return_withdrawal', "HkpPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || !isset($res['success']) || !$res['success'] || !isset($res['data']['orderNo'])) {
            throw new \Exception(lang("渠道返回:") . $res['message'] ?? lang('支付信息错误'), 0);
        }
        return $res['data']['orderNo'];
    }

    //生成签名
    private function generateSignature($params, $signKey) {
        unset($params['sign']);
        // 将参数按照 ASCII 码从小到大排序
        ksort($params);
        // 拼接参数成 URL 键值对格式的字符串
        $paramStr = '';
        foreach ($params as $key => $value) {
            $paramStr .= $key . '=' . $value . '&';
        }
        // 去掉末尾的 '&' 字符
        $paramStr = rtrim($paramStr, '&');
        // 在字符串末尾拼接商户密钥
        $paramStr = $paramStr . '&secret=' . $signKey;
        // 对字符串进行 MD5 计算，并转换为小写表示
        return strtolower(md5($paramStr));
    }

    //异步回调支付
    public function notify($data)
    {
        if ( $this->generateSignature($data, $this->pay_set['private_key']) != $data['sign'] ) {
            return "sign error";
        }
        //代收两个状态。支付中 ，支付成功对方完成转账
        //WAITING_PAY	待支付
        //PAYING	支付中
        //PAID	支付成功
        //PAY_FAILED	支付失败
        //REFUND	已退款
        if ($data['status'] != 'PAID') {
            return 'fail';
        }
        $rsps['order_sn'] = $data['merchantOrderNo'];
        $rsps['trade_sn'] = $data['orderNo'];
        $rsps['result'] = 'success';
        $rsps['money'] = $data['amount'] / 100;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        if ( $this->generateSignature($data, $this->pay_set['private_key']) != $data['sign'] ) {
            return "sign error";
        }
        //代收两个状态。支付中 ，支付成功对方完成转账
        //WAITING_PAY	待支付
        //PAYING	支付中
        //PAID	支付成功
        //PAY_FAILED	支付失败
        //REFUND	已退款
        //不是成功或失败的返回不处理
        if ( !in_array($data['status'], ['PAID', 'PAY_FAILED'])) {
            return 'fail';
        }

        $rsps['status'] = 1;
        if ( $data['status'] != 'PAID' ) {
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $data['merchantOrderNo'];
        $rsps['othr_number'] = $data['orderNo'];
        $rsps['msg'] = $oldData['errorMsg'] ??'';
        $rsps['result'] = 'success';
        return $rsps;
    }
}