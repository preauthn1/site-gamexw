<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\MsgFactory;
use think\Request;
class Msg
{	
	
    //未读
    public function unread(Request $request){
        $rsps=(new MsgFactory($request->token->uid))->unread_run();
        return Respons::run($rsps);
    }

    //消息列表
    public function lists(Request $request){
        $rsps=(new MsgFactory($request->token->uid))->list_run();
        return Respons::run($rsps);
    }

    //设置已读
    public function set_read(Request $request){
        $rsps=(new MsgFactory($request->token->uid))->set_read_run();
        return Respons::run($rsps);
    }

    //删除消息
    public function del(Request $request){
        $rsps=(new MsgFactory($request->token->uid))->del_run();
        return Respons::run($rsps);
    }
    
    //消息详细
    public function detail(Request $request){
        $rsps=(new MsgFactory($request->token->uid))->detail_run();
        return Respons::run($rsps);
    }

    //系统消息
    public function system_msg(Request $request){
        $rsps=(new MsgFactory($request->token->uid))->system_msg_run();
        return Respons::run($rsps);
    }
}