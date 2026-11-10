<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\FlyerFactory;
use think\Request;
class Flyer
{

    //登录飞行员
    public function login(Request $request){
        $rsps=(new FlyerFactory($request->token->uid))->login_run();
        return Respons::run($rsps);
    }

    //个人中心
    public function list(Request $request){
        $rsps=(new FlyerFactory($request->token->uid))->list_run();
        return Respons::run($rsps);
    }

    //同步余额
    public function flyer_balance(){
        $rsps=(new FlyerFactory())->flyer_balance_run();
        return Respons::run($rsps);
    }

    //下分
    public function flyer_out(Request $request){
        $rsps=(new FlyerFactory($request->token->uid))->flyer_out_run();
        return Respons::run($rsps);
    }

    //上分
    public function flyer_in(Request $request){
        $rsps=(new FlyerFactory($request->token->uid))->flyer_in_run();
        return Respons::run($rsps);
    }

    //同步注单
    public function flyer_zd(Request $request){
        $rsps=(new FlyerFactory())->flyer_zd_run();
        return Respons::run($rsps);
    }

    //注单列表
    public function zhudan(Request $request){
        $rsps=(new FlyerFactory($request->token->uid))->zhudan_run();
        return Respons::run($rsps);
    }

    //下分
    public function flyer_out_all(Request $request){
        $rsps=(new FlyerFactory())->flyer_out_all_run();
        return Respons::run($rsps);
    }
    //报表同步
    public function flyer_report(Request $request){
        $rsps=(new FlyerFactory())->flyer_report_run();
        return Respons::run($rsps);
    }
}