<?php
namespace app\api\controller;
use app\api\factory\JackpotFactory;
use app\common\lib\Respons;
use think\Request;

class Jackpot
{	
	
	//排行榜列表
	public function lists(Request $request){
        $rsps=(new JackpotFactory())->list_run();
        return Respons::run($rsps);
	}

	//规则详情
	public function detail(Request $request){
		$rsps=(new JackpotFactory())->detail_run();
		return Respons::run($rsps);
	}

    //我的奖励
    public function rewards(Request $request){
        $rsps=(new JackpotFactory($request->token->uid))->rewards_run();
        return Respons::run($rsps);
    }

    //排行榜配置状态
    public function config(Request $request){
        $rsps=(new JackpotFactory())->config_run();
        return Respons::run($rsps);
    }

}