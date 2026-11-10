<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\LangModel;
use app\common\model\RuleIndexModel;
use app\common\model\RuleModel;
use app\manage\factory\RuleIndexFactory;
use think\Request;

class RuleIndex
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function lists(\think\Request $request)
    {
        $rsps = (new RuleIndexFactory($request->token->uid))->lists();
        return $rsps;
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        $type = config('custom.rule_index_type');
        if (isset($param['add'])) {
            $info = $param['info'];
            unset($info['id']);
            $res = RuleIndexModel::insertGetId($info);
            $info['type'] = isset($type[$info['type']]) ? $type[$info['type']] : '';
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new RuleIndexModel(), lang('规则添加'));
        }
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $types = config('custom.rule_index_type');
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = RuleIndexModel::where('id', $info['id'])->field('id,type,lang')->find();
            $data['type'] = isset($types[$data['type']]) ? $types[$data['type']] : '';
            $res = RuleIndexModel::update($info);
            $info['type'] = isset($types[$info['type']]) ? $types[$info['type']] : '';
            return Respons::run($res ? 1 : 0, '', '', $data, $info, new RuleIndexModel(), lang('规则修改'));
        }
        if (isset($param['find'])) {
            $res = RuleIndexModel::where("id", $param['id'])->find();
            $langs = LangModel::order('id asc')->select();
            $res['langs'] = $langs;
            $res['types'] = $types;
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $res = RuleIndexModel::where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', new RuleIndexModel(), lang('规则删除'));
        }
    }
}