<?php
namespace app\common\payment;
use app\common\payment\PayRequest;
use think\facade\Log;

class BmsPay
{
    private $iv = '!WFNZFU_{H%M(S|a';    //IV偏移量这个是固定的不需要去管
    private $appId = '';  //商户ID
    private $key ='';  //商户的Keyd在商户后台可以获取
    private $key16 =''; //   商户秘钥的前16位
    private $domain="";
    private $payUrl="";
    private $apiUrl="";
    public function __construct($pay_set,$domain){
        $this->appId=$pay_set['appId'];
        $this->key=$pay_set['key'];
        $this->key16=$pay_set['key16'];
        if (isset($pay_set['test'])){
            $this->apiUrl='https://payapitest.soon-ex.com';
            $this->payUrl='https://paytest.soon-ex.com';
        }else{
            $this->apiUrl='https://payapi.soon-ex.com';
            $this->payUrl='https://pay.soon-ex.com';
        }
        $this->domain=$domain;
    }

    //获取支付信息
    public function create($pay_arr){
        $params['amount'] = $pay_arr['apply_price']; // 订单金额:
        $params['thirdOrderNumber'] = $pay_arr['order_sn']; //商家自己平台的订单号
        $params['thirdUserId'] =  $pay_arr['user_id']; //商家自己平台的会员ID，如果没有可以用上面的订单号
        Log::write(['Submit_create',"BmsPay",$params], "payment");
        $url=$this->apiUrl.'/otc/api/getRechargeData';

        $header = array('Content-Type: application/json', 'Authorization: Basic'.base64_encode($this->appId.':'.$this->key.''));
        $res = PayRequest::curlS($url,json_encode($params),$header);
        Log::write(['Return_create',"BmsPay",$res], "payment");
        $res=json_decode($res,true);
        if ($res['code'] != 0) {
            throw new \Exception($res['message'] ?? '支付信息错误', 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception($res['message'] ?? '支付信息错误', 0);
        }
        $data = $res['data'];
        if (!isset($data['orderNumber'])) {
            throw new \Exception($res['message'] ?? '支付信息错误', 0);
        }
        $pay_info['pay_url'] = $this->payUrl."/iran/#/?orderId=".$data['orderNumber']."&phone=".$pay_arr['phone']."&mail=".$pay_arr['user_id']."@br.con";
        return $pay_info;
    }

    //获取转账信息
    public function create_withdrawal($pay_arr){
        list($msec, $sec) = explode(' ', microtime());
        $msectime= (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000); //获取当前时间戳
        $nonce=date('Ymd').substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);  //生成12位的随机数
        $en = [
            'amount'=>$pay_arr['amount'],    // 订单金额:
            'thirdOrderNumber'=>$pay_arr['order_sn'],//uniqid(),商家自己平台的提现订单号
            'thirdUserId'=>$pay_arr['user_id'], //商家自己平台的会员ID，如果没有可以用上面的订单号
            'issuePayPo'=>[
                'accountName' => $pay_arr['bank_number'],  //pix钥匙串
                'name' => $pay_arr['account_name'],  //提现用户姓名
                'bankName'=>$pay_arr['ifsc'],//IBAN账号
                'paymentId' => $pay_arr['bank_code'] //收款方式ID这里以PIX为例,其他收款方式见文件，如果填错可能会影响订单代收
            ]
        ];
        
        $data = [
            'encryptedData'=>$this->encode(json_encode($en)),   //提现数据加密
            'signaturePo'=>[
                'apiId'=>$this->appId,
                'nonce'=>$nonce.'',
                'signature'=>'',
                'timestamp'=>$msectime.''
            ],
        ];
        
        $signature = $this->sign([   //调用签名函数进行数据签名
            $data['signaturePo']['timestamp'].'',
            $data['signaturePo']['nonce'].'',
            $data['signaturePo']['apiId'],$this->key,
            json_encode($en), //将数组转json格式的数据
        ]);
        
        $data['signaturePo']['signature'] = $signature;
        Log::write(['Submit_withdrawal',"BmsPay",$data], "withdrawal");
        $url = $this->apiUrl.'/otc/api/issue';   //提现接口，这个接口post方式以json格式提交
        $res = PayRequest::curlS($url,json_encode($data),array('Content-Type: application/json'));
        Log::write(['Return_withdrawal',"BmsPay",$res], "withdrawal");
        $res=json_decode($res,true);
        if ($res['code'] != 0) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        if (!isset($res['data'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        $data = $res['data'];
        if (!isset($data['orderNumber'])) {
            throw new \Exception($res['message'] ??'支付信息错误', 0);
        }
        return $data['orderNumber'];
    }

    //异步回调处理
    public function notify($post_data){
        $notify_data = $post_data;
        $encryptedData = isset($notify_data["encryptedData"]) ? $notify_data["encryptedData"]: "";
        if (empty($encryptedData)){
            return 'error';
        }
        $order_str = $this->decode($encryptedData);
        if (empty($order_str)){
            return 'error';
        }
        Log::write(['notify_create', "BmsPay",'解密数据', $order_str], "payment");
        $order = json_decode($order_str, true);
        if (!isset($order["orderType"]) || $order["orderType"]!=0) {
            return 'error';
        }
        if (!isset($order["status"]) || $order["status"]!=1) {
            return 'error';
        }
        $thirdOrderNumber = isset($order["thirdOrderNumber"]) ? $order["thirdOrderNumber"]: "";
        $orderNumber = isset($order["orderNumber"]) ? $order["orderNumber"]: "";
        
        $rsps['order_sn'] = $thirdOrderNumber;
        $rsps['trade_sn'] = $orderNumber;
        $rsps['result'] = "success";        
        return $rsps;
    }

    //异步回调处理
    public function notify_withdrawal($post_data){
        $notify_data = $post_data;
        $rsps = [];
        $rsps['status'] = 1;
        $orderNumber = '';
        $thirdOrderNumber = '';
        $encryptedData = isset($notify_data["encryptedData"]) ? $notify_data["encryptedData"]: "";
        if (empty($encryptedData)){
            $rsps['status']=0;
        }else{
            $order_str = $this->decode($encryptedData);
            Log::write(['notify_withdrawal', "BmsPay",'解密数据', $order_str], "withdrawal");
            if (empty($order_str)){
                $rsps['status']=0;
            }else{
                $order = json_decode($order_str, true);
                if ($order["status"]!=1) {
                    $rsps['status']=2;
                }
                $orderNumber = isset($order["orderNumber"]) ? $order["orderNumber"]: "";
                $thirdOrderNumber = isset($order["thirdOrderNumber"]) ? $order["thirdOrderNumber"]: "";
            }
        }
        
        $rsps['order_sn'] = $thirdOrderNumber;
        $rsps['othr_number'] = $orderNumber;
        $rsps['msg'] = '';
        $rsps['result'] = "success";
        return $rsps;
    }

    public  function sign($data){   //数据签名
        sort($data,SORT_LOCALE_STRING);
        $str = $data[0].$data[1].$data[2].$data[3].$data[4];
        return strtoupper(sha1($str));
    }

    public  function encode($str)  //数据加密函数
    {
        $data = base64_encode(openssl_encrypt($str, 'AES-128-CBC', $this->key16, OPENSSL_RAW_DATA, $this->iv));
        return $data;
    }

    public   function decode($secretData){   //数据解密函数
        return openssl_decrypt(base64_decode($secretData),'AES-128-CBC',$this->key16,OPENSSL_RAW_DATA,$this->iv);
    }

}