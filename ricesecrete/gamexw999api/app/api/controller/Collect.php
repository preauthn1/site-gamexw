<?php

namespace app\api\controller;

use app\api\factory\CollectFactory;
use app\common\lib\Respons;
use think\Request;

class Collect{

    //收藏列表
    public function index(Request $request){
        $result = (new CollectFactory($request->token->uid))->collectList();
        return Respons::run($result);
    }

    //收藏
    public function collect(Request $request){
        $result = (new CollectFactory($request->token->uid))->collect();
        return Respons::run($result);
    }


}