<?php
namespace app\manage\controller;
use app\common\model\ServiceModel;
use app\common\model\UserModel;
use app\common\lib\Respons;
use app\manage\factory\ServiceFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;
class Service
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(\think\Request $request){
        $rsps = (new ServiceFactory($request->token->uid))->list();
        return $rsps;
    }

    //添加
    public function add(Request $request){
        $param = $request->param();
        if (isset($param['add'])) {
            $info=$param['info'];
            $ServiceModel=new ServiceModel();
            $res=$ServiceModel->insertGetId($info);
            $info['id'] = $res;
            $info['img'] = $info['img'] == '/static/admin/img/add.png' ?'':$info['img'];
            return Respons::run($res?1:0,'','','',$info,$ServiceModel,lang('客服管理添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $ServiceModel=new ServiceModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $ServiceModel->where('id',$info['id'])->find();
            $res=$ServiceModel->update($info);
            $info['img'] = $info['img'] == '/static/admin/img/add.png' ?'':$info['img'];
            return Respons::run($res?1:0,'','',$data,$info,$ServiceModel,lang('客服管理修改'));
        }
        if (isset($param['find'])) {
            $res=$ServiceModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //编辑
    public function status_edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $ServiceModel=new ServiceModel();
            $data = $ServiceModel->where('id',$info['id'])->field("id,status")->find();
            $res=$ServiceModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$ServiceModel,lang('客服管理状态修改'));
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $ServiceModel=new ServiceModel();
            $res=$ServiceModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$ServiceModel,lang('客服管理删除'));
        }
    }
}