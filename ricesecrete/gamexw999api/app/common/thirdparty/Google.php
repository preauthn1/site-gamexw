<?php
namespace app\common\thirdparty;
use think\facade\Log;

class Google
{
    private $client_id ='';
    private $client_secret ='';
    private $redirect_uri ='';

    public function __construct($third_set){
        $this->client_id = $third_set['appid'];
        $this->client_secret = $third_set['secretkey'];
        $this->secretkey = $third_set['secretkey'];
        $rootDomain = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_REFERER'] ;
        // 使用 parse_url 函数解析 URL
        // $urlParts = parse_url($currentUrl);
        // 获取域名部分
        // $domain = $urlParts['path'];
        // 拆分域名为数组
        // $domainParts = explode('.', $domain);

        // 获取根域名（顶级域名）
        // $rootDomain = $domainParts[count($domainParts) - 2] . '.' . $domainParts[count($domainParts) - 1];
        $this->redirect_uri =  trim($rootDomain, '/') . '/third.html';
    }

    /**
     * CODE
     */
    public function get_url($third_arr){
        $client_id=$this->client_id;
//        $redirect_uris=urlencode($this->redirect_uri);
        if(isset($third_arr['href'])){
            $redirect_uri = $third_arr['href'];
        }else{
            $redirect_uri = $this->redirect_uri;
        }
        $redirect_uri = urlencode(trim($redirect_uri, '/') . '/third.html');
        $scope=urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email');
        $url = "https://accounts.google.com/o/oauth2/auth?response_type=code&access_type=offline&client_id={$client_id}&redirect_uri={$redirect_uri}&state&scope={$scope}&approval_prompt=auto";
        $rsps = [];
        $rsps['data'] = ['url'=>$url];
        $rsps['code']=1;
        return $rsps;
    }

    public function get_token($postData){
        $fields = (is_array($postData)) ? http_build_query($postData) : $postData;
        $curlHeaders = [
            'content-type: application/x-www-form-urlencoded;CHARSET=utf-8',
            'Content-Length: ' . strlen($fields),
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://accounts.google.com/o/oauth2/token");
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if($response && $responseCode == 200){
            $json = json_decode($response, true);
            $token=$json['access_token'];
            return $token;
        }else {
            return false;
        }
    }

    public function get_userInfo($third_arr){
        $code = $third_arr['code']??"";
        $postData=array(
            'code'=>$code,
            'client_id'=>$this->client_id,
            'client_secret'=>$this->client_secret,
            'redirect_uri'=>$this->redirect_uri,
            'grant_type'=>'authorization_code'
        );
        $access_token = $this->get_token($postData);
        if (!$access_token){
            throw new \Exception('请求错误', 0);
        }
        $url = "https://www.googleapis.com/oauth2/v1/userinfo?access_token=".$access_token;
        $userInfo = json_decode(file_get_contents($url),true);
        if (!isset($userInfo['id'])){
            throw new \Exception('请求错误', 0);
        }
        Log::write(['谷歌授权登录', $userInfo], "three_game");
        $data['third_uid'] = $userInfo['id'];
        $data['username'] = rtrim($userInfo['email'], '@gmail.com');
        $data['email'] = $userInfo['email'];
        $data['head_img'] = isset($userInfo['picture'])?$userInfo['picture']:"";
        return $data;
    }

}