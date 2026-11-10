<?php

namespace app\api\controller;

use app\api\factory\TaskFactory;
use app\common\lib\Respons;
use think\Request;

class Task{

    //任务列表
    public function index(){
        $result = (new TaskFactory())->index_run();
        return Respons::run($result);
    }

    //任务领取奖励
    public function receive(Request $request){
        return (new TaskFactory($request->token->uid))->receive();
    }

    //阶段任务奖励领取
    public function stage_receive(Request $request){
        return (new TaskFactory($request->token->uid))->stage_receive();
    }
}