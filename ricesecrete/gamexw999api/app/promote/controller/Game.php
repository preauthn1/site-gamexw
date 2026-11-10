<?php
namespace app\promote\controller;

use app\common\service\BetService;
use app\manage\factory\GameFactory;
use app\common\model\{GameTypeModel, UserAdminLogModel, GameBetsModel, UserModel};
use think\facade\{Session, View, Request, Config, Db};
use app\common\redis\CommomRedis;
use app\common\lib\Respons;

class Game extends Base
{	
    public function report(){
        $agent = $this->user_agent;
        $sql = UserModel::where([['tree','like', $agent['tree'].'%']])->field('id')->buildSql();
        $whereStr = 'gb_uid in ('.$sql .')';
        return (new GameFactory())->report($whereStr);
    }

    public function clear(){
        $param = Request::param();
        $key = $param['key'];
        $CommomRedis = new CommomRedis();
        if ($key == 'all') {
            $CommomRedis->clearTag((new GameFactory())->tag);
        } else {
            $CommomRedis->delete($key);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function user_report(){
        $param=Request::param();
        $month = Db::name('third_stats_fyfy')->order("sff_m desc")->group("sff_m")->column("sff_m");

        $page=$param['page'];
        $limit=$param['limit'];
        $where=[];
        $where[] = ['tree','like', "{$this->user['tree']}%"];

        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $where[]=["su_dt",">=", date("Ymd",strtotime($time[0]))];
            $where[]=["su_dt","<=", date("Ymd",strtotime($time[1]))];
        }

        if(!empty($param['username'])){
            $uids = UserModel::where([['id|username','=',$param['username']]])->column("id");
            $where[] = ["su_uid","in", $uids];
        }
        $tables = BetService::getThirdStatsTable();
//        $tables['_stats_users']
        $rsps['count'] = Db::name($tables['_stats_users'])->where($where)->count();
        $lists = Db::name($tables['_stats_users'])->where($where)
            ->limit((($page-1)*$limit),$limit)
            ->order("su_dt desc")
            ->select();
        if($lists){
            $lists = $lists->toArray();
        }
        $rsps['data'] = $lists;
        $rsps['where'] = $where;
        $rsps['code']=1;
        return Respons::run($rsps);
//        View::assign("month",$month);
//        return View::fetch('game/user_report');
    }

}