<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\PaymentTypeModel;
use think\facade\Request;

class PaymentType
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function list()
    {
        $param = Request::param();
        $limit = $param['limit'];
        $where = [];
        $BankModel = new PaymentTypeModel();
        if (isset($param['name']) && $param['name'] != '') {
            $where[] = ["name", "like", "%" . $param['name'] . "%"];
        }
        $rsps['data'] = $BankModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit, false, ['query' => $param]);
        $rsps['code'] = 1;
        $rsps['type'] = config('custom.payment_method_type');
        return Respons::run($rsps);
    }

    //添加
    public function add()
    {
        $param = Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            unset($info['id']);
            $BankModel = new PaymentTypeModel();
            $res = $BankModel->insertGetId($info);
            return Respons::run($res ? 1 : 0);
        }
    }


    //编辑
    public function edit()
    {
        $param = Request::param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $BankModel = new PaymentTypeModel();
            $res = $BankModel->update($info);
            return Respons::run($res ? 1 : 0);
        }
        if (isset($param['find'])) {
            $BankModel = new PaymentTypeModel();
            $res = $BankModel->where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete()
    {
        $param = Request::param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $BankModel = new PaymentTypeModel();
            $res = $BankModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0);
        }
    }
}