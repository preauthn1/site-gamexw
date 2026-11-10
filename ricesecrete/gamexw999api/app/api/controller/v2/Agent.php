<?php

namespace app\api\controller\v2;

use app\api\factory_v2\AgentFactory;
use app\common\lib\Respons;
use think\Request;

class Agent{

    //我的推荐
    public function recommend(Request $request){
        $rsps = (new AgentFactory($request->token->uid))->recommend_run();
        return Respons::run($rsps);
    }

    //我的佣金
    public function commission(Request $request){
        $rsps = (new AgentFactory($request->token->uid))->commission_run();
        return Respons::run($rsps);
    }

    //佣金详情
    public function commissionDetail(Request $request){
        $rsps = (new AgentFactory($request->token->uid))->commissionDetail_run();
        return Respons::run($rsps);
    }

    //我的收入
    public function income(Request $request){
        $rsps = (new AgentFactory($request->token->uid))->income_run();
        return Respons::run($rsps);
    }

    //所有数据
    public function statistics(Request $request){
        $rsps = (new AgentFactory($request->token->uid))->statistics_run();
        return Respons::run($rsps);
    }

    //直接投注（下级投注）
    public function childBet(Request $request){
        $rsps = (new AgentFactory($request->token->uid))->childBet_run();
        return Respons::run($rsps);
    }

    //直接会员（下级会员）
    public function childMember(Request $request){
        $rsps = (new AgentFactory($request->token->uid))->childMember_run();
        return Respons::run($rsps);
    }

    //直属奖金
    public function childBonus(Request $request){
        $rsps = (new AgentFactory($request->token->uid))->childBonus_run();
        return Respons::run($rsps);
    }

    //返佣比例
    public function commissionRate(Request $request){
        $rsps = (new AgentFactory($request->token->uid))->commissionRate_run();
        return Respons::run($rsps);
    }

    //直属开户（下级开户）
    public function childAccount(Request $request){
        $rsps = (new AgentFactory($request->token->uid))->childAccount_run();
        return Respons::run($rsps);
    }


}