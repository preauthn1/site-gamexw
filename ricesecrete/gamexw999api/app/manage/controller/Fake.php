<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\FakeGameSet;
use app\common\model\GameInfosModel;
use app\common\model\GamesModel;
use app\common\Utils;
use think\facade\Request;

class Fake extends Base {
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function game() {
        $name = $this->request->param('name/s');
        $where = [];
        if (!empty($name)) {
            $where[] = ["fgs.game_name", "like", "%{$name}%"];
        }
        $field = 'fgs.*,tc.tc_name,g.g_icon';
        $fakeGameSetModel = new FakeGameSet();
        $data = $fakeGameSetModel->alias('fgs')
            ->where($where)->field($field)
            ->join('third_configs tc', 'tc.tc_id=fgs.tcid')
            ->join('games g', 'g.g_id=fgs.gid')
            ->select();
        foreach($data as &$value){
            $value['game_icon'] = toMedia($value['g_icon']);
        }
        $total = $fakeGameSetModel->alias('fgs')->where($where)->count();

        $games = GamesModel::alias('a')
            ->join('game_infos b', 'a.g_id=b.gi_gid')
            ->join('third_configs tc', 'tc.tc_id=a.g_tcid')
            ->where('a.g_state', 1)
            ->where('a.g_is_del', 0)
            ->column('b.gi_name', 'a.g_id');

        return $this->success([
            'total' => $total,
            'data' => $data,
            'games' => Utils::getOptionsByConfig($games)
        ]);
    }

    //添加
    public function add() {
        $param = Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            if( strlen($info['username']) < 10 ){
                return $this->error('用户名最少十位');
            }
            if (!isset($info['gid']) || empty($info['gid'])) {
                return $this->error('请选择游戏');
            }
            $model = new FakeGameSet();
            $game = GameInfosModel::where('gi_gid', $info['gid'])->find();
            if (empty($game)) {
                return $this->error('请选择正确的游戏');
            }
            $_game = GamesModel::where('g_id', $info['gid'])->field('g_state,g_tcid')->find();
            $g_tcid = $_game['g_tcid'] ?? 0;
            $info['tcid'] = $g_tcid;
            $info['game_name'] = $game['gi_name'];
            $info['game_icon'] = strexists($game['gi_headimg'], 'https://') ? $game['gi_headimg'] : '';
            //判断选择的游戏是否开启
            if( $info['status'] == 1 && (!isset($_game['g_state']) || $_game['g_state'] != 1) ){
                return $this->error('请先开启游戏');
            }
            if (isset($info['id']) && $info['id'] > 0) {
                $model = new FakeGameSet();
                $data = $model->where('id', $info['id'])->find();
                if (empty($data)) {
                    return $this->error('数据不存在');
                }
                $res = $model->update($info);
            } else {
                $res = $model->insertGetId($info);
                $info['id'] = $res;
            }
            return $res ? $this->success() : $this->error();
        }
        if (isset($param['find'])) {
            $model = new FakeGameSet();
            $res = $model->where("id", $param['id'])->find();
            return $this->success(['data' => $res]);
        }
        return $this->success();
    }

    //删除
    public function delete() {
        $id = Request::param('id/s');
        if (empty($id)) {
            return $this->error('数据ID有误');
        }
        $model = new FakeGameSet();
        $res = $model->where("id", "in", $id)->delete();
        return $res ? $this->success() : $this->error();
    }
}