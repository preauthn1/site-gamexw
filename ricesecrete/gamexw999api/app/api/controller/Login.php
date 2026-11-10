<?php
namespace app\api\controller;
use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\api\factory\LoginFactory;
use app\common\model\ThirdSetModel;
use think\Request;

class Login
{
    
    //验证码
    public function captcha(){
        $rsps=(new LoginFactory())->captcha_run();
        return Respons::run($rsps);
    }

    //注册
    public function register(){
        $rsps=(new LoginFactory())->register_run();
        return Respons::run($rsps);
    }

    //登录
    public function login(){
        $rsps=(new LoginFactory())->login_run();
        return Respons::run($rsps);
    }

        //点击量
    public function click_count()
    {
        $rsps = (new LoginFactory())->click_count_run();
        return Respons::run($rsps);
    }


    public function get_third_login(){
        $rsps=(new LoginFactory())->get_third_login_run();
        return Respons::run($rsps);
    }

    public function get_third_login_url(){
        $rsps=(new LoginFactory())->get_third_login_url_run();
        return Respons::run($rsps);
    }

    public function third_login(){
        $rsps=(new LoginFactory())->third_login_run();
        return Respons::run($rsps);
    }

    public function third_login_callback(){
        $base_url = getAppUrl();
        $params = request()->param();
        $id = '';
        if (isset($params['id'])) {
//            $ThirdSetModel = new ThirdSetModel();
//            $ThirdSet = $ThirdSetModel->where("id", $params['id'])->where("status",1)->find();
//            $base_url = '';
            $id = $params['id'];
        }
        if (isset($params['code'])){
            return redirect($base_url."/#/pages/thirdlogin/callback/{$id}?code=".urlencode($params['code']));
        }else{
            return redirect($base_url);
        }
    }


    public function thrid_bind_user(Request $request){
        $rsps=(new LoginFactory($request->token->uid))->thrid_bind_user();
        return Respons::run($rsps);
    }

    public function unbind_third_user(Request $request){
        $rsps=(new LoginFactory($request->token->uid))->unbind_third_user();
        return Respons::run($rsps);
    }
}