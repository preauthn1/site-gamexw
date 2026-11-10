<?php

namespace app\manage\controller;

use app\common\model\NewsModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\common\model\SignNewsModel;
use app\common\model\WuFuContentModel;
use app\common\Utils;
use app\manage\factory\NewsFactory;
use app\manage\factory\PddNewsFactory;
use app\manage\factory\SignNewsFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;

class WufuContent
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function lists(\think\Request $request)
    {

        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where = [];
        $wuFuContentModel = new WuFuContentModel();
        if (isset($param['name']) && $param['name'] !== '') {
            $where[] = ["name|intro", "like", "%" . $param['name'] . "%"];
        }

        if (isset($param['w_id']) && $param['w_id'] !== '') {
            $where[] = ["w_id", "=", $param['w_id']];
        }

        $list = $wuFuContentModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit, false, ['query' => $param]);
        foreach ($list as &$value) {
            $value['create_time'] = date("Y-m-d H:i:s", $value['add_time']);
        }
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        $rsps['code'] = 1;
        $rsps['data'] = $list;
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        $info = $param['info'];
        unset($info['id']);
        $info['create_time'] = $info['update_time'] = time();
        $res = WuFuContentModel::insertGetId($info);
        $info['id'] = $res;
        return Respons::run($res ? 1 : 0, '', '', '', $info, new WuFuContentModel(), lang('五福内容添加'));
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $wuFuContentModel = new WuFuContentModel();
            $data = $wuFuContentModel->where('id', $info['id'])->find();
            $info['update_time'] = time();
            $res = $wuFuContentModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $wuFuContentModel, lang('五福内容修改'));
        }
        $wuFuContentModel = new WuFuContentModel();
        $res = $wuFuContentModel->where("id", $param['id'])->find();
        return Respons::run(1, $res);
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $wuFuContentModel = new WuFuContentModel();
            $res = $wuFuContentModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $wuFuContentModel, lang('五福内容删除'));
        }
    }
}