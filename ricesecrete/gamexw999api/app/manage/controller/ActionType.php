<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\ActionTypeModel;
use app\common\model\UrlModel;
use app\common\Utils;
use think\facade\View;
use think\Request;
class ActionType
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $actionTypeModel =new ActionTypeModel();
        if (isset($param['name']) && $param['name']!='') {
            $where[]=["name","like","%".$param['name']."%"];
        }
        $rsps['data']=$actionTypeModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            $info['create_time'] = $info['update_time'] = time();
            $res = ActionTypeModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new ActionTypeModel(),lang('活动类型添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $actionTypeModel = new ActionTypeModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $actionTypeModel->where('id',$info['id'])->find();
            $res=$actionTypeModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$actionTypeModel,lang('活动类型修改'));
        }
        if (isset($param['find'])) {
            $res=$actionTypeModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $actionTypeModel =new ActionTypeModel();
            $res=$actionTypeModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$actionTypeModel,lang('活动类型删除'));
        }
    }
}