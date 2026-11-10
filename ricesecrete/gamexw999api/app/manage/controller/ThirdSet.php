<?php
namespace app\manage\controller;
use app\common\model\GameSetModel;
use app\common\model\PaySetModel;
use app\common\lib\Respons;
use app\common\model\ThirdSetModel;
use app\manage\factory\ThirdSetFacctory;
use think\facade\View;
use think\facade\Request;
class ThirdSet
{	
	protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(\think\Request $request){
        $rsps = (new ThirdSetFacctory($request->token->uid))->list();
        return $rsps;
    }

    //编辑
    public function add(){
        $param=Request::param();
        if (isset($param['add'])) {
            $info=$param['info'];
            $ThirdSetModel=new ThirdSetModel();
            $res=$ThirdSetModel->insert($info);
            return Respons::run($res?1:0);
        }
        return View::fetch('third_set/add');
    }

    //编辑
    public function edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $ThirdSetModel=new ThirdSetModel();
            $upd['id']=$info['id'];
            $upd['third_sys_sign']=$info['third_sys_sign'];
            $upd['name']=$info['name'];
            $upd['status']=$info['status'];
            $upd['sort']=$info['sort'];
            $upd['appid']=$info['appid'];
            $upd['secretkey']=$info['secretkey'];
            $upd['icon']=$info['icon'];
            $upd['callback']=$info['callback'];
            $res=$ThirdSetModel->update($upd);
            return Respons::run($res?1:0);
        }
        if (isset($param['find'])) {
            $ThirdSetModel =new ThirdSetModel();
            $res=$ThirdSetModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
        View::assign("id",$param['id']);
        return View::fetch('third_set/edit');
    }
    
}