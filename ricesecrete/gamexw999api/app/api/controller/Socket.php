<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\SocketFactory;
use think\Request;

class Socket
{
    public function regUser(Request $request){
        $rsps=(new SocketFactory($request->token->uid ?? 0))->regUser_run();
        return json($rsps);
    }

    public function sendMsg(){
        $rsps=(new SocketFactory())->sendMsg_run();
        return json($rsps);
    }
}
