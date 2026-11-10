<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\ForecastGameModel;
use app\common\model\GamesModel;
use app\common\Utils;
use think\Request;
class ForecastGame
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $forecastGameModel =new ForecastGameModel();
        $condition = [];
        if( isset($param['game_name']) && $param['game_name'] !== '' ){
            $condition[] = ['gi.gi_name', 'like', '%'.$param['game_name'].'%'];
        }
        if( isset($param['platform_name']) && $param['platform_name'] !== '' ){
            $condition[] = ['tc.tc_name', 'like', '%'.$param['platform_name'].'%'];
        }
        $condition[] = ['a.forecast_id', '=', $param['forecast_id']];
        $paginate=$forecastGameModel->alias('a')
            ->join('game_infos gi', 'gi.gi_gid=a.gid')
            ->join('third_configs tc', 'tc.tc_id=a.tcid')
            ->where($condition)
            ->field('a.*,gi.gi_name as game_name,gi.gi_headimg as game_img,tc.tc_name as platform_name')
            ->order("a.id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
        $param = $request->param();
        if (isset($param['add'])) {
            $info=$param['info'];
            //批量添加游戏
            $gameIds = explode(',', $info['game_ids']);
            $saveAll = [];
            $tcidArr = [];
            foreach ($gameIds as $v){
                $arr = [];
                $arr['gid'] = $v;
                $tcid = GamesModel::where('g_id', '=', $v)->value('g_tcid');
                $tcidArr[] = $tcid;
                $arr['tcid'] = $tcid;
                $arr['time'] = $info['time'];
                $arr['forecast_id'] = $info['forecast_id'];
                $arr['sort'] = $info['sort'];
                $arr['create_time'] = time();
                $arr['update_time'] = time()+rand(10, 180);//修改时间增加随机秒数，用于接口计算时不同游戏进度不一样
                $saveAll[] = $arr;
            }
            //判断平台不能超过两个
            //获取已添加的平台id
            $_tcidArr = ForecastGameModel::group('tcid')->column('tcid');
            $tcidArr = array_merge($tcidArr, $_tcidArr);
            if( count(array_unique($tcidArr)) > 2 ){
                return Respons::run(0, [], '最多只能添加2个平台的游戏');
            }
            $res = (new ForecastGameModel())->saveAll($saveAll);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new ForecastGameModel(),lang('预测游戏添加'));
        }
    }

    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $forecastGameModel = new ForecastGameModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $forecastGameModel->where('id',$info['id'])->find();
            $res=$forecastGameModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$forecastGameModel,lang('预测游戏修改'));
        }
        if (isset($param['find'])) {
            $res=$forecastGameModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $forecastGameModel =new ForecastGameModel();
            $res=$forecastGameModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$forecastGameModel,lang('预测游戏删除'));
        }
    }
}