<?php

namespace app\api\controller;

use app\api\factory\AdvertisingFactory;
use app\common\lib\Respons;
use think\Request;

class Advertising
{
    //广告详情
    public function detail(Request $request)
    {
        $mode = $request->param('mode');
        $rsps = [
            'code' => 0,
            'data' => [],
        ];
        switch ($mode) {
            case "advertising":
                $rsps = (new AdvertisingFactory($request->token->uid))->detail_run();
                break;
        }
        return Respons::run($rsps);
    }
}