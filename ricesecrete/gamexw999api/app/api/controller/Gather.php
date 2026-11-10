<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\GatherFactory;
use think\Request;
class Gather
{	
    public function index(Request $request){
        (new GatherFactory())->getToken();
    }	
}