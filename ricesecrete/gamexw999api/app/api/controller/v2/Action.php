<?php

namespace app\api\controller\v2;

use app\api\factory_v2\ActionFactory;
use app\common\lib\Respons;
use think\Request;

class Action
{

    //救援金
    public function jyjdetail(Request $request)
    {
        $rsps = (new ActionFactory($request->token->uid))->jyjdetail_run();
        return Respons::run($rsps);
    }

    //现金返还领取
    public function apply(Request $request)
    {
        $rsps = (new ActionFactory($request->token->uid))->apply_run();
        return Respons::run($rsps);
    }
}