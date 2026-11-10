<?php

namespace app\api\controller;

use app\api\factory\LotteryOnlineFactory;
use app\common\lib\Respons;
use think\Request;

class LotteryOnline{

    //获取转盘任务
    public function getOnlineTask(){
        $result = (new LotteryOnlineFactory())->getLotteryOnlineTaskData();
        return Respons::run(1, $result);

    }

    //获取转盘任务
    public function online(Request $request){
        $result = (new LotteryOnlineFactory($request->token->uid))->online();
        return Respons::run($result);

    }


}