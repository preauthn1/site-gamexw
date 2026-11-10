<?php
namespace app\promote\middleware;
use app\common\model\UserModel;
use think\facade\Session;
use think\facade\Request;
use app\common\lib\Respons;

class CheckLogin
{	
	
    public function handle($request, \Closure $next)
    {
    	$agent = Session::get("agentInfo");
    	if (!isset($agent['id'])) {
            if ($request->isPost()) {
                throw new \think\Exception("未登录", 991);//未登录
            }else{
                if ("Index"==$request->controller() && "index"==$request->action() ) {
                    return redirect(url('/login/index'));//未登录
                }else{
                    return response("<script>window.top.admin_not_login();</script>");//未登录
                }
            }
    	}
        
        $res = UserModel::alias("a")->join("dq_user_agent b","a.id=b.uid")->where(["a.id" => $agent['id'], "b.type" => 1, "a.status" => 1])->field('a.id')->find();
        if (empty($res)) {
            Session::delete('agentInfo');
            if ("Index"==$request->controller() && "index"==$request->action() ) {
                return redirect(url('/login/index'));//未登录
            }else{
                return response("<script>window.top.admin_not_login();</script>");//未登录
            }
    	}
        
        return $next($request);
    }
}