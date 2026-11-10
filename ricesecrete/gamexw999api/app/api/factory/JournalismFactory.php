<?php
namespace app\api\factory;
use app\common\model\JournalismModel;
use app\common\model\JournalismTypeModel;
use app\api\redis\ApiRedis;
use think\facade\Request;
class JournalismFactory
{
    private $param;//请求参数

    //验证
    public function __construct(){
        $this->param=Request::param();
    }

    //分类列表
    public function type_run(){
        $data = JournalismTypeModel::where(['status'=>1])->field('id,name')->order('sort desc')->select();
        $rsps['code']=1;
        $rsps['data']=$data;
        return $rsps;
    }


    //新闻列表
    public function list_run(){
        $pagenow = $this->param['page']??1;
        $limit = $this->param['limit']??10;
        $type_id = $this->param['type_id']??0;
        $isAmount = $this->param['is_amount']??0;
        $offset = ($pagenow - 1) * $limit;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $where = [];
        $where[] = ["status", "=", 1];
        if($type_id > 0){
            $where[] = ["type_id", "=", $type_id];
        }
        if(!empty($lang)){
            $where[] = ["lang", "=", $lang];
        }
        if($isAmount > 0){
            $where[] = ["amount", ">", 0];
        }
        $time = time();
        $where[] = ['start_time', '<=', $time];
        $where1 = 'end_time >= '.$time.' or end_time = 0';
        $data = JournalismModel::with(['journalismGame.game'])
            ->where($where)->where($where1)
            ->limit($offset, $limit)
            ->order('sort DESC, id DESC')
            ->filter(function($data) use($sys){
                if($data['pic'] == '/static/admin/img/add.png'){
                    $data['pic'] = $sys['domain'].'/static/admin/img/add.png';
                }
                $data['addtime'] = date('Y-m-d H:i:s',$data['addtime']);
            })
            ->withoutField('status,sort')
            ->select();
        foreach ($data as $v){
            //将游戏数据取出来
            $arr = [];
            foreach ($v->journalismGame as $_v){
                if( $_v->game && $_v->game1 ){
                    $_v->game->hidden(['gi_id', 'gi_thirdlang']);
                    $_gameArr = $_v->game;
                    $_gameArr['game_name'] = $_gameArr['gi_name'];
                    $_gameArr['id'] = $_gameArr['gi_gid'];
                    $_gameArr['game_status'] = $_v->game1->g_state;
                    $arr[] = $_gameArr;
                }
            }
            $v->games = $arr;
            unset($v->journalismGame);
        }
        $rsps['code']=1;
        $rsps['total']=JournalismModel::where($where)->count();
        $rsps['data']=$data;
        return $rsps;
    }


    //新闻详情
    public function details_run(){
        $id = $this->param['id']??0;
        if(!$id){
            return ['code'=>1,'data'=>""];
        }
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $data = JournalismModel::with(['journalismGame.game','journalismGame.game1'])
                ->where(['id'=>$id])
                ->filter(function($data) use($sys){
                    if($data['pic'] == '/static/admin/img/add.png'){
                        $data['pic'] = $sys['domain'].'/static/admin/img/add.png';
                    }
                    $data['addtime'] = date('Y-m-d H:i:s',$data['addtime']);
                })
                ->withoutField('status,sort')
                ->find() ??"";
        if( $data ){
            //将游戏数据取出来
            $arr = [];
            foreach ($data->journalismGame as $_v){
                if( $_v->game && $_v->game1 ){
                    $_v->game->hidden(['gi_id', 'gi_thirdlang']);
                    $_gameArr = $_v->game;
                    $_gameArr['game_name'] = $_gameArr['gi_name'];
                    $_gameArr['id'] = $_gameArr['gi_gid'];
                    $_gameArr['game_status'] = $_v->game1->g_state;
                    $arr[] = $_gameArr;
                }
            }
            $data->games = $arr;
            unset($data->journalismGame);
        }
        $rsps['code']=1;
        $rsps['data']=$data;
        return $rsps;
    }
}