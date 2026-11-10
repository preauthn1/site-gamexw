<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\FeedbackFactory;
use think\Request;
class Feedback
{

    //反馈添加
    public function Set(Request $request){
        $rsps=(new FeedbackFactory($request->token->uid))->set_run();
        return Respons::run($rsps);
    }
}