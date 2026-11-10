<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class IndiaPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['McorderNo'] = $pay_arr['order_sn']; //
        $params['Amount'] =  intval($pay_arr['apply_price']); // 订单金额 两位小数点
        $params['Type'] = $this->pay_set['currency']; //货币类型
        $params['ChannelCode'] = $this->pay_set['channel_code']; //通道代码
        $params['CallBackUrl'] = $pay_arr['notify_url']; // 异步通知地址
        $params['JumpUrl'] = $pay_arr['call_domain']; // 重定向
        $t = time();
        $n = $this->getNonce(6);
        $url = '/api/order/create';
        $sign_arr = [
            'method' => 'POST',
            'url' => $url,
            'time' => $t,
            'nonce' => $n,
            'access_key' => $this->pay_set['access_key'],
        ];
        $sign = $this->generateSign($sign_arr);

        $header = array(
            'Content-Type: application/json;charset=utf-8',
            'accessKey:' . $this->pay_set['access_key'],
            'timestamp:' . $t,
            'nonce:' . $n,
            'sign:' . $sign,
        );
        $req = json_encode($params);
        $url = $this->pay_set['link'] . $url;
        Log::write(['Submit_create', "IndiaPay", $url, $sign_arr, $header], "payment");
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_create', "IndiaPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != 200) {
            throw new \Exception($res['msg'] ?? '支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['result']['payUrl'];
        return $pay_info;
    }


    //获取转账信息
    public function create_withdrawal($pay_arr)
    {
        $params['McorderNo'] = $pay_arr['order_sn']; //订单号
        $params['Amount'] = intval($pay_arr['amount']);
        $params['Type'] = $this->pay_set['currency']; //货币类型
        $params['ChannelCode'] = $this->pay_set['channel_code']; //通道代码
        $params['BankAccount'] = $pay_arr['bank_number']; //银行卡号
        $params['Ifsc'] = $pay_arr['ifsc']; //IFSC
        $params['BankName'] = $pay_arr['bankName']; //银行名称
        $params['name'] = $pay_arr['account_name']; //收款人姓名
        $params['NotifyUrl'] = $pay_arr['notify_url']; // 异步通知地址

        $t = time();
        $n = $this->getNonce(6);
        $url = '/api/payorder/create';
        $sign_arr = [
            'method' => 'POST',
            'url' => $url,
            'time' => $t,
            'nonce' => $n,
            'access_key' => $this->pay_set['access_key'],
        ];
        $sign = $this->generateSign($sign_arr);

        $header = array(
            'Content-Type: application/json;charset=utf-8',
            'accessKey:' . $this->pay_set['access_key'],
            'timestamp:' . $t,
            'nonce:' . $n,
            'sign:' . $sign,
        );
        $req = json_encode($params);
        $url = $this->pay_set['link'] . $url;
        Log::write(['create_withdrawal', "IndiaPay", $url, $req, $header], "withdrawal");
        $res = PayRequest::curlS($url, $req, $header);
        Log::write(['Return_withdrawal', "IndiaPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) ||  $res['code'] != 200) {
            throw new \Exception("渠道返回:" . $res['message'], 0);
        }
        return $res['result']['merchantOrder'] ?? '';
    }

    private function generateSign($data)
    {
        $str = "{$data['method']}&{$data['url']}&{$data['access_key']}&{$data['time']}&{$data['nonce']}";
//        Log::write(['generateSign', "IndiaPay", $str], "payment");
        return base64_encode(hash_hmac("sha256", $str, $this->pay_set['app_secret'],true));
    }


    //异步回调支付
    public function notify($data)
    {
        $header = request()->header();
        $sign = $header['sign'] ?? '';
        if ($data['status'] != 'success') {
            return "faile";
        }
        if($this->pay_set['access_key'] != $header['accesskey']){
            return "faile";
        }

        $sign_arr = [
            'method' => 'POST',
            'url' => $_SERVER['REQUEST_URI'],
            'time' => $header['timestamp'],
            'nonce' => $header['nonce'],
            'access_key' => $this->pay_set['access_key'],
        ];
        $sign_str = $this->generateSign($sign_arr);
//        Log::write(['generateSign', "IndiaPay", $sign_arr, $sign_str, $sign], "payment");
        if ($sign_str != $sign) {
            return "sign error";
        }
        $rsps['order_sn'] = $data['merchantorder'];
        $rsps['trade_sn'] = $data['orderno'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ?: 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $rsps['status'] = 1;
        $header = request()->header();
        $sign = $header['sign'] ?? '';
        if ($data['status'] != 'success') {
            $rsps['status'] = 2;
        }
        if($this->pay_set['access_key'] != $header['accesskey']){
            $rsps['status'] = 2;
        }
        $sign_arr = [
            'method' => 'POST',
            'url' => $_SERVER['REQUEST_URI'],
            'time' => $header['timestamp'],
            'nonce' => $header['nonce'],
            'access_key' => $this->pay_set['access_key'],
        ];
        $sign_str = $this->generateSign($sign_arr);
        if ($sign_str != $sign) {
            $rsps['status'] = 2;
        }
        $rsps['order_sn'] = $data['merchantorder'];
        $rsps['othr_number'] = $data['orderno'];
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }

    /* 生成随机数*/
    private function getNonce($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }

        return $str;
    }


    public function get_balance($type){
        $t = time();
        $n = $this->getNonce(6);
        $url = '/api/merchant/Balance';
        $sign_arr = [
            'method' => 'GET',
            'url' => $url,
            'time' => $t,
            'nonce' => $n,
            'access_key' => $this->pay_set['access_key'],
        ];
        $sign = $this->generateSign($sign_arr);
        $header = array(
            'Content-Type: application/json;charset=utf-8',
            'accessKey:' . $this->pay_set['access_key'],
            'timestamp:' . $t,
            'nonce:' . $n,
            'sign:' . $sign,
        );
        $url = $this->pay_set['link'] . $url;
        Log::write(['get_balance_create', "IndiaPay", $url], "payment");
        $res = PayRequest::curl_get($url, [], $header);
        Log::write(['get_balance_return', "IndiaPay", $res], "payment");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != '200') {
            if($type == 1){
                //抛出错误
                throw new \Exception('支付信息错误', 0);
            }else{
                return  'fail';
            }
        }
        return $res['result'][0]['balance'] ?? '';
    }
}
