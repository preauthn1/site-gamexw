<?php
namespace app\api\controller;
use app\common\lib\Respons;
use app\api\factory\NewsFactory;
use think\Request;
class News
{	
	
    //问答列表
    public function lists(Request $request){
        $rsps=(new NewsFactory())->list_run();
        return Respons::run($rsps);
    }
	

    //问答详情
    public function detail(Request $request){
        $rsps=(new NewsFactory())->detail_run();
        return Respons::run($rsps);
    }


    public function type_list(Request $request){
        $rsps=(new NewsFactory())->type_list_run();
        return Respons::run($rsps);
    }

    public function teach_list(Request $request){
        $rsps=(new NewsFactory())->teach_list_run();
        return Respons::run($rsps);
    }

    public function agent_list(Request $request){
        $rsps=(new NewsFactory())->agent_list_run();
        return Respons::run($rsps);
    }
	
}