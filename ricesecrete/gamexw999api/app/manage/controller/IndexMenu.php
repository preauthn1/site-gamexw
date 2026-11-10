<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\IndexMenuModel;
use app\common\model\LangModel;
use app\common\Utils;
use think\Request;

class IndexMenu extends Base
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request)
    {
        $param = \think\facade\Request::param();
        $condition = [['pid', '=', 0]];
        $indexMenuModel = new IndexMenuModel();
        if (isset($param['area']) && $param['area'] !== '') {
            $condition[] = ['area', '=', $param['area']];
        }
        if (isset($param['lang']) && $param['lang'] !== '') {
            $condition[] = ['lang', '=', $param['lang']];
        }

        [$data, $total] = $this->getDataList($indexMenuModel, $condition, null, 'sort ASC,id ASC');
        foreach ($data as $item) {
            $item['children'] = $indexMenuModel->where('pid', $item['id'])->order('sort ASC,id ASC')->select();
        }
        $menu_area_type = config('custom.menu_area_type');

        return $this->success([
            'total' => $total,
            'data' => $data,
            'menu_type' => $menu_area_type,
            'url' => Utils::getUrlList(),
            'menu_type_arr' => Utils::getOptionsByConfig(config('custom.menu_area_type')),
            'lang_arr' => Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]),
        ]);
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            unset($info['id']);
            $info['href'] = html_entity_decode($info['href']);
            $res = IndexMenuModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new IndexMenuModel(), lang('telegram添加'));
        }
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $indexMenuModel = new IndexMenuModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $indexMenuModel->where('id', $info['id'])->find();
            $info['href'] = html_entity_decode($info['href']);
            $res = $indexMenuModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $indexMenuModel, lang('telegram修改'));
        }
        if (isset($param['find'])) {
            $res = $indexMenuModel->where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $indexMenuModel = new IndexMenuModel();
            $res = $indexMenuModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $indexMenuModel, lang('telegram删除'));
        }
    }
}