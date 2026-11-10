<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\BankFactory;
use think\Request;
class Bank
{	
    //银行卡
    public function lists(Request $request){
        $rsps=(new BankFactory($request->token->uid))->list_run();
        return Respons::run($rsps);
    }

    //添加银行卡
    public function add_bank(Request $request){
        $rsps=(new BankFactory($request->token->uid))->add_bank_run();
        return Respons::run($rsps);
    }

    //我的银行卡
    public function my_bank(Request $request){
        $rsps=(new BankFactory($request->token->uid))->my_bank_run();
        return Respons::run($rsps);
    }

    //删除银行卡
    public function my_del(Request $request){
        $rsps=(new BankFactory($request->token->uid))->my_del_run();
        return Respons::run($rsps);
    }	
}