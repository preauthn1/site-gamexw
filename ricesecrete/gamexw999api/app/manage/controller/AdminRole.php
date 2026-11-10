<?php
namespace app\manage\controller;

use app\common\model\{AdminNodeModel, AdminPermissionModel, AdminRoleModel};
use app\manage\factory\AdminRoleFactory;
use think\facade\{Db, View, Request, Session};
use app\common\lib\{Respons,Util};

class AdminRole extends Base
{	
	protected $middleware = ['ManageCheckLogin',"Language"];


    public function __construct(AdminRoleModel $AdminRoleModel)
    {
        $this->AdminRoleModel = $AdminRoleModel;
    }

    //列表
    public function list(\think\Request $request){
        $rsps = (new AdminRoleFactory($request->token->uid, $this->AdminRoleModel))->list();
        return $rsps;
    }

    //添加
    public function add(){
    	$param=Request::param();
    	if (isset($param['add'])) {
            $admin = Session::get('adminInfo');
            $info=$param['info'];
            $info['admin_id']=$admin['id'];
            $AdminRoleModel=new AdminRoleModel();
            $permission_name = $info['permission_name'];
            unset($info['permission_name']);
            $res=$AdminRoleModel->insertGetId($info);
            $info['role_permission'] = $permission_name;
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,$AdminRoleModel,lang('角色添加'));

        }
        return View::fetch('admin_role/add');
    }


    //编辑
    public function edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $AdminRoleModel=new AdminRoleModel();
            $data = $AdminRoleModel->where('id',$info['id'])->find();
            if (empty($data)) {
                return Respons::run(0,'','参数有误');
            }
            $name = [];
            $permission_name = $info['permission_name'] ??'';
            $role_permission = empty($data['role_permission']) ? []:explode(',',$data['role_permission']);
            foreach ($role_permission as &$v){
                $name[] = AdminPermissionModel::where('id',$v)->value('permission_name');
            }
            $data['role_permission'] = empty($name) ? '':implode(',',$name);
            if(!empty($info['permission_name'])){
                unset($info['permission_name']);
            }
            $res = $AdminRoleModel->update($info);
            $info['role_permission'] = $permission_name;
            return Respons::run($res?1:0,'','',$data,$info,$AdminRoleModel,lang('角色修改'));
        }
        if (isset($param['find'])) {
            $AdminRoleModel =new AdminRoleModel();
            $res=$AdminRoleModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
        View::assign("id",$param['id']);
        return View::fetch('admin_role/edit');
    }

    //删除
    public function delete(){
        $param=Request::param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $AdminRoleModel =new AdminRoleModel();
            $res=$AdminRoleModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$AdminRoleModel,lang('角色删除'));
        }
    }

    //角色添加
    public function node_add(){
        $param=Request::param();
        if (isset($param['add'])) {
            $data = $param['data'];
            try {
                validate(\app\common\validate\AdminRoleValidate::class)->check($data);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            Db::startTrans();
            try {
                $data['admin_id'] = Session::get("adminInfo.id");
                $data['role_permission'] = trim($data['ids'], ',');
                $res = $this->AdminRoleModel->save($data);
                $data['role_permission'] = trim($data['name'], ',');
                $data['id'] = $this->AdminRoleModel->id;
                Db::commit();
                return Respons::run($res ? 1 : 0, '', '', '', $data, $this->AdminRoleModel,lang('角色添加'));
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
        return View::fetch('admin_role/node_add');
    }

    /*角色编辑*/
    public function node_edit(){
        $param = Request::param();
        if (isset($param['find'])) {
            $res = $this->AdminRoleModel->where(["id" => $param['id']])->find();
            return Respons::run(1, $res);
        }
        if (isset($param['edit'])) {
            $par = $param['data'];
            $model = $this->AdminRoleModel->where(['id' => $par['id']])->find();
            $par['role_permission'] = trim($par['ids'], ',');
            if($model) $data = $model->toArray(); //浅拷贝 如果不用toarray 下面更新数据的时候 data 会被修改
                /**@var AdminNodeModel $AdminNodeModel*/
            $AdminNodeModel = app()->make(AdminNodeModel::class);
            $data['role_permission'] =  $AdminNodeModel->where(function($query) use($model) {
                $query->where('id', 'in', $model->role_permission);
            })->column('node_name');
            $data['role_permission'] = implode(',', $data['role_permission']);
            $res = $model->save($par);
            $par['role_permission'] = $par['name'];
            return Respons::run($res ? 1 : 0, '', '', $data, $par, $this->AdminRoleModel, lang('角色修改'));
        }
        View::assign("id", $param['id']);
        return View::fetch('admin_role/node_edit');
    }


}