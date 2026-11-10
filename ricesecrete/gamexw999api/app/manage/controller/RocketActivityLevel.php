<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\RocketActivityLevelModel;
use think\Request;

class RocketActivityLevel
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request)
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $rocketActivityLevelModel = new RocketActivityLevelModel();
        //活动id
        if (isset($param['rid']) && $param['rid'] !== '') {
            $condition[] = ['rid', '=', $param['rid']];
        }else{
            $rsps['data'] = [];
            $rsps['code'] = 1;
            return Respons::run($rsps);
        }
        $paginate = $rocketActivityLevelModel
            ->where($condition)
            ->field('*')
            ->order("level desc")
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
            $info['create_time'] = $info['update_time'] = time();
            unset($info['id']);
            $res = RocketActivityLevelModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new RocketActivityLevelModel(), lang('活动区间配置添加'));
        }
    }

    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $RocketActivityLevelModel = new RocketActivityLevelModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $RocketActivityLevelModel->where('id', $info['id'])->find();
            $info['update_time'] = time();
            $res = $RocketActivityLevelModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $RocketActivityLevelModel, lang('活动区间配置修改'));
        }
        if (isset($param['find'])) {
            $res = $RocketActivityLevelModel->where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $RocketActivityLevelModel = new RocketActivityLevelModel();
            $res = $RocketActivityLevelModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $RocketActivityLevelModel, lang('活动区间配置删除'));
        }
    }
}