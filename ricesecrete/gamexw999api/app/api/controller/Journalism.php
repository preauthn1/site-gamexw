<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\JournalismFactory;
use think\Request;
class Journalism
{
    //分类列表
    public function type(Request $request){
        $rsps = (new JournalismFactory())->type_run();
        return json($rsps);
    }

    //新闻列表
    public function list(Request $request){
        $rsps = (new JournalismFactory())->list_run();
        return json($rsps);
    }

    //新闻详情
    public function details(Request $request){
        $rsps = (new JournalismFactory())->details_run();
        return json($rsps);
    }
}