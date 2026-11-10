<?php

namespace app\api\controller;

use app\api\factory\ReceiveCodeFactory;
use app\common\lib\Respons;
use think\Request;

class ReceiveCode{

    //领取
    public function receive(Request $request){
        return (new ReceiveCodeFactory($request->token->uid))->receive();
    }

}