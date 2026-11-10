<?php
namespace app\common\thirdparty;

class Zalo
{
    private $client_id ='';
    private $client_secret ='';
    private $redirect_uri ='';

    public function __construct($third_set){
        $this->client_id = $third_set['appid'];
        $this->client_secret = $third_set['secretkey'];
        $this->redirect_uri = $third_set['callback'];
	}

    /**
     * CODE
     */
    public function get_url($third_arr){
        $client_id=$this->client_id;
        $redirect_uris=urlencode($this->redirect_uri);
        $url = "https://oauth.zaloapp.com/v4/permission?app_id={$client_id}&redirect_uri={$redirect_uris}&state=zalo";
        $rsps = [];
        $rsps['data'] = ['url'=>$url];
        $rsps['code']=1;
        return $rsps;
    }

    public function get_token($code){
        $ch        = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'https://oauth.zaloapp.com/v4/access_token');
        curl_setopt($ch, CURLOPT_POSTFIELDS, "code={$code}&app_id={$this->client_id}&grant_type=authorization_code");
        curl_setopt($ch, CURLOPT_HTTPHEADER,array(
            "secret_key: {$this->client_secret}",
            'Content-Type: application/x-www-form-urlencoded'
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        $res = json_decode($response,true);
        if (!isset($res['access_token'])){
            throw new \Exception('请求错误', 0);
        }
        return $res['access_token'];
    }

    public function get_userInfo($third_arr){
        $code = $third_arr['code']??"";
        $access_token = $this->get_token($code);
        $url = "https://graph.zalo.me/v2.0/me?access_token={$access_token}&fields=id,name,picture";
        $userInfo = json_decode(file_get_contents($url),true);
        if (!isset($userInfo['id'])){
            throw new \Exception('请求错误', 0);
        }
        $data['third_uid'] = $userInfo['id'];
        $data['username'] = 'FB'.$userInfo['id'].'z';
        $data['head_img'] = "";
        if (isset($userInfo['picture'])&&isset($userInfo['picture']['data'])&&isset($userInfo['picture']['data']['url'])){
            $data['head_img'] = $userInfo['picture']['data']['url'];
        }
        return $data;
    }

}