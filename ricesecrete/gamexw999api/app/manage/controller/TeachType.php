<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\TeachTypeModel;
use think\facade\Request;

class TeachType
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function list()
    {
        $param = Request::param();
        $limit = $param['limit'];
        $where = [];
        $newsTypeModel = new TeachTypeModel();
        if (isset($param['name']) && $param['name'] !== '') {
            $where[] = ["name", "like", "%" . $param['name'] . "%"];
        }
        $rsps['data'] = $newsTypeModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit, false, ['query' => $param]);
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //添加
    public function add()
    {
        $param = Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            unset($info['id']);
            $TeachTypeModel = new TeachTypeModel();
            $res = $TeachTypeModel->insertGetId($info);
            return Respons::run($res ? 1 : 0, '', '', '', $info, $TeachTypeModel, lang('公告类型添加'));
        }
    }


    //编辑
    public function edit()
    {
        $param = Request::param();
        $TeachTypeModel = new TeachTypeModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $TeachTypeModel->where('id', $info['id'])->find();
            $res = $TeachTypeModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $TeachTypeModel, lang('公告类型修改'));
        }
        if (isset($param['find'])) {
            $res = $TeachTypeModel->where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete()
    {
        $param = Request::param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $TeachTypeModel = new TeachTypeModel();
            $res = $TeachTypeModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $TeachTypeModel, lang('公告类型删除'));
        }
    }
}