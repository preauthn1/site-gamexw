<?php
namespace app\common\payment;
use app\common\model\PaySetModel;
use app\common\model\UserVerifyModel;
use app\common\payment\PayRequest;
use app\common\validate\UserValidate;
use app\manage\controller\PaySet;
use think\facade\Log;

class YoXiPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
//         $this->get_bank();
        $params['code'] = strtolower($this->pay_set['code']); //商户号
        $params['order_no'] = $pay_arr['order_sn']; //订单号
        $params['amount'] =  intval($pay_arr['apply_price']); // 订单金额
//        $params['deposit_name'] = $pay_arr['username']; //用户名
        $nick_name = '';
        $w = [];
        $w[] = ['uid', '=', $pay_arr['uid']];
        $w[] = ['status', '=', 2];
        $UserContent = UserVerifyModel::where($w)->value('content');
        if(empty($UserContent)){
            throw new \Exception('请先完成实名认证', 0);
        }else{
            $user_content = json_decode($UserContent,true);
            if(count($user_content) > 0){
                foreach ($user_content as $item) {
                    if($item['key'] == 'Nickname'){
                        $nick_name = $item['value'];
                    }
                }
            }
        }
        if(empty($nick_name)){
            throw new \Exception('实名认证数据不全', 0);
        }
        $params['deposit_name'] = $nick_name; //用户名
        $sign = $this->generateSign($params);
        $params['sign'] = $sign;
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址

        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['link'] . '/api/add/receive';
        Log::write(['Submit_create', "YoXiPay", $url, $params], "payment");
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_create', "YoXiPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != '200') {
            $msg = lang('目前無法使用 錯誤代碼:') . $res['rtn_code'];
            throw new \Exception($msg, 0);
//            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['payment_url'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
//        PaySetModel::where($w)->
        $params['order_no'] = $pay_arr['order_sn']; //订单号
        $params['amount'] = intval($pay_arr['amount']); // 订单金额

        $params['bank_account'] = $pay_arr['bank_number']; //银行账户号码
        $params['bank_code'] = $pay_arr['bank_code']; //銀行代碼
        $params['bank_account_name'] = $pay_arr['account_name']; //银行账户名称
        $params['bank_branch'] = $pay_arr['branches']; //银行分行名称
        $params['bank_branch_code'] = $pay_arr['branches_code']; //銀行分行代碼
        $params['code'] = strtolower($this->pay_set['code']); //商户号
        $sign = $this->generateSign($params);
        $params['customer_bank_name'] = $pay_arr['bankName']; //客户银行名称
//        $params['customer_bank_code'] = $pay_arr['bank_code']; //客户银行代码
        $params['notify_url'] = $pay_arr['notify_url']; // 异步通知地址
        $params['sign'] = $sign;

        Log::write(['withdrawal_create', "YoXiPay", $params], "withdrawal");
        $req = json_encode($params);
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        $url = $this->pay_set['link'] . '/api/add/withdraw';
        $res = PayRequest::curlS($url, $req,$header);
        Log::write(['Return_withdrawal', "YoXiPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['status'] != 0) {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['order_no'] ??'';
    }

    private function generateSign($data)
    {
        $data = array_filter($data);
        $data['api_key'] = $this->pay_set['api_key'];
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if($value != '' && $key != 'sign'){
                $str .= $key . '=' . $value . '&';
            }
        }
        $str = rtrim($str, '&');
        Log::write(['SignStr', "YoXiPay", $str], "payment");
        return md5($str);
    }


    //异步回调支付
    public function notify($data)
    {
        if (!isset($data['order_status']) || $data['order_status'] != '1') {
            return "faile";
        }
        $sign_str = $this->generateSign($data);
        if ($sign_str != $data['sign']) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['order_no'];
        $rsps['trade_sn'] = '';
        $rsps['result'] = ['status' => 1];
//        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ? $data['amount']:0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
//        $sign = isset($data['sign']) ? $data['sign'] : '';
//        $data_decode = json_decode(urldecode($data['transdata']), true);
//        if ($data_decode['resp_code'] != 'S') {
//            $rsps['status'] = 2;
//        }
//        $sign_str = $this->generateSign($data_decode, $this->pay_set['sign_key']);
//        if ($sign_str != $sign) {
//            return "sign error";
//        }
        if (!isset($data['order_status']) || $data['order_status'] != '1') {
            $rsps['status'] = 2;
        }
        $sign_str = $this->generateSign($data);
        if ($sign_str != $data['sign']) {
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $data['order_no'];
        $rsps['othr_number'] = $data['payment_code'];
        $rsps['msg'] = '';
        $rsps['result'] = ['status' => 1];
        return $rsps;
    }

    public function get_bank(){
        $url = $this->pay_set['link'] . '/api/bank/list';
        Log::write(['Submit_create', "YoXiPay", $url], "payment");
        $header = array(
            'Content-Type: application/json;charset=utf-8'
        );
        $res = PayRequest::curl_get($url, [], $header);
        $res = json_decode($res, true);
        Log::write(['Return_create', "YoXiPay", $res], "payment");
        if (empty($res) ||  $res['status'] != '200') {
            throw new \Exception($res['msg'] ??'支付信息错误', 0);
        }
    }
}
