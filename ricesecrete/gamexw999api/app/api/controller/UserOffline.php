<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\UserOfflineFactory;
use think\Request;
class UserOffline
{	
    public function parame(Request $request){
        $rsps=(new UserOfflineFactory($request->token->uid))->parame_run();
        return Respons::run($rsps);
    }
    //充值
    public function apply(Request $request){
        $rsps=(new UserOfflineFactory($request->token->uid))->apply_run();
        return Respons::run($rsps);
    }

    //充值记录
    public function log(Request $request){
        $rsps=(new UserOfflineFactory($request->token->uid))->log_run();
        return Respons::run($rsps);
    }

    //线下收款账户
    public function sk_bank(Request $request){
        $rsps=(new UserOfflineFactory($request->token->uid))->sk_bank_run();
        return Respons::run($rsps);
    }

    //获取个人赠送彩金
    public function mosaic_gold(Request $request){
        $rsps=(new UserOfflineFactory($request->token->uid))->mosaic_gold_run();
        return Respons::run($rsps);
    }

    public function all_collection(Request $request){
        $rsps=(new UserOfflineFactory($request->token->uid))->all_collection_run();
        return Respons::run($rsps);
    }

    public function get_bank_set_content(Request $request){
        $rsps=(new UserOfflineFactory($request->token->uid))->get_bank_set_content_run();
        return Respons::run($rsps);
    }
}