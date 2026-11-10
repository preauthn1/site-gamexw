<?php
namespace app\manage\middleware;

use app\common\model\{AdminModel, AdminPermissionModel, AdminNodeModel};
use think\facade\{Log, Session, Request};
use app\common\lib\Respons;
use function foo\func;
use app\common\lib\TokenCheck;
use Exception;

class CheckLogin
{	
	
    public function handle($request, \Closure $next)
    {
        // OPTIONS请求直接返回
        if ($request->isOptions()) {
            return response();
        }
    	$header = $request->header();
        try {
            $token = TokenCheck::manage_run($header);
        } catch (Exception $e) {
            throw new \app\common\exception\MessageException(lang($e->getMessage()), $e->getCode());
        }
        $request->token = $token;
        $request->auth_middleware = true;
        return $next($request);
    }
    
    public function ignore($url){
        //这里去掉模块 /admin 因为路径没有带模块
        $urls =[
            '/index/index',
            '/index/get_data',
            '/index/get_match',
            '/index/cjl_set',
            '/index/cjl_ignore',
            '/index/cancel_match',
            '/agent_report/user_data_view',
            '/agent_report/user_data',
            '/agent_report/offline_list_data',
            '/user/user_top',
            '/user/get_top_user',
            '/index/bind_google',
            '/artificial_balance/set_water',
            '/artificial_balance/import',
            '/user_bank/public_list',
            '/fund/record',
            '/user_bank/list',
            '/liansai_zhudan/get_order_data',
            '/user_offline/get_user_data',
            '/user_withdrawal/get_user_data',
            '/user/running_water',
            '/admin/rand_char',
            '/admin_node/get_data',
        ];

        if(in_array($url, $urls)){
            return true;
        }else{
            return false;
        }
    }

    /*有些权限节点是带参数的 在这里先组装上*/
    public function zuUrl($url){
        $news_ = [
            //news系列菜单 携带的是type的参数
            '/news/lists' => 'type',
            '/news/delete' => 'type',
            '/news/add' => 'type',
            '/news/edit' => 'type',
        ];
        //如果当前路径 是存在需要特殊处理的列表中
        $in_url = in_array($url,array_keys($news_));
        if($in_url && isset(Request::param()[$news_[$url]])){
            //将要携带的参数拼接上去 再把值拼接 就能等到数据库中存的路径了
            $url = $url .'?'.$news_[$url] .'=' . Request::param()[$news_[$url]];
        }
        return $url;
    }
}