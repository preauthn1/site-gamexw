<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\UserWithdrawalFactory;
use think\Request;
class UserWithdrawal
{	
	
    //提现
    public function apply(Request $request){
        $rsps=(new UserWithdrawalFactory($request->token->uid))->apply_run();
        return Respons::run($rsps);
    }

    //提现限制
    public function limit(Request $request){
        $rsps=(new UserWithdrawalFactory($request->token->uid))->limit_run();
        return Respons::run($rsps);
    }

    //提现记录
    public function log(Request $request){
        $rsps=(new UserWithdrawalFactory($request->token->uid))->log_run();
        return Respons::run($rsps);
    }
}