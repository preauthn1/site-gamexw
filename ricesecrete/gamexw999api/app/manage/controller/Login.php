<?php
namespace app\manage\controller;

use app\common\model\{AdminModel, AdminNodeModel, IpLimitModel, LangModel};
use app\common\lib\{Respons, Util, Authenticator};
use think\facade\{View, Request, Session, Cookie, Db, Config, Lang};
use Gregwar\Captcha\{CaptchaBuilder, PhraseBuilder};
use app\manage\factory\LoginFactory;

class Login
{	
    protected $middleware = ["Language"];

    //登录
    public function login(){
        $rsps=(new LoginFactory())->login_run();
        return $rsps;
    }

    public function captcha()
    {
        $rsps=(new LoginFactory())->captcha_run();
        return $rsps;
    }
}