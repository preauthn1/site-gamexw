<?php
namespace app\admin\controller;

use app\api\factory\GameFactory;
use app\common\Utils;
use app\common\model\{GameSetModel, GameZhudanModel};
use app\common\lib\{Respons, Util};
use think\facade\{Config, View, Db};
use think\db\Where;
use think\Exception;
use think\Request;

class GameZhudan
{
    protected $middleware = ['adminCheckLogin',"Language"];

    //列表
//    public function list(){
//        $param=Request::param();
//        if (isset($param['select'])) {
//            $page=$param['page'];
//            $limit=$param['limit'];
//            $where=[];
//
//            if (isset($param['add_time']) && $param['add_time']!='') {
//                $date=explode(' - ',$param['add_time']);
//                $where[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
//                $where[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
//            }
//
//            if (isset($param['zd_id']) && $param['zd_id']!=''){
//                $where[]=["a.id","=",$param['zd_id']];
//            }
//            if (isset($param['username']) && $param['username']!=''){
//                $where[]=["a.username","=",$param['username']];
//            }
//            if (isset($param['game_name']) && $param['game_name']!=''){
//                $where[]=["a.game_name","=",$param['game_name']];
//            }
//            //正式用户
//            $where[]=["a.lebal","=",1];
//
//            $GameZhudanModel =new GameZhudanModel();
//            $rsps['count']=$GameZhudanModel
//                ->alias("a")
//                ->where($where)
//                ->count();
//            $rsps['data']=$GameZhudanModel
//                ->alias("a")
//                ->where($where)
//                ->limit((($page-1)*$limit),$limit)
//                ->order("id desc")
//                ->select();
//            if(isset($rsps['data'][0])){
//                foreach ($rsps['data'] as $k=>$v){
//                    $rsps['data'][$k]['add_time'] = date('Y-m-d H:i:s',$v['add_time']);
//                    $rsps['data'][$k]['begin_time'] = date('Y-m-d H:i:s',$v['begin_time']);
//                    $rsps['data'][$k]['end_time'] = date('Y-m-d H:i:s',$v['end_time']);
//                }
//            }
//            $rsps['code']=1;
//            return Respons::run($rsps);
//        }
//        return View::fetch('game_zhudan/list');
//    }

