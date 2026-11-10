<?php

namespace app\manage\controller;

use app\api\redis\ApiRedis;
use app\common\lib\Respons;
use app\common\model\DefaultHeadModel;
use think\Request;

class DefaultHeadImg
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request)
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $indexMenuModel = new DefaultHeadModel();
        if (isset($param['name']) && $param['lang'] !== '') {
            $condition[] = ['name', 'like', "%".$param['name']."%"];
        }
        $paginate = $indexMenuModel
            ->field('*')->where($condition)
            ->order("id desc")
            ->paginate($limit, false, ['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            unset($info['id']);
            $info['add_time'] = time();
            $res = DefaultHeadModel::insertGetId($info);
            $this->clearCache();
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new DefaultHeadModel(), lang('telegram添加'));
        }
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $indexMenuModel = new DefaultHeadModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $info['update_time'] = time();
            $data = $indexMenuModel->where('id', $info['id'])->find();
            $res = $indexMenuModel->update($info);
            $this->clearCache();
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
            $indexMenuModel = new DefaultHeadModel();
            $res = $indexMenuModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $indexMenuModel, lang('telegram删除'));
        }
    }

    //清除默认头像列表缓存
    private function clearCache(){
        $apiRedis = new ApiRedis();
        $apiRedis->delete('default_head_list');
    }
}