<?php
namespace app\api\factory;
use app\common\lib\Util;
use think\facade\Request;
use think\facade\Db;
class GatherFactory
{
    private $domain;
    private $account;
    private $passwd;
    
    public function __construct(){
        $this->domain="http://sport.302k.cn:3101/";
        $this->account="13366558725";
        $this->REDACTED;
    }
    
    public function getToken(){
        $param = [];
        $param['account'] = $this->account;
        $param['passwd'] = $this->passwd;
        dump($param);
        $url = $this->domain."api/auth/login";
        echo $url;
        $ret = $this->http_post_json($url, $param);
        dump($ret);
    }
    
    public function http_post_json($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type=application/x-www-form-urlencoded;charset=utf-8',
            )
        );
        $response = curl_exec($ch);
        dump($response);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $response;
        /*
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($jsonStr)
            )
        );
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $response;*/
    }
}