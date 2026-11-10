<?php

namespace app\manage\controller;

use app\common\model\NewsModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\common\model\SignNewsModel;
use app\manage\factory\NewsFactory;
use app\manage\factory\PddNewsFactory;
use app\manage\factory\SignNewsFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;

class SignNews
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function lists(\think\Request $request)
    {
        $rsps = (new SignNewsFactory($request->token->uid))->lists();
        return $rsps;
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            unset($info['id']);
            $info['add_time'] = time();
            $res = SignNewsModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new NewsModel(), lang('基金问答添加'));
        }
        View::assign("pid", $param['info']['pid']);
        return View::fetch('news/aadd');
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $NewsModel = new SignNewsModel();
            $data = $NewsModel->where('id', $info['id'])->find();
            $res = $NewsModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $NewsModel, lang('基金问答修改'));
        }
        if (isset($param['find'])) {
            $NewsModel = new SignNewsModel();
            $res = $NewsModel->where("id", $param['id'])->find();
            $langs = LangModel::order('id asc')->select();
            $res['langs'] = $langs;
            return Respons::run(1, $res);
        }
        return View::fetch('news/aedit');
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $NewsModel = new SignNewsModel();
            $res = $NewsModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $NewsModel, lang('基金问答删除'));
        }
    }
}