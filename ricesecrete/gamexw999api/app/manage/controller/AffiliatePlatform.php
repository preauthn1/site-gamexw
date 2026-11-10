<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\AffiliatePlatformModel;
use think\Request;

class AffiliatePlatform
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request)
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $affiliatePlatform = new AffiliatePlatformModel();
        $paginate = $affiliatePlatform
            ->field('*')->where($condition)
            ->order("id desc")
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
            $res = AffiliatePlatformModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new AffiliatePlatformModel(), lang('添加'));
        }
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $affiliatePlatform = new AffiliatePlatformModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $affiliatePlatform->where('id', $info['id'])->find();
            $res = $affiliatePlatform->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $affiliatePlatform, lang('修改'));
        }
        if (isset($param['find'])) {
            $res = $affiliatePlatform->where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $affiliatePlatform = new AffiliatePlatformModel();
            $res = $affiliatePlatform->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $affiliatePlatform, lang('删除'));
        }
    }
}