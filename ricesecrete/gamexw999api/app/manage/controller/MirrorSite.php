<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\MirrorSiteModel;
use think\Request;
class MirrorSite
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $mirrorSiteModel =new MirrorSiteModel();
        if (isset($param['url']) && $param['url']!='') {
            $where[]=["url","=", $param['url']];
        }
        $rsps['data']=$mirrorSiteModel
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
            $res = MirrorSiteModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new MirrorSiteModel(),lang('镜像站点添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $mirrorSiteModel = new MirrorSiteModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $mirrorSiteModel->where('id',$info['id'])->find();
            $res=$mirrorSiteModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$mirrorSiteModel,lang('镜像站点修改'));
        }
        if (isset($param['find'])) {
            $res=$mirrorSiteModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $mirrorSiteModel =new MirrorSiteModel();
            $res=$mirrorSiteModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$mirrorSiteModel,lang('镜像站点删除'));
        }
    }
}