<?php
namespace app\common\game;
class GameRequest{

    //提现提交表单
    public static function curlS($url, $return_array,$header=[]){
        if(!$header){
            $header[]="Content-type: application/x-www-form-urlencoded";
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $contents = curl_exec($ch);
        curl_close($ch);
        return $contents;
    }

    public static function httpSend($url, $data){
        if($url == "" ){
            return false;
        }
        $url = $url.'?'.http_build_query($data);
        $client = new \GuzzleHttp\Client();
        $request = $client->get($url);
        $output = $request->getBody()->getContents();
        $output = json_decode($output,true);
        return $output;
    }



    /**
     * get 请求
     * @param $url
     * @param $data
     * @return bool|string
     */
    public static function curl_get($url, $data,$header=[]){
        if($url == "" ){
            return false;
        }
        if(!$header){
            $header[]="Content-type: application/x-www-form-urlencoded";
        }
        $url = $url.'?'.http_build_query($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $output = curl_exec($ch);
        //释放curl句柄
        curl_close($ch);
        return $output;
    }


    /**
     * curl post请求 编码参数
     * @param string $url
     * @param String $return_array
     * @param array $header
     * @return bool|string
     */
    public static function curl_post(string $url, string $return_array, array $header=[], $proxy = ''){
        if(!$header){
            $header[]="Content-type: application/x-www-form-urlencoded";
        }

        $ch        = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $return_array);
        if( $proxy ){
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $contents = curl_exec($ch);
        curl_close($ch);
        return $contents;
    }
}