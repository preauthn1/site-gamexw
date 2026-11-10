<?php
namespace app\manage\controller;
use app\common\model\GonggaoModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\manage\factory\GonggaoFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;

class Gonggao
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function list(\think\Request $request)
    {
        $rsps = (new GonggaoFactory($request->token->uid))->list();
        return $rsps;
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            $start_time = $info['start_time'];
            $end_time = $info['end_time'];
            $info['add_time'] = time();
            $info['update_time'] = time();
            $info['admin_name'] = Session::get("adminInfo.name");
            $info['start_time'] = strtotime($info['start_time']);
            $info['end_time'] = strtotime($info['end_time']);
            $GonggaoModel = new GonggaoModel();
            $res = $GonggaoModel->insertGetId($info);
            $info['start_time'] = $start_time;
            $info['end_time'] = $end_time;
            $info['id'] = $res;
            $langs = LangModel::order('id asc')->select();
            $info['langs'] = $langs;
            return Respons::run($res ? 1 : 0, '', '', '', $info, $GonggaoModel, lang('公告添加'));
        }
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $start_time = $info['start_time'];
            $end_time = $info['end_time'];
            $info['update_time'] = time();
            $info['admin_name'] = Session::get("adminInfo.name");
            $info['start_time'] = strtotime($info['start_time']);
            $info['end_time'] = strtotime($info['end_time']);
            $GonggaoModel = new GonggaoModel();
            $data = $GonggaoModel->where('id', $info['id'])->field("*,from_unixtime(start_time,'%Y-%m-%d %H:%i:%s') as start_time,from_unixtime(end_time,'%Y-%m-%d %H:%i:%s') as end_time")->find();
            $res = $GonggaoModel->update($info);
            $info['start_time'] = $start_time;
            $info['end_time'] = $end_time;
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $GonggaoModel, lang('公告修改'));
        }
        if (isset($param['find'])) {
            $GonggaoModel = new GonggaoModel();
            $res = $GonggaoModel->where("id", $param['id'])->find();
            $res['start_time'] = date("Y-m-d H:i:s", $res['start_time']);
            $res['end_time'] = date("Y-m-d H:i:s", $res['end_time']);
            $langs = LangModel::order('id asc')->select();
            $res['langs'] = $langs;
            return Respons::run(1, $res);
        }
    }

    //编辑
    public function status_edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $info['update_time'] = time();
            $info['admin_name'] = Session::get("adminInfo.name");
            $GonggaoModel = new GonggaoModel();
            $data = $GonggaoModel->where('id', $info['id'])->field("id,status")->find();
            $res = $GonggaoModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $GonggaoModel, lang('公告状态修改'));
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $GonggaoModel = new GonggaoModel();
            $res = $GonggaoModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $GonggaoModel, lang('公告删除'));
        }
    }
}