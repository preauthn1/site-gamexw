<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\manage\factory\ChartCaiwuFactory;
use think\facade\View;
use think\facade\Request;
use think\facade\Db;
class ChartCaiwu
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(\think\Request $request){
        $rsps = (new ChartCaiwuFactory($request->token->uid))->list();
        return $rsps;
    }
}
