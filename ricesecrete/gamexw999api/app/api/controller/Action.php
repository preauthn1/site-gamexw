<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\ActionFactory;
use think\Request;
class Action
{	
	
	//活动列表
	public function lists(Request $request){
            $rsps=(new ActionFactory(isset($request->token) ? $request->token->uid : 0))->list_run();
            return Respons::run($rsps);
	}


	//活动详情
	public function detail(Request $request){
		$rsps=(new ActionFactory($request->token->uid))->detail_run();
		return Respons::run($rsps);
	}

	//活动申请
	public function apply(Request $request){
		$rsps=(new ActionFactory($request->token->uid))->apply_run();
		return Respons::run($rsps);
	}

    //抽奖配置
    public function lotteryconfig(Request $request){
        $rsps=(new ActionFactory($request->token->uid))->lotteryconfig_run();
        return Respons::run($rsps);
    }
    //抽奖配置列表
    public function lotteryconfiglist(Request $request){
        $rsps=(new ActionFactory($request->token->uid))->lotteryconfiglist_run();
        return Respons::run($rsps);
    }

    //中奖记录
    public function lotteryrecord(Request $request){
        $rsps=(new ActionFactory($request->token->uid))->lotteryrecord_run();
        return Respons::run($rsps);
    }

    //领取抽奖次数
    public function receiveLotteryCount(Request $request){
        $rsps=(new ActionFactory($request->token->uid))->receiveLotteryConditionCount_run();
        return Respons::run($rsps);
    }

    //抽奖
    public function lottery(Request $request){
        $rsps=(new ActionFactory($request->token->uid))->lottery_run();
        return Respons::run($rsps);
    }

    //活动统计
    public function activity_statistics(Request $request){
        $rsps=(new ActionFactory($request->token->uid))->activity_statistics_run();
        return Respons::run($rsps);
    }

    //救援金
    public function jyjlist(Request $request){
        $rsps=(new ActionFactory($request->token->uid))->jyjlist_run();
        return Respons::run($rsps);
    }
}