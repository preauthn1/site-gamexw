<?php

namespace app\manage\controller;

use app\common\lottery\App;
use app\common\model\SLotteryPresetModel;
use app\common\model\SLotteryResultModel;
use app\common\model\SLotteryModel;
use app\common\Utils;
use think\facade\Db;
use think\facade\Request;

class SLotteryResult extends Base {

    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list() {
        $lottery_names = SLotteryModel::where('status', 1)->column('name', 'id');
        $lottery_id = $this->request->param('lottery_id/d');
        $qishu = $this->request->param('qishu/s');
        $where = [];
        if (empty($lottery_id)) {
            $lottery_id = !empty($lottery_names) ? array_keys($lottery_names)[0] : 0;
        }
        $lottery = SLotteryModel::where('id', $lottery_id)->find();
        if (empty($lottery)) {
            return $this->error('彩种信息有误');
        }
        $where[] = ["lottery_id", "=", $lottery_id];
        if ($qishu) {
            $where[] = ["qishu", "=", $qishu];
        }
        [$data, $total] = $this->getDataList(new SLotteryResultModel(), $where);
        $obj = App::init($lottery['lottery_type'], $lottery);
        $page = $this->request->param('page/d', 1);
        if ($page == 1) {
            $preset = SLotteryPresetModel::where('qishu', '>', $data ? $data[0]['qishu'] : 0)->where('lottery_id', $lottery_id)->select();
            if (!empty($preset)) {
                foreach ($preset AS $row) {
                    $numbers = explode(",", $row['numbers']);
                    $preset_data = [
                        'lottery_id' => 0,
                        'lottery_name' => '预设开奖',
                        'qishu' => $row['qishu'],
                        'ball_1' => $numbers[0],
                        'ball_2' => $numbers[1],
                        'ball_3' => $numbers[2],
                        'ball_4' => $numbers[3],
                        'ball_5' => $numbers[4],
                        'open_time' => $row['create_time']
                    ];
                    array_unshift($data, $preset_data);
                }
            }
        }
        foreach ($data AS &$value) {
            $balls = [
                $value['ball_1'], $value['ball_2'], $value['ball_3'], $value['ball_4'], $value['ball_5'],
            ];
            if (!isset($value['lottery_name'])) {
                $value['lottery_name'] = $lottery_names[$value['lottery_id']] ?? $value['lottery_id'];
            }
            $value['sum'] = [
                $obj->getResultText($balls,'sum'),
                $obj->getResultText($balls,'sum', 'big_small'),
                $obj->getResultText($balls,'sum', 'single_double'),
                $obj->getResultText($balls,'sum', 'dragon_tiger'),
            ];
            $value['three'] = [
                $obj->getResultText($balls,'before_three'),
                $obj->getResultText($balls,'middle_three'),
                $obj->getResultText($balls,'after_three'),
            ];
        }
        return $this->success([
            'total' => $total,
            'data' => $data,
            'lottery' => $lottery,
            'lotterys' => Utils::getOptions('s_lottery', ['id', 'name'], ['status' => 1]),
            'type' => Utils::getOptionsByConfig(config('lottery.type')),
            'options' => config('lottery.options'),
            'options_text' => config('lottery.options_text'),
        ]);
    }

    //预设开奖
    public function preset() {
        $param = Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            if (empty($info['lottery_id'])) {
                return $this->error('彩种ID有误');
            }
            $lottery = SLotteryModel::where('id', $info['lottery_id'])->find();
            if (empty($lottery)) {
                return $this->error('彩种信息有误');
            }
            if (empty($info['qishu'])) {
                return $this->error('彩种期数有误');
            }
            if (empty($info['result'])) {
                return $this->error('预设结果不能为空');
            }
            $ball_numbers = config('lottery.ball_numbers');
            $numbers = myArrayFilter(explode(",", $info['result']));
            if ($ball_numbers[$lottery['lottery_type']] != count($numbers)) {
                return $this->error('预设结果有误');
            }
            if (isset($info['id']) && $info['id'] > 0) {
                $model = new SLotteryResultModel();
                $res = $model->where('id', $info['id'])->find();
                if (empty($res)) {
                    return $this->error('数据不存在');
                }
                $old = $res->toArray();
                foreach ($numbers AS $i => $value) {
                    if (isset($res->{"ball_{$i}"})) {
                        $res->{"ball_{$i}"} = intval($value);
                    }
                }
                $res->status = 0;
                $res->save();
                Utils::addOptLog($old, $res->toArray(), 's_lottery_result', '修改已开奖结果'); //记录日志
            } else {
                $preset = SLotteryPresetModel::where([
                    'lottery_id' => $info['lottery_id'],
                    'qishu' => $info['qishu'],
                ])->find();
                if ($preset) {
                    $preset->numbers = $info['result'];
                    $preset->save();
                } else {
                    SLotteryPresetModel::create([
                        'lottery_id' => $info['lottery_id'],
                        'qishu' => $info['qishu'],
                        'numbers' => $info['result'],
                    ]);
                }
            }
            return $lottery ? $this->success() : $this->error();
        }

        if (isset($param['find'])) {
            $model = new SLotteryResultModel();
            $res = $model->where("id", $param['id'])->find();
            return $this->success(['data' => $res]);
        }
        return $this->error();
    }

    //删除
    public function delete() {
        $id = Request::param('id/s');
        if (empty($id)) {
            return $this->error('数据ID有误');
        }
        $model = new SLotteryResultModel();
        $res = $model->where("id", "in", $id)->delete();
        return $res ? $this->success() : $this->error();
    }
}