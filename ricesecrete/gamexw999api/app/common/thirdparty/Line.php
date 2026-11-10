<?php
namespace app\common\thirdparty;
use Complex\Exception;
use think\facade\Log;
class Line
{
    private $client_id ='';
    private $client_secret ='';
    private $redirect_uri ='';

    public function __construct($third_set){
        $this->client_id = $third_set['appid'];
        $this->client_secret = $third_set['secretkey'];
//        $this->redirect_uri = $third_set['callback'];
        $rootDomain = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_REFERER'] ;
        $this->redirect_uri =  trim($rootDomain, '/') . '/third.html';
	}

    /**
     * CODE
     */
    public function get_url($third_arr){
        $client_id=$this->client_id;
        $redirect_uris=urlencode($this->redirect_uri);
        $scope = urlencode('profile openid');
        $state = md5(time());
        $url = "https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uris}&state={$state}&scope={$scope}&initial_amr_display=lineqr";
        $rsps = [];
        $rsps['data'] = ['url'=>$url];
        $rsps['code']=1;
        return $rsps;
    }

    public function get_token($code){
        $client_id=$this->client_id;
        $redirect_uris=urlencode($this->redirect_uri);
        $client_secret=$this->client_secret;
        $ch        = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/oauth2/v2.1/token');
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=authorization_code&client_id={$client_id}&client_secret={$client_secret}&code={$code}&redirect_uri={$redirect_uris}");
        curl_setopt($ch, CURLOPT_HTTPHEADER,array(
            'Content-Type: application/x-www-form-urlencoded'
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        $res = json_decode($response,true);
        if (!isset($res['id_token'])){
            throw new \Exception('请求错误', 0);
        }
        return $res['id_token'];
    }

    public function get_userInfo($third_arr){
        $code = $third_arr['code']??"";
        $id_token = $this->get_token($code);
        $client_id=$this->client_id;
        $ch        = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/oauth2/v2.1/verify');
        curl_setopt($ch, CURLOPT_POSTFIELDS, "id_token={$id_token}&client_id={$client_id}");
        curl_setopt($ch, CURLOPT_HTTPHEADER,array(
            'Content-Type: application/x-www-form-urlencoded'
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        $userInfo = json_decode($response,true);
        if (!isset($userInfo['sub'])){
            throw new \Exception('请求错误', 0);
        }
        $data['third_uid'] = $userInfo['sub'];
//        $data['username'] = 'FB'.$userInfo['sub'].'l';
        $data['username'] = $userInfo['email'] ?? '';
        $data['email'] = $userInfo['email'] ?? '';
        $data['head_img'] = $userInfo['picture']??"";
        return $data;
    }

}