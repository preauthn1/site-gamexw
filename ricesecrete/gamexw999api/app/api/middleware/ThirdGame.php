<?php
namespace app\api\middleware;

use app\common\FastGame\Base\Third;
use app\model\ThirdConfigs;
use app\model\Games;
use think\facade\{Request, Log};

class ThirdGame
{

    /**
     * @param $codes 自定义返回code
     */
    public function handle($request, \Closure $next)
    {
        $param = $request->param();
        //区分集成三方
        $tccode=strtoupper($param['third']);
        $setcode=false;
        if (substr($tccode,0,5)=='SETRD'){
            $setcode=substr($tccode,5);
        }
        if ($setcode){
            $apicfgs=[
                'trdcfg'=>[
                    'tc_set_code'=>$setcode
                ]
            ];
            $setrd_datas=[
                'request'=>$request,
                'action_method'=>$param['action_method'] ?? null,
                'header'=>Request::header(),
                'param'=>Request::param()
            ];
            $thirdpreobj=Third::initExtClass($apicfgs,$setrd_datas);
            $thinfo=$thirdpreobj?$thirdpreobj->apicfgs['trdcfg']:null;
        }else {
            $thinfo = ThirdConfigs::where(['tc_code' => strtoupper($param['third'])])->find();
        }
        if(empty($thinfo)){
            $rsps['status'] = 501;
            $rsps['msg'] = "third not exist";
            return json($rsps);
        }
        if (!is_array($thinfo)){
            $thinfo = $thinfo->toArray();
        }
        
        $request->tc_id = $thinfo['tc_id'];
        $request->action_method = $param['action_method'] ?? null;
        $request->header = Request::header();
        $request->param = Request::param();
        return $next($request);
    }
}
