<?php
namespace app\manage\controller;

use app\common\model\FeedbackModel;
use app\common\model\UserModel;
use app\common\lib\Respons;
use app\manage\factory\FeedbackFaction;
use think\facade\View;
use think\facade\Request;
use think\facade\Db;
class Feedback
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //反馈列表
    public function list(\think\Request $request){
        $rsps = (new FeedbackFaction($request->token->uid))->list();
        return $rsps;
    }

}
