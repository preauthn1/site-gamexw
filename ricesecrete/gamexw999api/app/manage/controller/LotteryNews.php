<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\LangModel;
use app\common\model\LotteryNewsModel;
use app\manage\factory\LotteryNewsFactory;
use think\facade\View;
use think\Request;

class LotteryNews
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $rsps = (new LotteryNewsFactory($request->token->uid))->lists();
        return $rsps;
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            $info['add_time']=time();
            $res = LotteryNewsModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new LotteryNewsModel(),lang('转盘简介'));
        }
        View::assign("pdd_id", $param['pdd_id']);
        return View::fetch('news/aadd');
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $NewsModel=new LotteryNewsModel();
            $data = $NewsModel->where('id',$info['id'])->find();
            $res=$NewsModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$NewsModel,lang('转盘简介修改'));
        }
        if (isset($param['find'])) {
            $NewsModel =new LotteryNewsModel();
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
            $NewsModel =new LotteryNewsModel();
            $res=$NewsModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$NewsModel,lang('转盘简介删除'));
        }
    }
}