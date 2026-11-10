<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\{AdminRoleModel, AdminNodeModel};
use think\facade\{Config, Db, Session, View};
use app\Request;
use app\manage\factory\AdminnodeFactory;


class AdminNode
{

    public function __construct(AdminNodeModel $AdminNodeModel)
    {
        $this->AdminNodeModel = $AdminNodeModel;
    }

    protected $middleware = ['ManageCheckLogin',"Language"];

    //登录
    public function get_auth(Request $request){
        $rsps=(new AdminnodeFactory($request->token->uid))->get_auth();
        return $rsps;
    }

    public function list(Request $request){
        $rsps=(new AdminnodeFactory($request->token->uid))->list();
        return $rsps;
    }

    /*添加*/
    public function add(Request $request){
        $param = $request->param();
        $p_name = '';
        if(isset($param['add'])){
            $info = $param['info'];
            try {
                validate(\app\common\validate\AdminNodeValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            Db::startTrans();
            try {
                $info['node_url'] = $info['path'];
                $res = $this->AdminNodeModel->save($info);
                Db::commit();
                return Respons::run($res ? 1 : 0);
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }

        return Respons::run(0, [], 'error');
    }

    /*修改*/
    public function edit(Request $request){
        $param = $request->param();
        if (isset($param['find'])) {
            $data = $this->AdminNodeModel
                    ->with('parent')
                    ->withoutField('create_time, update_time')
                    ->where(['id' => $param['id']])
                    ->find();
            return Respons::run(1, $data);
        }
        if (isset($param['edit'])) {
            $info = $param['info'];
            try {
                validate(\app\common\validate\AdminNodeValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            Db::startTrans();
            try {
                [$model, $data] = getOneData($this->AdminNodeModel, ['id' => $info['id']]);
                $info['node_url'] = $info['path'];
                $res = $model->save($info);
                Db::commit();
                return Respons::run($res ? 1 : 0);
            } catch (\Exception $e) {
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
        }
        return Respons::run(0, [], 'error');
    }

    /*删除*/
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $res = $this->AdminNodeModel->where("id", $id)->delete();
            return Respons::run($res?1:0);
        }
        return Respons::run(0, [], 'error');
    }

    /*修改排序*/
    public function setSort(Request $request){
        $param = $request->param();
        if(!$param['id']){return Respons::run(0, [], lang('参数异常'));}
        $res = $this->AdminNodeModel->update($param);
        return Respons::run($res ? 1 : 0);
    }

    /*获取树节点数据*/
    public function get_data(Request $request){
        $param = $request->param();
        $model = $this->AdminNodeModel;
        $admin = Session::get('adminInfo');
        $role_node_arr = [];
        //当前用户的权限
        if( isset($admin['role_permission']) ){
            $role_permission = $admin['role_permission'];
        }else{
            $role_permission = AdminRoleModel::where('id', '=', $admin['role_id'])->value('role_permission');
        }
        //角色已经拥有的权限节点
        if(isset($param['id'])){
            /**@var AdminRoleModel $AdminRoleModel*/
            $AdminRoleModel = app()->make(AdminRoleModel::class);
            $role = $AdminRoleModel->find($param);
            $role_node_arr = explode(',',$role['role_permission']);
        }
        //如果是系统管理员 要能看到全部的权限节点
        $admin_role_id = $admin['role_id'];
        $NodeShowArr = getNodeShow();
        Session::set('role_node_data',compact('role_node_arr', 'role_permission','NodeShowArr','admin_role_id'));

        $node_data = $model
                    //递归获取权限节点
                    ->with('children')
                    //使用封装好的搜索器
                    ->withSearch(['id'])
                    ->where(['pid' => 0])
                    ->order('sort ASC,id DESC')
                    ->select();

        Session::delete('role_node_data');
        return Respons::run(1, compact('node_data','role_permission'));
        //废弃 组装选中数据
        //return Respons::run(1,$this->assemblyData($node_data,$role_node_arr));
    }

    /**
     * 组装节点中已选择的数据
     * @param object $node_data 查出来的数据库数据
     * @param array $role_node_arr 当前用户的权限
     * @return object
     */
    public function assemblyData(object $node_data, array $role_node_arr): object
    {
        global $arr;
        $arr =  $role_node_arr;
        function data(&$info){
            foreach($info as &$v){
                if(in_array($v['id'],$GLOBALS['arr'])){
                    $v['checked'] = true;
                }
                if(isset($v['children'])){
                    data($v['children']);
                }

            }
        }
        data($node_data);
        return $node_data;
    }

}