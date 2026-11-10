<?php

namespace app\api\controller\v2;

use app\api\factory_v2\GameFactory;
use app\common\FastGame\Base\BaseController;
use think\Request;

class Game extends BaseController
{
    /**
     * 游戏首页
     * @param Request $request
     * @return \think\response\Json
     */
    public function index(Request $request)
    {
        $rsps = (new GameFactory())->index_run();
        return json($rsps);
    }

    /**
     * 游戏分类列表
     * @param Request $request
     * @return \think\response\Json
     */
    public function game_list(Request $request){
        $rsps = (new GameFactory())->game_list();
        return json($rsps);
    }


    /**pwa 应用
     * @param Request $request
     * @return \think\response\Json
     */
    public function pwaapp(Request $request)
    {
        $rsps = (new GameFactory())->pwaapp_run();
        return json($rsps);
    }
}
