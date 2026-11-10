<?php
namespace app\manage\factory;
use think\facade\{Request, Session, Config};
use app\common\lib\{Respons, Util};
use app\common\model\{AdminModel, AdminNodeModel, IpLimitModel};
use app\common\redis\CommomRedis;
use Firebase\JWT\JWT;
use Gregwar\Captcha\{CaptchaBuilder, PhraseBuilder};
class LoginFactory {


    //登录验证
    public function login_run(){
        $CommomRedis=new CommomRedis();
    	$param = Request::param();
    	if(!isset($param['username']) || !isset($param['password']) || !isset($param['code'])){
    	    Session::delete('phrase');
    	    return Respons::run(0,[],lang('参数错误'));
    	}
    	try {
            validate(\app\common\validate\LoginValidate::class)->check($param);
        } catch (\think\exception\ValidateException $e) {
            // 验证失败 输出错误信息
            return Respons::run(0,[],$e->getError());
        }

        $code = $CommomRedis->get($param['code_key']);
        if ($code != $param['code']) {
            $CommomRedis->delete($param['code_key']);
            return Respons::run(0,[],lang('验证码错误'));
//            throw new \think\Exception('验证码错误', 0);
        }
	    
        $ip = Util::get_ip();
        $xzip = IpLimitModel::where(["ip"=>$ip, 'admin_status' => 1])->count();
        if($xzip > 0){
            Session::delete('phrase');
            return Respons::run(0,[],lang('IP已封停'));
        }

        /** @var AdminModel $AdminModel */
        $AdminModel = app()->make(AdminModel::class);

        $admin = $AdminModel
            ->with('adminRole')
            ->where("username",$param['username'])
            ->find();
        if (!isset($admin['id'])) {
            Session::delete('phrase');
            return Respons::run(0,[],lang('账户或密码错误'));
        }

        if (Util::md5Password($param['password'])!=$admin['password']) {
            Session::delete('phrase');
            return Respons::run(0,[],lang('账户或密码错误'));
        }

        $limit_ip = $admin['limit_ip'];
        if ($admin['status'] == 0) {
            Session::delete('phrase');
            return Respons::run(0,[],lang('账户禁用，禁止登陆'));
        }
        if(!empty($limit_ip) && $admin['id'] != 1){
            $limit_ip = explode(",",$limit_ip);
            $ip = Util::get_ip();
            if(!in_array($ip, $limit_ip)){
                Session::delete('phrase');
                return Respons::run(0,[],lang('IP限制，无法登录')."--".$ip);
            }
        }
            
    	
//        if ($res['goodsle_bind']==1) {
//            $Authenticator=new Authenticator();
//            $checkResult = $Authenticator->verifyCode($res['google_secret'],$param['google_code'],1);
//            if (!$checkResult) {
//                Session::delete('phrase');
//                return Respons::run(0,[],lang('google验证失败'));
//            }
//        }
    	$key = Config::get("custom.jwt.jwt_key");//加密key
        $token = [
            "iss"=>"",  //签发者 可以为空
            "aud"=>"", //面象的用户，可以为空
            "iat" => Config::get("custom.jwt.iat"), //签发时间
            "nbf" => Config::get("custom.jwt.nbf"), //在什么时候jwt开始生效
            "exp" => Config::get("custom.jwt.exp"), //token 过期时间
            "uid" => $admin['id'], //记录的user的信息
            "sign" => md5($admin['id'].$admin['password']),
        ];
        if(isset($admin['usertokenkey']) && $admin['usertokenkey']){
            $CommomRedis->delete($admin['usertokenkey']);//删除key
        }
        $jwt = JWT::encode($token,$key,"HS256"); //根据参数生成了 token
        $admin['usertokenkey']=md5("tokenkey".$jwt);
        $admin['usertoken']=$jwt;
        $AdminModel::where('id', $admin['id'])->update(['usertokenkey' => $admin['usertokenkey'], 'usertoken' => $admin['usertoken']]);
        $CommomRedis->set($admin['usertokenkey'],$admin['usertoken']);
        $rsps['token'] = $admin['usertoken'];
        $rsps['token_key'] = $admin['usertokenkey'];
        $rsps['username'] = $admin['username'];
//        $rsps['admin'] = $admin;
        $rsps['roles'] = ["role{$admin['role_id']}"];
    	return Respons::run(1,$rsps,lang('登录成功'));
    }

    public function captcha_run()
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
?>