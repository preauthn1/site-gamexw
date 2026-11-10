<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\UserVerifyConfigModel;
use think\Request;

class UserVerifyConfig
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request)
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $user_verify_config_model = new UserVerifyConfigModel();
        if (isset($param['type']) && $param['type'] !== '') {
            $condition[] = ['type', '=', $param['type']];
        }
        if (isset($param['status']) && $param['status'] !== '') {
            $condition[] = ['status', '=', $param['status']];
        }
        if (isset($param['name']) && $param['name'] !== '') {
            $condition[] = ['name', 'like', '%'.$param['name'].'%'];
        }
        $paginate = $user_verify_config_model
            ->field('*')->where($condition)
            ->order("sort ASC")
            ->paginate($limit, false, ['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            unset($info['id']);
            $res = UserVerifyConfigModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new UserVerifyConfigModel(), lang('userverify添加'));
        }
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $user_verify_config_model = new UserVerifyConfigModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $user_verify_config_model->where('id', $info['id'])->find();
            $res = $user_verify_config_model->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $user_verify_config_model, lang('userverify修改'));
        }
        if (isset($param['find'])) {
            $res = $user_verify_config_model->where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $user_verify_config_model = new UserVerifyConfigModel();
            $res = $user_verify_config_model->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $user_verify_config_model, lang('userverify删除'));
        }
    }
}