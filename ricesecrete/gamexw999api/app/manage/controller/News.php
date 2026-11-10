<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\AgentNewsModel;
use app\common\model\LangModel;
use app\common\model\NewsModel;
use app\manage\factory\NewsFactory;
use think\facade\View;
use think\Request;

class News
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function lists(\think\Request $request)
    {
        $rsps = (new NewsFactory($request->token->uid))->lists();
        return $rsps;
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            $info['add_time'] = time();
            $res = NewsModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new NewsModel(), lang('基金问答添加'));
        }
        if (isset($param['type']) && $param['type'] == 1) {
            View::assign("action_id", $param['action_id']);
            return View::fetch('news/aadd');
        } else {
            return View::fetch('news/add');
        }
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $NewsModel = new NewsModel();
            $data = $NewsModel->where('id', $info['id'])->find();
            $res = $NewsModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $NewsModel, lang('基金问答修改'));
        }
        if (isset($param['find'])) {
            $NewsModel = new NewsModel();
            $res = $NewsModel->where("id", $param['id'])->find();
            $langs = LangModel::order('id asc')->select();
            $res['langs'] = $langs;
            return Respons::run(1, $res);
        }
        if (isset($param['type']) && $param['type'] == 1) {
            return View::fetch('news/aedit');
        } else {
            return View::fetch('news/edit');
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $NewsModel = new NewsModel();
            $res = $NewsModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $NewsModel, lang('基金问答删除'));
        }
    }

    public function agent_lists(\think\Request $request)
    {
        $rsps = (new NewsFactory($request->token->uid))->agent_lists();
        return $rsps;
    }

    public function agent_add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            $info['add_time'] = time();
            $res = AgentNewsModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new AgentNewsModel(), lang('代理问答添加'));
        }
    }

    public function agent_edit(Request $request)
    {
        $param = $request->param();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $AgentNewsModel = new AgentNewsModel();
            $data = $AgentNewsModel->where('id', $info['id'])->find();
            $res = $AgentNewsModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $AgentNewsModel, lang('代理问答修改'));
        }
        if (isset($param['find'])) {
            $AgentNewsModel = new AgentNewsModel();
            $res = $AgentNewsModel->where("id", $param['id'])->find();
            $langs = LangModel::order('id asc')->select();
            $res['langs'] = $langs;
            return Respons::run(1, $res);
        }
    }


    public function agent_delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $AgentNewsModel = new AgentNewsModel();
            $res = $AgentNewsModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $AgentNewsModel, lang('代理问答删除'));
        }
    }

    public function agent_daan_lists(\think\Request $request)
    {
        $rsps = (new NewsFactory($request->token->uid))->agent_daan_lists();
        return $rsps;
    }
}