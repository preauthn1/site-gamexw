<?php
namespace app\manage\controller;
use app\common\model\IpLimitModel;
use app\common\lib\Respons;
use app\manage\factory\IpLimitFactory;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
class IpLimit
{	
	protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(\think\Request $request){
        $rsps = (new IpLimitFactory($request->token->uid))->list();
        return $rsps;
    }

    //添加
    public function add(){
    	$param=Request::param();
    	if (isset($param['add'])) {
    		$info=$param['info'];
    		$info['add_time']=time();
    		$info['update_time']=time();
    		$info['admin_name']=Session::get("adminInfo.name");
            $IpLimitModel=new IpLimitModel();
    		$res=$IpLimitModel->insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,$IpLimitModel,lang('IP黑明单添加'));
        }
        View::assign('myIp', \app\common\lib\Util::get_ip());
        return View::fetch('ip_limit/add');
    }


    //编辑
    public function edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $IpLimitModel=new IpLimitModel();
            $data = $IpLimitModel->where('id',$info['id'])->find();
            $info['update_time']=time();
            $res=$IpLimitModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$IpLimitModel,lang('IP黑明单修改'));
        }
        if (isset($param['find'])) {
            $IpLimitModel =new IpLimitModel();
            $res=$IpLimitModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
        View::assign("id",$param['id']);
        View::assign('myIp', \app\common\lib\Util::get_ip());
        return View::fetch('ip_limit/edit');
    }

    //编辑
    public function status_edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $IpLimitModel=new IpLimitModel();
            $field = '';
            if(isset($info['admin_status'])){
                $field = ',admin_status';
            }else{
                $field = ',api_status';
            }
            $data = $IpLimitModel->where('id',$info['id'])->field('id'.$field)->find();
            $info['update_time']=time();
            $res=$IpLimitModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$IpLimitModel,lang('IP黑明单状态修改'));
        }
    }

    //删除
    public function delete(){
        $param=Request::param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $IpLimitModel =new IpLimitModel();
            $res=$IpLimitModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$IpLimitModel,lang('IP黑明单状态删除'));
        }
    }
}