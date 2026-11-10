<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class RoyalPay
{
    private $pay_set = [];

    public function __construct($pay_set, $domain)
    {
        $this->pay_set = $pay_set;
    }

    //获取支付信息
    public function create($pay_arr)
    {
        $params['mchNo'] = $this->pay_set['mchNo'];//商户号
        $params['appId'] = $this->pay_set['appId'];//appId
        $params['mchOrderNo'] = $pay_arr['order_sn'];//订单编号
        $params['amount'] = $pay_arr['apply_price'] * 100;//金额，单位分
        $params['customerEmail'] = $pay_arr['mail'] ??$pay_arr['username']."@gmail.com";//客户邮箱
        $params['customerName'] = $pay_arr['username'];//客户名字
        $params['customerPhone'] = $pay_arr['phone'] ?: '0815648636';//客户手机号
        $params['notifyUrl'] = $pay_arr['notify_url'];//回调地址（可空，如果为空已后台设置为准）
        $params['extParam'] = 'payment';//商户扩展参数 回调时原样返回
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);

        $header = array(
            'Content-Type: application/json;'
        );
        Log::write(['Submit_create', "RoyalPay", $params,$header], "payment");
        $res = PayRequest::curlS($this->pay_set['url'], json_encode($params),$header);
        Log::write(['Return_create', "RoyalPay", $res], "payment");
        $res = json_decode($res, true);

        if (empty($res) || $res['code'] != 0 || !isset($res['data']['payData'])) {
            throw new \Exception($res['data']['errMsg'] ??'支付信息错误', 0);
        }
        $pay_info['pay_url'] = $res['data']['payData'];
        $pay_info['othr_number'] = $res['data']['payOrderId'];
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
        $params['mchNo'] = $this->pay_set['mchNo'];//商户号
        $params['appId'] = $this->pay_set['appId'];//appId
        $params['mchOrderNo'] = $pay_arr['order_sn'];//订单编号
        $params['amount'] = $pay_arr['amount'] * 100;//金额 单位分
        $params['entryType'] = 'IMPS';//固定值 IMPS
        $params['accountNo'] = $pay_arr['bank_number'];//收款账号或银行卡号
        $params['accountCode'] = $pay_arr['ifsc'] ?: $bank_codes;//银行编码 当 entryType 字段为 IMPS 时，该字段为收款人分行的 IFSC 代码。
        $params['bankName'] = $bank_name;//收款账号开户行名称
        $params['accountName'] = $pay_arr['account_name']?? 'username';//收款人姓名
        $params['accountEmail'] = $pay_arr['mail'] ??$pay_arr['username']."@gmail.com"; //收款人邮箱
        $params['accountPhone'] = $pay_arr['phone'] ?: '0815648636'; //收款人手机号
        $params['notifyUrl'] = $pay_arr['notify_url'];// 异步通知地址
        $params['transferDesc'] = 'withdrawal';// 备注
        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);

        $header = array(
            'Content-Type: application/json;'
        );

        Log::write(['Submit_withdrawal', "RoyalPay", $params,$header], "withdrawal");
        $res = PayRequest::curlS($this->pay_set['withdrawal_url'], json_encode($params),$header);
        Log::write(['Return_withdrawal', "RoyalPay", $res], "withdrawal");
        $res = json_decode($res, true);
        if (empty($res) || $res['code'] != 0 || !isset($res['data']['transferId'])) {
            throw new \Exception($res['msg'] ?? ($res['data']['errMsg'] ?? '支付信息错误'), 0);
        }
        return $res['data']['transferId'] ?: '';
    }

    //签名
    private function generateSign($data, $signKey) {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' !== $value) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str .= 'key='.$signKey;
        return strtoupper(md5($str));
    }

    //异步回调支付
    public function notify($data)
    {
        $sign = $data['sign'] ?: '';
        if( $sign != $this->generateSign($data, $this->pay_set['sign_key']) ){
            return "sign error";
        }
        //状态 2-成功 3-失败
        if ($data['state'] != 2) {#其他状态直接返回告知三方成功，只处理支付成功状态
            return "success";
        }
        $rsps['order_sn'] = $data['mchOrderNo'];
        $rsps['trade_sn'] = $data['payOrderId'];
        $rsps['result'] = "success";
        $rsps['money'] = $data['amount'] ? $data['amount'] / 100 : 0;
        return $rsps;
    }


    //异步回调支付
    public function notify_withdrawal($data)
    {
        $sign = $data['sign'] ?: '';
        if( $sign != $this->generateSign($data, $this->pay_set['sign_key']) ){
            return "sign error";
        }
        $rsps['status'] = 1;
        if ($data['state'] != 2) {
            $rsps['status'] = 2;
        }

        $rsps['order_sn'] = $data['mchOrderNo'];
        $rsps['othr_number'] = $data['transferId'];
        $rsps['msg'] = $data['errMsg'] ?? '';
        $rsps['result'] = "success";
        return $rsps;
    }

    //获取三方代收余额
//    public function get_balance($type){
//        $params['mchNo'] = $this->pay_set['mchNo'];//商户号
//        $params['appId'] = $this->pay_set['appId'];//appId
//        $params['sign'] = $this->generateSign($params, $this->pay_set['sign_key']);
//        Log::write(['get_balance_create',"RoyalPay",$params], "payment");
//        $header = array(
//            'Content-Type: application/json;'
//        );
//        $res = PayRequest::curlS($this->pay_set['balance_url'], json_encode($params), $header);
//        $res = json_decode($res, true);
//        Log::write(['get_balance_return',"RoyalPay",$res], "payment");
//        if (empty($res) || $res['code'] != 0) {
//            if($type == 1){
//                //抛出错误
//                throw new \Exception('支付信息错误', 0);
//            }else{
//                return  'fail';
//            }
//        }
//        return $res['data']['balance'] ?? '';
//    }
}