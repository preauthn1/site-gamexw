<?php
namespace app\manage\controller;
use app\common\model\NewsModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\common\model\PddNewsModel;
use app\manage\factory\NewsFactory;
use app\manage\factory\PddNewsFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;
class PddNews
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $rsps = (new PddNewsFactory($request->token->uid))->lists();
        return $rsps;
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            $info['add_time']=time();
            $res = PddNewsModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new NewsModel(),lang('基金问答添加'));
        }
        View::assign("pdd_id", $param['pdd_id']);
        return View::fetch('news/aadd');
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $NewsModel=new PddNewsModel();
            $data = $NewsModel->where('id',$info['id'])->find();
            $res=$NewsModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$NewsModel,lang('基金问答修改'));
        }
        if (isset($param['find'])) {
            $NewsModel =new PddNewsModel();
            $res=$NewsModel->where("id",$param['id'])->find();
            $langs = LangModel::order('id asc')->select();
            $res['langs'] = $langs;
            return Respons::run(1,$res);
        }
        return View::fetch('news/aedit');
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $NewsModel =new PddNewsModel();
            $res=$NewsModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$NewsModel,lang('基金问答删除'));
        }
    }
}