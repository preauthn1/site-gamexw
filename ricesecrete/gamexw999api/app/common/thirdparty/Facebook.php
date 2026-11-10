<?php
namespace app\common\thirdparty;

use think\facade\Log;

class Facebook
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
        $url = "https://www.facebook.com/v12.0/dialog/oauth?client_id={$client_id}&redirect_uri={$redirect_uris}&state";
        $rsps = [];
        $rsps['data'] = ['url'=>$url];
        $rsps['code']=1;
        return $rsps;
    }

    public function get_token($code){
        $client_id=$this->client_id;
        $redirect_uris=urlencode($this->redirect_uri);
        $client_secret=$this->client_secret;
        $url = "https://graph.facebook.com/v12.0/oauth/access_token?client_id={$client_id}&redirect_uri={$redirect_uris}&client_secret={$client_secret}&code={$code}";
        $res = json_decode(file_get_contents($url),true);
        if (!isset($res['access_token'])){
            throw new \Exception('请求错误', 0);
        }
        return $res['access_token'];
    }

    public function get_userInfo($third_arr){
        $code = $third_arr['code']??"";
        $access_token = $this->get_token($code);
        $url = "https://graph.facebook.com/v12.0/me?fields=id,picture&access_token={$access_token}";
        $userInfo = json_decode(file_get_contents($url),true);
        if (!isset($userInfo['id'])){
            throw new \Exception('请求错误', 0);
        }
        Log::write(['FB授权登录', $userInfo], "three_game");
        $data['third_uid'] = $userInfo['id'];
        $data['username'] = 'FB'.$userInfo['id'].'f';
        $data['head_img'] = "";
        if (isset($userInfo['picture'])&&isset($userInfo['picture']['data'])&&isset($userInfo['picture']['data']['url'])){
            $data['head_img'] = $userInfo['picture']['data']['url'];
        }
        return $data;
    }

}