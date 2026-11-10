<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\ServiceFactory;
use think\Request;
class Service
{

    //公告列表
    public function lists(Request $request){
        $rsps=(new ServiceFactory())->list_run();
        return Respons::run($rsps);
    }

}