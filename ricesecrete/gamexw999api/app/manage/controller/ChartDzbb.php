<?php
namespace app\admin\controller;
use app\common\model\ArtificialBalanceModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\UserModel;
use app\common\model\UserReportModel;
use app\common\lib\Respons;
use app\common\Utils;
use think\facade\View;
use think\facade\Request;
use think\facade\Db;
class ChartDzbb
{	
	protected $middleware = ['adminCheckLogin',"Language"];

    //列表
    public function list(){
        $param=Request::param();
        if (isset($param['select'])) {
            $page=$param['page'];
            $limit=$param['limit'];
            $where=[];
            $where1=[];
            $where2=[];
            if (isset($param['username']) && $param['username']!='') {
                $where[]=["b.username","like","%".$param['username']."%"];
            }
            if (isset($param['rec_code']) && $param['rec_code']!='') {
                $where[]=["b.rec_code","like","%".$param['rec_code']."%"];
            }
            if (isset($param['date']) && $param['date']!='') {
                $date=explode(' - ',$param['date']);
            }else{
                $date = [date("Y-m-d"), date("Y-m-d")];
            }
            $where[]=["a.start_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.start_time","<=", Utils::getQueryDateTime($date[1], true)];

            $rsps['data'] = UserReportModel::alias("a")
	            ->join("dq_user b","a.uid=b.id")
	            ->where($where)
	            ->group("a.uid")
	            ->field("a.uid, b.username, b.rec_code, SUM(a.zd_number) as zd_number, SUM(a.zd_amount) as zd_amount,SUM(a.sy_amount) as sy_amount,SUM(a.trans_fee) as trans_fee,SUM(a.adjust_add) as adjust_add,SUM(a.adjust_reduce) as adjust_reduce,SUM(a.yh_amount) as yh_amount,SUM(a.agent_commission) as agent_commission,SUM(a.share_commission) as share_commission")
                ->paginate($limit,false,['query' => $param]);
	        

            $rsps['code']=1;
            return Respons::run($rsps);
        }
        if (isset($param['tz'])) {
        	$rsps=$this->tz_data($param);
	        return Respons::run($rsps);
        }
        return View::fetch('chart_dzbb/list');
    }
}
