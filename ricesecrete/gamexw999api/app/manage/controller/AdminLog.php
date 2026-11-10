<?php
namespace app\manage\controller;
use app\common\model\AdminLogModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\manage\factory\AdminLogFactory;
use think\facade\View;
use think\facade\Request;
class AdminLog
{	
	protected $middleware = ['ManageCheckLogin',"Language"];

    //权限列表
    public function list(\think\Request $request){
        $rsps = (new AdminLogFactory($request->token->uid))->list();
        return $rsps;
    }

}