<?php

namespace app\api\controller\v2;

use app\common\lib\Respons;
use app\api\factory_v2\NewsFactory;
use think\Request;

//常见问题
class News
{
    //问答列表
    public function lists(Request $request)
    {
        $rsps = (new NewsFactory())->list_run();
        return Respons::run($rsps);
    }

    //问答详情
    public function detail(Request $request)
    {
        $rsps = (new NewsFactory())->detail_run();
        return Respons::run($rsps);
    }

}