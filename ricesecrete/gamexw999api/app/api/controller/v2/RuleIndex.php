<?php

namespace app\api\controller\v2;

use app\api\factory_v2\RuleIndexFactory;
use app\common\lib\Respons;
use think\Request;

//首页规则
class RuleIndex
{
    //规则列表
    public function lists(Request $request)
    {
        $rsps = (new RuleIndexFactory())->list_run();
        return Respons::run($rsps);
    }

    //规则详情
    public function detail(Request $request)
    {
        $rsps = (new RuleIndexFactory())->detail_run();
        return Respons::run($rsps);
    }

}