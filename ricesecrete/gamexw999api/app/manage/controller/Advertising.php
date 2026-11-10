<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\ActionModel;
use app\common\model\AdvertisingModel;
use app\manage\factory\AdvertisingFactory;
use think\Request;

class Advertising
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function lists(\think\Request $request)
    {
        $rsps = (new AdvertisingFactory($request->token->uid))->lists();
        return $rsps;
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            if (empty($info['start_time']) && empty($info['end_time'])) {
                return Respons::run(0, [], lang("请选择时间"));
            }
            $info['start_time'] = strtotime($info['start_time']);
            $info['end_time'] = strtotime($info['end_time']);
            $info['add_time'] = time();
            $info['update_time'] = time();
            unset($info['id']);
            $res = AdvertisingModel::insertGetId($info);
            $param['info']['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $param['info'], new AdvertisingModel(), lang('广告添加'));
        }
    }

    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            if (empty($info['start_time']) && empty($info['end_time'])) {
                return Respons::run(0, [], lang("请选择时间"));
            }
            $info['start_time'] = strtotime($info['start_time']);
            $info['end_time'] = strtotime($info['end_time']);
            $info['update_time'] = time();
            $res = AdvertisingModel::update($info);
            $param['info']['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $param['info'], new AdvertisingModel(), lang('广告修改'));
        }
        if (isset($param['find'])) {
            $AdvertisingModel = new AdvertisingModel();
            $res = $AdvertisingModel->where("id", $param['id'])->find();
            if ($res) {
                $res = $res->toArray();
            }
            $res['start_time'] = date("Y-m-d H:i:s", $res['start_time']);
            $res['end_time'] = date("Y-m-d H:i:s", $res['end_time']);
            $res['add_time'] = date("Y-m-d H:i:s", $res['add_time']);
            $res['update_time'] = date("Y-m-d H:i:s", $res['update_time']);
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $res = AdvertisingModel::where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', new AdvertisingModel(), lang('广告删除'));
        }
    }

}
