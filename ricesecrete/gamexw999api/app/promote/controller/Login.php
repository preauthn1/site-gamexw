<?php
namespace app\promote\controller;
use app\common\model\ClientLogin;
use app\common\model\UserAgentModel;
use app\common\model\UserModel;
use app\common\model\IpLimitModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\lib\Authenticator;
use app\common\redis\CommomRedis;
use app\manage\controller\UserLoginLog;
use thans\jwt\facade\JWTAuth;
use think\facade\View;
use think\facade\Request;
use think\facade\Session;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Config;
use think\facade\Lang;
use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;
class Login extends Base
{
    //登录验证
    public function login(){
        $param = $this->request->param();
    	if(!isset($param['username']) || !isset($param['password']) || !isset($param['code'])){
            return $this->error('参数有误');
    	}
    	try {
            validate(\app\common\validate\LoginValidate::class)->check($param);
        } catch (\think\exception\ValidateException $e) {
            return $this->error($e->getError());
        }

        $redis = new CommomRedis();
        $code = $redis->get($param['code_key']);
        if ($code != $param['code']) {
            $redis->delete($param['code_key']);
            return $this->error('验证码错误');
        }

        $user = UserModel::where(["username|mail|phone" => $param['username']])->find();
        if (empty($user)) {
            return $this->error('账户密码错误或您的账号不是内部代理');
    	}

        $user_agent = UserAgentModel::where('uid', $user['id'])->where('type', 1)->find();
        if (empty($user_agent)) {
            return $this->error('账户密码错误或您的账号不是内部代理');
        }

        if ($user['status'] == 0) {
            return $this->error('账户禁用，禁止登陆');
        }
        
        if (Util::userMd5Password($param['password']) != $user['password']) {
            return $this->error('账户或密码错误');
    	}
        $token = JWTAuth::builder(['client_user_id' => $user->id]);
        $user->login_time = time();
        $user->end_login_ip = Util::get_ip();
        $user->save();
        event(new \app\event\UserLogin($user));
        $rsps['token'] = $token;
        $rsps['token_key'] = $token;
        $rsps['username'] = $user['phone'] ?? ($user['mail'] ?? $user['username']);
        $rsps['mail'] = $user['mail'];
        $rsps['phone'] = $user['phone'];
        $rsps['roles'] = ["admin"];
        return $this->success([
            'data' => $rsps
        ]);
    }

    //退出登录
    public function outLogin(){
        Session::delete('agentInfo');
        return redirect(url('/login/index'));//未登录
    }

    public function captcha($id = '')
    {
        $phraseBuilder = new PhraseBuilder(5, '0123456789');
        $builder = new CaptchaBuilder(null, $phraseBuilder);
        $builder->build();
        $base64 = $builder->inline();
        $key = md5($base64);
        $CommomRedis=new CommomRedis();
        $CommomRedis->set($key, $builder->getPhrase(), 300);
        $rsps = ['base64' => $base64, "key" => $key];
        return Respons::run(1,$rsps);
    }
}