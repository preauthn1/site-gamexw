<?php

namespace app\api\controller\v2;

use app\api\factory_v2\AffiliateFactory;
use app\common\lib\Respons;
use think\Request;

//附属机构
class Affiliate
{
    private $param;//请求参数


    public function __construct(){
        $this->param=\think\facade\Request::param();
    }

    /**
     * 控制板接口
     * @param Request $request
     * @return \think\response\Json
     */
    public function summary(Request $request)
    {
        $rsps = (new AffiliateFactory($request->token->uid))->summary_run();
        return Respons::run($rsps);
    }

    /**
     * 报告统计接口
     * @param Request $request
     * @return \think\response\Json
     */
    public function report(Request $request)
    {
        $rsps = (new AffiliateFactory($request->token->uid))->report_run();
        return Respons::run($rsps);
    }

    /**
     * 常问问题接口
     * @param Request $request
     * @return \think\response\Json
     */
    public function faq(Request $request)
    {
        $rsps = (new AffiliateFactory($request->token->uid))->faq_run();
        return Respons::run($rsps);
    }


    /**
     * 教程接口
     * @param Request $request
     * @return \think\response\Json
     */
    public function course(Request $request)
    {
        $rsps = (new AffiliateFactory($request->token->uid))->course_run();
        return Respons::run($rsps);
    }
}