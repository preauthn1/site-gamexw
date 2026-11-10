<?php

namespace app\api\controller\v2;

use app\api\factory_v2\IndexFactory;
use app\common\lib\Respons;
use think\Request;

class Index
{
    /**
     * 首页菜单列表
     * @return \think\response\Json
     */
    public function menu_list(Request $request)
    {
        $rsps = (new IndexFactory($request->token && $request->token->uid ? $request->token->uid : 0))->menu_run();
        return Respons::run($rsps);
    }
}
