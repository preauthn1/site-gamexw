<?php
namespace app\common\payment;
use think\facade\Log;

class PayRequest{
    //提现提交表单
    public static function curlS($url, $return_array,$header=[],$timeout = 10){
        if(!$header){
            $header=["Content-type: application/x-www-form-urlencoded"];
        }

        // 增加HTTP代理请求
        $isProxy = env('IS_REQUIRE_GAME_PROXY', '');
        $proxy = '';
        if ($isProxy) {
            $proxy = env('GAME_PROXY_URL', '');
        }

        if(is_array($return_array)){
            $postData = "";
            foreach ($return_array as $key => $val) {
                $postData = $postData . $key . "=" . $val . "&";
            }
            $postData = rtrim($postData, '&');
        }else{
            $postData = $return_array;
        }
        $ch        = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        $contents = curl_exec($ch);
        if( $contents === false ){
            Log::write(['withdrawal_request_error', $url, $postData, $header, curl_error($ch)], "withdrawal");
            throw new \Exception('请求异常，请确认三方是否收到订单再继续审核', 0);
        }

        curl_close($ch);
        return $contents;
    }


    public static function curl_get($url, $data, $header = []){
        if($url == "" ){
            return false;
        }
        // 增加HTTP代理请求
        $isProxy = env('IS_REQUIRE_GAME_PROXY', '');
        $proxy = '';
        if ($isProxy) {
            $proxy = env('GAME_PROXY_URL', '');
        }
        if(count($data) > 0){
            $url = $url.'?'.http_build_query($data);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        $output = curl_exec($ch);
        //释放curl句柄
        curl_close($ch);
        return $output;
    }
}