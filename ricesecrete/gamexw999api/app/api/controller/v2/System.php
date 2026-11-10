<?php

namespace app\api\controller\v2;

use app\api\factory_v2\SystemFactory;
use app\common\lib\Respons;
use think\Request;

class System
{
    //系统配置
    public function info(Request $request)
    {
        $rsps = (new SystemFactory())->info_run();
        return Respons::run($rsps);
    }
}