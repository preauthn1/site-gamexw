<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\NoticeFactory;
use think\Request;
class Notice
{	
	
    //公告列表
    public function lists(Request $request){
        $rsps=(new NoticeFactory($request->token->uid ?? 0))->list_run();
        return Respons::run($rsps);
    }
    
    //公告详情
    public function detail(Request $request){
        $rsps=(new NoticeFactory($request->token->uid ?? 0))->detail_run();
        return Respons::run($rsps);
    }

    //公告类型
    public function type(Request $request){
        $rsps=(new NoticeFactory($request->token->uid ?? 0))->type_run();
        return Respons::run($rsps);
    }
	
}