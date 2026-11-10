<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\AgentFactory;
use think\Request;
class Agent
{

    //代理申请
    public function apply(Request $request){
        $rsps=(new AgentFactory($request->token->uid))->apply_run();
        return Respons::run($rsps);
    }

    //代理状态
    public function apply_status(Request $request){
        $rsps=(new AgentFactory($request->token->uid))->apply_status_run();
        return Respons::run($rsps);
    }

    //代理中心
    public function core(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->core_run();
        return Respons::run($rsps);
    }

    /* 
     * 团队列表（用户）
     */
    public function user(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->user_run();
        return Respons::run($rsps);
    }

    /* 
     * 团队用户明细
     */
    public function userdetail(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->userdetail_run();
        return Respons::run($rsps);
    }

    /*
     * 提现统计
     */
    public function statcash(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->statcash_run();
        return Respons::run($rsps);
    }

    /*
     * 提现列表
     */
    public function cashlist(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->cashlist_run();
        return Respons::run($rsps);
    }

    /*
     * 充值统计
     */
    public function statrecharge(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->statrecharge_run();
        return Respons::run($rsps);
    }

    /*
     * 充值列表
     */
    public function rechargelist(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->rechargelist_run();
        return Respons::run($rsps);
    }

    /*
     * 注册统计
     */
    public function statreg(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->statreg_run();
        return Respons::run($rsps);
    }

    /*
     * 注单列表
     */
    public function orderlist(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->orderlist_run();
        return Respons::run($rsps);
    }

    /*
    * 注单人数统计
    */
    public function ordernum(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->ordernum_run();
        return Respons::run($rsps);
    }

    /*
     * 注单统计
     */
    public function statorder(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->statorder_run();
        return Respons::run($rsps);
    }

    /*
     * 盈利统计1
     */
    public function statprofit(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->statprofit_run();
        return Respons::run($rsps);
    }

    /*
     * 财务报表
     */
    public function report(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->report_run();
        return Respons::run($rsps);
    }

    /*
     * 代理佣金发放记录
     */
    public function log(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->log_run();
        return Respons::run($rsps);
    }

    /*
     * 推广佣金
     */
    public function popularize(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->popularize_run();
        return Respons::run($rsps);
    }

    //获取充值返利
    public function getRechargeRebate(Request $request){
        $result = (new AgentFactory($request->token->uid))->getRechargeRebate();
        return Respons::run($result);
    }

    //获取团队报酬列表
    public function getRemuneration(Request $request){
        $result = (new AgentFactory($request->token->uid))->getRemuneration();
        return Respons::run($result);
    }

    //领取推广奖金
    public function receiveInviteRebate(Request $request){
        $result = (new AgentFactory($request->token->uid))->receiveInviteRebate();
        return Respons::run($result);
    }

    //获取推广排行榜
    public function getRankingRecord(Request $request){
        $result = (new AgentFactory($request->token->uid))->getSellRankingRecord();
        return Respons::run($result);
    }

    /*
     * 推广码
     */
    public function promotion(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->promotion_run();
        return Respons::run($rsps);
    }

    /*
     * 二维码
     */
    public function qrcode(Request $request){
        $rsps=(new AgentFactory())->qrcode_run();
        return response($rsps, 200, ['Content-Length' => filesize($rsps)])->contentType('image/png');
    }

    //获取用户投注情况
    public function user_order(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->user_order_run();
        return Respons::run($rsps);
    }

    /*
     * 统计代理佣金收益
     */
    public function profit(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->profit_run();
        return Respons::run($rsps);
    }

    //团队介绍
    public function view(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->teamView();
        return Respons::run($rsps);
    }

    //佣金列表
    public function commission(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->commissionList();
        return Respons::run($rsps);
    }

    //反水列表
    public function back(Request $request){
        $rsps=(new AgentFactory($request->token->uid,$request->agent))->backList();
        return Respons::run($rsps);
    }

}