    /**
     * 第三方游戏注单列表
     */
    public function list_old(){
        $param=Request::param();
        if (isset($param['select'])) {

            $page=$param['page'];
            $limit=$param['limit'];
            $where=[];

            if (isset($param['add_time']) && $param['add_time']!='') {
                $date=explode(' - ',$param['add_time']);
                $where[]=["add_time",">=", Utils::getQueryDateTime($date[0])];
                $where[]=["add_time","<=", Utils::getQueryDateTime($date[1], true)];
            }

            if (isset($param['zd_id']) && $param['zd_id']!=''){
                $where[]=["id","=",$param['zd_id']];
            }
            if (isset($param['username']) && $param['username']!=''){
                $where[]=["username","=",$param['username']];
            }
            if (isset($param['game_name']) && $param['game_name']!=''){
                $where[]=["game_name","=",$param['game_name']];
            }
            //正式用户
            $where[] = ["lebal","=",1];

            $start_time = strtotime('-7 days');
            $end_time = strtotime('+1 days');
            $get_start_time = input('start_time') ? strtotime(input('start_time')) : '';
            $get_end_time = input('end_time') ? strtotime(input('end_time')) + 24 * 3600 : '';
            if ($get_start_time)
                $start_time = $get_start_time;
            if ($get_end_time)
                $end_time = $get_end_time;
            $field = "*";
            $tables = [];
            for ($i = $start_time; $i <= $end_time; $i+=86400) {
                $full_table_name = "dq_game_zhudan_" . Util::timeToStr($i, "Ymd");
                if (Util::existTable($full_table_name)) {
                    $tables[] = $full_table_name;
                }
            }

            $sqls = [];
            for($i=0; $i < count($tables); $i++) {
                $sqls[] = Db::table($tables[$i])->fetchSql()->where($where)->SELECT();
            }

            $db_lists = Db::table('dq_game_zhudan');
            if (!empty($sqls)) {
                $db_lists = $db_lists->union($sqls, true);
            }
            $data = $db_lists->field($field)->where($where)->limit((($page-1)*$limit), $limit)->order('id desc')->select();
            $rsps['data'] = json_decode(json_encode($data),true);

            if(isset($rsps['data'][0])){
                foreach ($rsps['data'] as $k=>$v){
                    $rsps['data'][$k]['add_time'] = date('Y-m-d H:i:s',$v['add_time']);
                    $rsps['data'][$k]['begin_time'] = date('Y-m-d H:i:s',$v['begin_time']);
                    $rsps['data'][$k]['end_time'] = date('Y-m-d H:i:s',$v['end_time']);
                }
            }
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        return View::fetch('game_zhudan/list');
    }

    /**
     * 三方游戏注单列表
     * @return string|\think\response\Json
     */
    public function list(Request $request){
        $param = $request->param();
        $day = [date("Ymd", time()), date("Ymd", time())];
        if (isset($param['select'])) {
            $limit = $param['limit'];
            if(isset($param['add_time']) && !empty($param['add_time'])){
                $day = explode(' - ', $param['add_time']);
            }
            $date_info = $this->getEveryDay($day[0], $day[1]);

//            $day = date('Ymd', strtotime($day));
            $tables = [];
            $table = 'dq_game_zhudan_';
            foreach($date_info as $v){
                if(existTable($table."{$v}")){
                    $tables[] = $table."{$v}";
                }
            }
            if(count($tables) == 0){
                return Respons::run(0, [], lang('暂无数据'));
            }

            $where = [];
            if((isset($param['keyword']) && !empty($param['keyword']))){
                $where[] = ['a.username|a.zd_id','like',"%{$param['keyword']}%"];
            }
            if((isset($param['game_sys_sign']) && !empty($param['game_sys_sign']))){
                $where[] = ['b.game_sys_sign', '=', $param['game_sys_sign']];
            }
            if((isset($param['type']) && !empty($param['type']))){
                $where[] = ['b.reward_coin' , '>' , 0];
            }
            if(isset($param['game_code']) && !empty($param['game_code'])){
                $where[] = ['b.game_code', '=', $param['game_code']];
            }
            try{
                $sqls = [];
                foreach($tables as $k => $v){
                    if($k > 0){
                        $sqls[] = Db::table($v)->alias('a')
                            ->where($where)
                            ->field('a.*, b.game_sys_sign')
                            ->join('game_set b','b.id = a.game_id', 'LEFT')->buildSql();
                    }
                }
                $sql = Db::table($tables[0])->alias('a')
                    ->where($where)
                    ->field('a.*, b.game_sys_sign')
                    ->join('game_set b','b.id = a.game_id', 'LEFT')
                    ->when(count($sqls) > 0, function ($query) use($sqls){
                        $query->unionAll($sqls);
                    })
                    ->buildSql();
                $data_sql = Db::table($sql. 'as a')->order('a.id desc');

                $data = $data_sql
                    ->filter(function($data){
                        $data['add_time'] = $data['add_time'] ? date('Y-m-d H:i:s', $data['add_time']) : '';
                        $data['end_time'] = $data['end_time'] ? date('Y-m-d H:i:s', $data['end_time']) : '';
                        $data['begin_time'] = $data['begin_time'] ? date('Y-m-d H:i:s', $data['begin_time']) : '';
                        return $data;
                    })
                    ->paginate($limit,false,['query' => $param]);
                $count_win = $data_sql->sum('reward_coin');
                $count_bet = $data_sql->sum('bets_coin');
                $code = 1;
                return Respons::run(compact('code','data', 'count_win', 'count_bet'));
            }catch(\think\exception\ValidateException $e) {
                return Respons::run(0,[],$e->getError());
            }

        }
        $arr = [0];
        $type = array_diff(config::get('custom.three_game'),$arr);
        $group = config::get('custom.three_game_group');
        $data = [];
        foreach($type as $k => $v){
            $data[$k] = $group[$k];
        }
        View::assign('type', $data);
        View::assign('day', implode(' - ' ,$day));
        return View::fetch('game_zhudan/list');

    }


    /**
     * 获取两个日期中的每一天
     * @param $startDate
     * @param $endDate
     * @param $format
     * @return array
     */
    function getEveryDay($startDate, $endDate, $format = "Ymd"): array
    {
        $startDate = strtotime($startDate);
        $endDate = strtotime($endDate);
        $arr = [];
        for ($i = $startDate; $i <= $endDate; $i += 86400)
        {
            $arr[] = date($format, $i);
        }
        return $arr;
    }
}