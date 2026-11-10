<?php
namespace app\api\middleware;
use app\common\model\UserAgentModel;
class Agent
{	
    /**
    * @param $codes 自定义返回code
    */
    public function handle($request, \Closure $next)
    {	
        $agent=UserAgentModel::where("uid",$request->token->uid)->find();
       	if (!isset($agent['id'])) {
            throw new \app\common\exception\MessageException("请先申请成为代理商", 103);
       	}
       	if ($agent['status']==1) {
            throw new \app\common\exception\MessageException("请等待审核！", 104);
       	}
       	if ($agent['status']==2) {
            throw new \app\common\exception\MessageException("审核未通过！", 105);
       	}
       	$request->agent=$agent;
    	return $next($request);
    }
}