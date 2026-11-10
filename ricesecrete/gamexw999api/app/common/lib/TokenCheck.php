<?php
namespace app\common\lib;
use app\api\redis\ApiRedis;
use app\common\model\AdminModel;
use app\common\Utils;
use Firebase\JWT\JWT;
use think\Exception;
use think\facade\Config;
use app\common\model\UserModel;
use think\facade\Request;
use think\facade\Session;

class TokenCheck
{	
    /**
    * token验证
    * @param header[usertoken] value
    * @param header[usertokenkey] key
    */
    public static function run($header=[],$codes=[]){
        $code=isset($codes['code'])?$codes['code']:1000;//1000--强制登录
        if (!isset($header['usertoken']) && !isset($header['usertokenkey'])) {
            throw new \think\Exception('未登录', $code);
        }
        
        if (empty($header['usertokenkey'])) {
            throw new \think\Exception('未登录', $code);
        }
        
        $UserModel = new UserModel();
        $ApiRedis=new ApiRedis();
        $user = $UserModel->where("usertokenkey",$header['usertokenkey'])->field("id,usertokenkey,usertoken,status,password,end_login_ip,login_time")->find();
        if (!isset($user['usertokenkey'])) {
            throw new \think\Exception('未登录', $code);
        }
        if ($user['status'] != 1) {
            throw new \think\Exception('账号已封停', 101);
        }
        $ip = Util::get_ip();
        $login_key = md5($user['id'].$user['password']);
        $key = Config::get("custom.jwt.jwt_key");//加密key
        try {
            $token = JWT::decode($header['usertoken'],$key,["HS256"]);
            $ApiRedis->setOnline($token->uid);
        } catch (\Exception $e) {
            throw new \think\Exception($e->getMessage(), $code);//未登录
        }
        
        if($token->sign != $login_key){
            throw new \think\Exception('登录已过期', $code);
        }
        
        if (time() > $token->exp) {
            throw new \think\Exception('登录已过期', $code);
        }

        //登录时间超过多少小时后更新登录时间，防止时间过长VIP不会升级
        $updateLoginTimeHours = env('update_login_time_hours', 5);
        if (time() - $user['login_time'] > $updateLoginTimeHours * 3600) {
            $UserModel->where("id", $user['id'])->update(['login_time' => time()]);
        }

        //判断最后登录的IP
        if( $ip != $user['end_login_ip'] && env('change_ip_check', false) ){
            throw new \think\Exception('您的网络IP变动,请重新登录', $code);
        }

        return $token;
    }

/**
    * token验证
    * @param header[usertoken] value
    * @param header[usertokenkey] key
    */
    public static function manage_run($header=[]){
        $code=1000;//1000--强制登录
        if (!isset($header['usertoken']) && !isset($header['usertokenkey'])) {
            throw new \think\Exception('未登录', $code);
        }
        
        if (empty($header['usertokenkey'])) {
            throw new \think\Exception('未登录', $code);
        }
        
        $AdminModel = new AdminModel();
        $user = $AdminModel->where("usertokenkey",$header['usertokenkey'])->find();
        if (!isset($user['usertokenkey'])) {
            throw new \think\Exception('未登录', $code);
        }
        if ($user['status'] != 1) {
            throw new \think\Exception('账号已封停', 101);
        }
        $ip = Util::get_ip();
        $login_key = md5($user['id'].$user['password']);
        $key = Config::get("custom.jwt.jwt_key");//加密key
        try {
            $token = JWT::decode($header['usertoken'],$key,["HS256"]);
        } catch (\Exception $e) {
            throw new \think\Exception($e->getMessage(), $code);//未登录
        }
        
        if($token->sign != $login_key){
            throw new \think\Exception('登录已过期', $code);
        }

        if (time() > $token->exp) {
            throw new \think\Exception('登录已过期', $code);
        }
        //判断最后登录的IP
        if( $ip != $user['end_login_ip'] && env('change_ip_check', false) ){
            throw new \think\Exception('您的网络IP变动,请重新登录', $code);
        }
        $user['is_auth_hide_user'] = Utils::isHideAuthUser($user['role_id']);
        Session::set('adminInfo', $user);
        $token->name = $user['name'];

        $now_url = request()->url();
        $log['admin_id'] = $user['id'];
        $log['username'] = $user['username'];
        $log['admin_name'] = $user['name'];
        $log['handle_name'] = '';
        $log['log_url'] = $now_url;
        Session::set("adminLog",$log);//记录log，session
        return $token;
    }
}