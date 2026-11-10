<?php

namespace app\api\controller\v2;

use app\api\factory_v2\UserOfflineFactory;
use app\common\lib\Respons;
use think\Request;

class UserOffline
{

    /**
     * 获取充值配置参数
     * @param Request $request
     * @return \think\response\Json
     */
    public function recharge_config(Request $request)
    {
        $rsps = (new UserOfflineFactory($request->token->uid))->recharge_config_run();
        return Respons::run($rsps);
    }

    //充值
    public function apply(Request $request){
        $rsps=(new UserOfflineFactory($request->token->uid))->apply_run();
        return Respons::run($rsps);
    }
}