<?php
namespace app\manage\controller;

use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\model\FalseAccoutModel;
use app\manage\factory\FalseAccoutFactory;
use think\facade\Request;

class FalseAccout
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function list(\think\Request $request)
    {
        $rsps = (new FalseAccoutFactory($request->token->uid))->list();
        return $rsps;
    }

    //添加
    public function add()
    {
        $param = Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            $FalseAccoutModel = new FalseAccoutModel();
            $res = $FalseAccoutModel->insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, $FalseAccoutModel, lang('假账户添加'));
        }
        return Respons::run(0);
    }


    //编辑
    public function edit()
    {
        $param = Request::param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $FalseAccoutModel = new FalseAccoutModel();
            $data = $FalseAccoutModel->where('id', $info['id'])->find();
            $res = $FalseAccoutModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $FalseAccoutModel, lang('假账户修改'));
        }
        if (isset($param['find'])) {
            $FalseAccoutModel = new FalseAccoutModel();
            $res = $FalseAccoutModel->where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
        return Respons::run(0);
    }

    //编辑
    public function status_edit()
    {
        $param = Request::param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $FalseAccoutModel = new FalseAccoutModel();
            $data = $FalseAccoutModel->where('id', $info['id'])->field('id,status')->find();
            $res = $FalseAccoutModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $FalseAccoutModel, lang('假账户状态修改'));
        }
        return Respons::run(0);
    }

    //删除
    public function delete()
    {
        $param = Request::param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $FalseAccoutModel = new FalseAccoutModel();
            $res = $FalseAccoutModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $FalseAccoutModel, lang('假账户删除'));
        }
        return Respons::run(0);
    }
}