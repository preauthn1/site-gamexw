<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\GameRankModel;
use think\Request;

class GameRank
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request)
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $gameRankModel = new GameRankModel();
        if (isset($param['type']) && $param['type'] !== '') {
            $condition[] = ['type', '=', $param['type']];
        }
        $paginate = $gameRankModel
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
//            switch (intval($info['type'])) {
//                case 1:
//                    $info['key_val'] = 'hot';
//                    break;
//                case 2:
//                    $info['key_val'] = 'top';
//                    break;
//                case 3:
//                    $info['key_val'] = 'new';
//                    break;
//                case 4:
//                    $info['key_val'] = 'self';
//                    break;
//            }
            $res = GameRankModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new GameRankModel(), lang('telegram添加'));
        }
    }


    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $gameRankModel = new GameRankModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $gameRankModel->where('id', $info['id'])->find();
//            switch (intval($info['type'])) {
//                case 1:
//                    $info['key_val'] = 'hot_' . $info['id'];
//                    break;
//                case 2:
//                    $info['key_val'] = 'top_' . $info['id'];
//                    break;
//                case 3:
//                    $info['key_val'] = 'new_' . $info['id'];
//                    break;
//                case 4:
//                    $info['key_val'] = 'self_' . $info['id'];
//                    break;
//            }
            $res = $gameRankModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $gameRankModel, lang('telegram修改'));
        }
        if (isset($param['find'])) {
            $res = $gameRankModel->where("id", $param['id'])->find();
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $gameRankModel = new GameRankModel();
            $res = $gameRankModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $gameRankModel, lang('telegram删除'));
        }
    }
}