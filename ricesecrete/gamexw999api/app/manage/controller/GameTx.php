<?php
namespace app\admin\controller;

use app\api\factory\GameFactory;
use app\common\model\{GameSetModel, GameTicketModel, GameTxModel, GameUserModel, PaySetModel, UserModel};
use app\common\lib\Respons;
use app\common\Utils;
use think\db\Where;
use think\Exception;
use think\facade\{Config, Request, View, Queue};


class GameTx
{
    protected $middleware = ['adminCheckLogin',"Language"];

    //列表
    public function old_list(){
        $param=Request::param();
        $day = date("Y-m-d", time());
        if (isset($param['select'])) {
            $page = $param['page'];
            $limit = $param['limit'];
            $where = [];
            $GameTxModel = new GameTxModel();
            if (isset($param['txid']) && $param['txid']!=''){
                $where[]=["a.txid","=",$param['txid']];
            }
            if (isset($param['ref_id']) && $param['ref_id']!=''){
                $where[]=["a.ref_id","=",$param['ref_id']];
            }
            if (isset($param['uid']) && $param['uid']!=''){
                $where[]=["c.id","=",$param['uid']];
            }
            if (isset($param['username']) && $param['username']!=''){
                $where[]=["c.username","=",$param['username']];
            }
            if (isset($param['game_sys_sign']) && $param['game_sys_sign']!=''){
                $where[]=["b.game_sys_sign","=",$param['game_sys_sign']];
            }

            $rsps['data'] = $GameTxModel
                ->alias("a")
                ->join("dq_game_set b","a.game_id=b.id")
                ->join("dq_user c","c.id=a.uid")
                ->where($where)
                ->when(isset($param['add_time']) && $param['add_time']!='', function($query) use($param){
                    $time = strtotime($param['add_time']) ;
                    $query->whereTime('a.add_time', 'between', [$time, $time + 86399]);
                })
                ->order("id desc")
                ->field("a.*,b.game_name as game_platform,c.username")
                ->paginate($limit,false,['query' => $param]);
            if(isset($rsps['data'][0])){
                foreach ($rsps['data'] as $k=>$v){
                    if($v['game_name'] == 'Pilot'){
                        $rsps['data'][$k]['bet_time'] = $v['add_time'];
                        $rsps['data'][$k]['update_time'] = $v['add_time'];
                    }
                }
            }
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        $sys = Utils::sysInfo();
        View::assign("domain",$sys['domain']);
        return View::fetch('game_tx/list');
    }

    //列表
    public function list(){
        $param = Request::param();
        $day = date("Y-m-d", time());
        if (isset($param['select'])) {
            $limit = $param['limit'];
            $day = isset($param['add_time']) ?  date("Y-m-d",strtotime($param['add_time'])) : $day;
            $day = date('Ymd', strtotime($day));
            /* @var GameTxModel $GameTxModel*/
            $GameTxModel = app()->make(GameTxModel::class);
            $model = $GameTxModel->setSuffix("_{$day}");
            //先校验该数据表是否存在
            try{
                $model->where(['id'=>1])->find();
            }catch(Exception $e){
                return Respons::run(0, [], lang('暂无数据'));
            }

            $where = [];
            if (isset($param['keyword']) && $param['keyword']!=''){
                $where[]=["a.tx_number|a.ref_id|c.id|c.username","like","%{$param['keyword']}%"];
            }
            if (isset($param['game_sys_sign']) && $param['game_sys_sign']!=''){
                $where[]=["b.game_sys_sign","=",$param['game_sys_sign']];
            }
            if (isset($param['game_code']) && $param['game_code']!=''){
                $where[]=["b.game_code","=",$param['game_code']];
            }
            try{
                $rsps['data'] = $model
                ->alias("a")
                ->join("dq_game_set b","a.game_id=b.id")
                ->join("dq_user c","c.id=a.uid")
                ->where($where)
                ->order("id desc")
                ->field("a.*,b.game_name as game_platform,c.username,b.game_sys_sign")
                ->paginate($limit,false,['query' => $param]);
                if(isset($rsps['data'][0])){
                    foreach ($rsps['data'] as $k=>$v){
                        if($v['game_name'] == 'Pilot'){
                            $rsps['data'][$k]['bet_time'] = $v['add_time'];
                            $rsps['data'][$k]['update_time'] = $v['add_time'];
                        }
                    }
                }
                $rsps['code'] = 1;
                return Respons::run($rsps);
            }catch(\think\exception\ValidateException $e) {
                return Respons::run(0,[],$e->getError());
            }

        }
        $arr = [0];
        $tx_arr = Config::get('custom.sports');
        $type = array_diff(config::get('custom.three_game'), $arr);
        $group = config::get('custom.three_game_group');
        $data = array_flip(array_intersect(array_keys($type), $tx_arr));
        $info = [];
        foreach($data as $k => $v){
            $info[$k] = $group[$k];
        }
        $sys = Utils::sysInfo();
        View::assign('type', $info);
        View::assign('day', $day);
        View::assign("domain",$sys['domain']);
        return View::fetch('game_tx/list');
    }





    //编辑
    public function old_detail(){
        $param=Request::param();
        if (isset($param['find'])) {
            $GameTxModel = new GameTxModel();
            $res=$GameTxModel->where("id",$param['id'])->find();
            $ref_id = $res['ref_id'];
            $GameTicketModel = new GameTicketModel();
            $res['extra'] = $res['extra']?json_decode($res['extra'],true):[];
            $res['detail'] = $res['detail']?json_decode($res['detail'],true):[];
            $res['tickets'] = $GameTicketModel->where("ref_id",$ref_id)->select();
            $res['game_user'] = GameUserModel::where("uid",$res['uid'])->field("member_id,username,add_time")->find();
            return Respons::run(1,$res);
        }
        View::assign("id",$param['id']);
        return View::fetch('game_tx/detail');
    }

    //体育订单详情
    public function detail(){
        $param = Request::param();
        if(!isset($param['add_time']) || !isset($param['id'])){
            return Respons::run(0, [], lang('参数异常'));
        }
        $day = date('Ymd', strtotime($param['add_time']));
        $id = $param['id'];
        /* @var GameTxModel $GameTxModel*/
        $GameTxModel = app()->make(GameTxModel::class);
        $model = $GameTxModel->setSuffix("_{$day}");
        try{
            $data = $model
                ->where(['id' => $id])
                ->with('User')
                ->filter(function($data){
                    $data['extra'] = unserialize($data['extra']);
                    switch ($data['tx_type']){
                        case 0 :
                            $data['tx_type_text'] = lang('单张注单');
                            break;
                        case 1 :
                            $data['tx_type_text'] = lang('上分/串关注单');
                            break;
                        case 2 :
                            $data['tx_type_text'] = lang('下分/三方注单');
                            break;
                    }
                })
                ->find();
            $key = $model->extra;
            $value = [];
            $detail = $data['extra'];
            foreach($key as $k => $v){
                foreach($detail as $ke => $val){
                    if(!isset($val[$k])){
                        unset($key[$k]);
                        continue;
                    }
                    $value[$ke][$k] = $val[$k];
                    if($k == 'match_start_time' || $k == 'match_end_time' ){
                        if($value[$ke][$k]){
                            $value[$ke][$k] = date('Y-m-d H:i:s', $value[$ke][$k]);
                        }
                    }
                }
            }
        }catch(Exception $e){
            return Respons::run(0, [], $e->getMessage());
        }
        View::assign("id", $param['id']);
        View::assign("data", $data);
        View::assign("key", $key);
        View::assign("detail", $value);
        return View::fetch('game_tx/new_detail');
    }

    public function tickets(){
        $param=Request::param();
        $GameTxModel = new GameTxModel();
        $res=$GameTxModel->where("id",$param['id'])->find();
        $ref_id = $res['ref_id'];
        $GameTicketModel = new GameTicketModel();
        $rsps['count']=$GameTicketModel->where("ref_id",$ref_id)->count();
        $rsps['data'] = $GameTicketModel->where("ref_id",$ref_id)->select();
        $rsps['code']=1;
        return Respons::run($rsps);

    }
}