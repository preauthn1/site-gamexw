<?php
namespace app\api\controller;
use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\api\factory\LoginFactory;
use think\facade\Request;

class Pages
{

    public function index(){
        $base_url = getAppUrl();
        $path = Request::pathinfo();
        if (empty($path)){
            echo "page error";exit();
        }
        return redirect($base_url.'/#/'.$path);
    }
}