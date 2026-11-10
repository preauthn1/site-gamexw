<?php

namespace app\api\controller;

use app\api\factory\PddFactory;
use app\common\lib\Respons;
use think\Request;

class Pdd{

    //获取转盘数据
    public function getTurntable(){
        $result = (new PddFactory())->getTurntable();
        return Respons::run($result);
    }

    //抽奖
    public function turntable(Request $request){
        $result = (new PddFactory($request->token->uid))->turntable();
        return Respons::run($result);
    }

    //取款
    public function withdrawal(Request $request){
        $result = (new PddFactory($request->token->uid))->withdrawal();
        return Respons::run($result);
    }
}