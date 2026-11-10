<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\ForecastModel;
use app\common\Utils;
use think\Request;
class Forecast
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $forecastModel =new ForecastModel();
        $condition = [];
        if( isset($param['phone']) && $param['phone'] !== '' ){
            $condition[] = ['phone', '=', $param['phone']];
        }
        $paginate=$forecastModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
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
            //判断该语言是否添加
            if( ForecastModel::where('lang', '=', $info['lang'])->value('id') ){
                return Respons::run(0,[],'一种语言只能添加一个预测页');

            }
            $info['create_time'] = $info['update_time'] = time();
            $res = ForecastModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new ForecastModel(),lang('预测页添加'));
        }
    }

    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $forecastModel = new ForecastModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $forecastModel->where('id',$info['id'])->find();
            $res=$forecastModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$forecastModel,lang('预测页修改'));
        }
        if (isset($param['find'])) {
            $res=$forecastModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $forecastModel =new ForecastModel();
            $res=$forecastModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$forecastModel,lang('预测页删除'));
        }
    }
}