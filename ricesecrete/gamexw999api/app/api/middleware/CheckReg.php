<?php
namespace app\api\middleware;
use think\facade\Session;
use app\common\model\AdminModel;
use think\facade\Request;
class CheckReg
{	
	
    public function handle($request, \Closure $next)
    {   
        $share_code=$request->param("share_code");
        Session::set("shareRegCode",$share_code);//分享码

    	$user=Session::get("shareReg");//是否分享注册
    	if (!isset($user['id'])) {
    		return redirect('/reg');//未登录
    	}
        return $next($request);
    }
}