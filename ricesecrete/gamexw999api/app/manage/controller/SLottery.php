<?php

namespace app\manage\controller;

use app\common\model\SLotteryModel;
use app\common\Utils;
use think\facade\Db;
use think\facade\Request;

class SLottery extends Base {

    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list() {
        $name = $this->request->param('name/s');
        $where = [];
        if (!empty($name)) {
            $where[] = ["name", "like", "%{$name}%"];
        }
        [$data, $total] = $this->getDataList(new SLotteryModel(), $where);

        return $this->success([
            'total' => $total,
            'data' => $data,
            'type' => Utils::getOptionsByConfig(config('lottery.type')),
            'options' => config('lottery.options'),
            'options_text' => config('lottery.options_text'),
        ]);
    }

    //添加
    public function add() {
        $param = Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            if (!isset($info['lottery_th']) || ($info['lottery_th'] > 0 && 1440 % $info['lottery_th'] != 0)) {
                return $this->error('彩票期数设置有误');
            }
            if (isset($info['id']) && $info['id'] > 0) {
                $model = new SLotteryModel();
                $lottery = $model->where('id', $info['id'])->find();
                if (empty($lottery)) {
                    return $this->error('数据不存在');
                }
                $model->update($info);
            } else {
                $lottery = SLotteryModel::create($info);
            }
            event(new \app\event\LotteryOpenTimeEvent($lottery));
            return $lottery ? $this->success() : $this->error();
        }
        if (isset($param['find'])) {
            $model = new SLotteryModel();
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
        $model = new SLotteryModel();
        $res = $model->where("id", "in", $id)->delete();
        return $res ? $this->success() : $this->error();
    }


    public function setodd() {
        $lottery_id = $this->request->param('lottery_id/d');
        $lottery_odds = $this->request->param('lottery_odds/a');
        if (empty($lottery_odds) || empty($lottery_id)) {
            return $this->error('参数有误');
        }
        $model = new SLotteryModel();
        $lottery = $model->where("id", $lottery_id)->find();
        if (empty($lottery)) {
            return $this->error('彩种数据不存在');
        }
        $options = config('lottery.options');
        $lottery_options = $options[$lottery['lottery_type']];
        $odds = [];
        foreach ($lottery_odds AS $key => $val) {
            list($group, $opt) = explode("___", $key);
            if (!isset($lottery_options[$group]) || !in_array($opt, $lottery_options[$group])) {
                continue;
            }
            $odd_info = Db::name('s_lottery_odds')->where([
                'lottery_id' => $lottery_id,
                'bet_group' => $group,
                'bet_name' => $opt,
            ])->find();
            if (!empty($odd_info)) {
                Db::name('s_lottery_odds')->where([
                    'id' => $odd_info['id']
                ])->update(['bet_odd' => floatval($val)]);
            } else {
                $odds[] = [
                    'lottery_id' => $lottery_id,
                    'bet_group' => $group,
                    'bet_name' => $opt,
                    'bet_odd' => floatval($val),
                    'bet_tag' => '',
                    'status' => 1,
                    'sort' => 0
                ];
            }
        }

        if (!empty($odds)) {
            Db::name('s_lottery_odds')->insertAll($odds);
        }

        return $this->success([
            'id' => $lottery_id,
            'odds' => $odds,
        ]);
    }


    public function getodd() {
        $lottery_id = $this->request->param('lottery_id/d');
        if (empty($lottery_id)) {
            return $this->error('参数有误');
        }
        $model = new SLotteryModel();
        $lottery = $model->where("id", $lottery_id)->find();
        if (empty($lottery)) {
            return $this->error('彩种数据不存在');
        }
        $odds = [];
        $odd_info = Db::name('s_lottery_odds')->where([
            'lottery_id' => $lottery_id,
        ])->select();
        if ($odd_info) {
            foreach ($odd_info AS $item) {
                $odds["{$item['bet_group']}___{$item['bet_name']}"] = floatval($item['bet_odd']);
            }
        } else {
            return $this->error('彩种赔率未设置');
        }
        return $this->success([
            'odds' => $odds,
        ]);
    }
}