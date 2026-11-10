<?php
namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\ThirdCostLogsModel;
use think\facade\Session;
use think\Request;

/**
 * 三方服务费预储值相关接口
 */
class ThirdCost extends Base
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    /**
     *  上分
     * @param Request $request 【monthclear是否月清 1月清 price 上分金额】
     * @return void
     */
    public function upcost(Request $request){
        $param=$request->param();
        $monthclear=isset($param['monthclear'])?$param['monthclear']:false;
        $monthclear=$monthclear==1?true:false;
        $price=isset($param['price'])?floatval($param['price']):false;
        if ($price){
            $rlt=ThirdCostLogsModel::upCost($price,Session::get("adminLog.admin_name"),$monthclear);
            return Respons::run($rlt?1:0);
        }else{
            return Respons::run(0,"上分金额不正确");
        }
    }

    /** 获取当前分值状态
     * @return void 【cost当前分 money当前金额，由分按比率转换】
     */
    public function getstatus(){
        $cost=ThirdCostLogsModel::getCost();
        $money=ThirdCostLogsModel::transToCost($cost,1);
        return Respons::run(1,[
            'cost'=>$cost,
            'money'=>$money,
            'rate'=>ThirdCostLogsModel::getRate()
        ]);
    }

    /** 获取分值变更列表
     * @param Request $request [page,limit,type:[all-全部 0节点 1上分 2月清]]
     * @return void
     */
    public function getlist(Request $request){
        $param= $request->param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $type=in_array($param['type'].'',['all','0','1','2'])?$param['type']:'all';
        return Respons::run(ThirdCostLogsModel::getlist($type,$page,$limit));
    }
}