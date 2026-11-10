<?php
namespace app\manage\controller;
use app\common\model\AdminPermissionModel;
use app\common\model\AdminRoleModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use think\facade\View;
use think\facade\Request;
class AdminPermission
{	
	protected $middleware = ['adminCheckLogin',"Language"];

    //编辑
    public function list(){
    	$param=Request::param();
    	if (isset($param['find'])) {
            $role_id=$param['id']??0;
            $role=AdminRoleModel::where("id",$role_id)->find();
            $permission=[];
            if (isset($role['role_permission'])) {
                $permission=explode(",",$role['role_permission']);
            }
    		$AdminPermissionModel =new AdminPermissionModel();
    		$res=$AdminPermissionModel->select();
    		foreach ($res as $key => $value) {
    			$res[$key]['name']=$value['permission_name'];
    			$res[$key]['pId']=$value['pid'];
                foreach ($permission as $key1 => $value1) {
                    if ($value1==$value['id']) {
                        $res[$key]['checked']=true;
                        break;
                    }
                }
    		}
    		return Respons::run(1,$res);
    	}
        return View::fetch('admin_permission/list');
    }

    //添加子权限
    public function add(){
    	$param=Request::param();
    	if (isset($param['add'])) {
    		$info=$param['info'];
    		$add['permission_name']=$info['child_permission_name'];
    		$add['permission_url']=$info['child_permission_url'];
    		$add['pid']=$info['id'];
    		$AdminPermissionModel =new AdminPermissionModel();
    		$res=$AdminPermissionModel->insertGetId($add);
    		return Respons::run($res?1:0);
    	}
    }

    //修改权限
    public function edit(){
    	$param=Request::param();
    	if (isset($param['edit'])) {
    		$info=$param['info'];
    		if (!isset($info['id'])) {
    			throw new \Exception(lang("请选择数据"), 0);
    		}
    		$AdminPermissionModel =new AdminPermissionModel();
    		$res=$AdminPermissionModel->update($info);
    		return Respons::run($res?1:0);
    	}
    }

    //删除
    public function delete(){
    	$param=Request::param();
    	if (isset($param['delete'])) {
    		$id=$param['id'];
    		$AdminPermissionModel =new AdminPermissionModel();
    		$res=$AdminPermissionModel->where("id",$id)->delete();
    		return Respons::run($res?1:0);
    	}
    }

}