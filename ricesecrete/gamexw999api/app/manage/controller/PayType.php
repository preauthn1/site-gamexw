<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\PaymentTypeModel;
use app\common\model\PaySetModel;
use app\common\model\PayTypeModel;
use app\common\Utils;
use think\facade\Request;

class PayType
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function list()
    {
        $param = Request::param();
        $limit = $param['limit'];
        $where = [];
        $payModel = new PayTypeModel();
        if (isset($param['name']) && $param['name'] != '') {
            $where[] = ["name", "like", "%" . $param['name'] . "%"];
        }

        $rsps['data'] = $payModel
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
            $payModel = new PayTypeModel();
            $res = $payModel->insertGetId($info);
            return Respons::run($res ? 1 : 0);
        }
    }


    //编辑
    public function edit()
    {
        $param = Request::param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $payModel = new PayTypeModel();
            $res = $payModel->update($info);
            return Respons::run($res ? 1 : 0);
        }
        if (isset($param['find'])) {
            $payModel = new PayTypeModel();
            $res = $payModel->where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete()
    {
        $param = Request::param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $payModel = new PayTypeModel();
            $res = $payModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0);
        }
    }
}