<?php

namespace app\manage\controller;

use app\common\model\SLotteryModel;
use app\common\model\SLotteryOrderModel;
use app\common\model\UserModel;
use app\common\Utils;

class SLotteryOrder extends Base {

    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list() {
        $lottery_id = $this->request->param('lottery_id/s');
        $username = $this->request->param('username/s');
        $status = $this->request->param('status');
        $qishu = $this->request->param('qishu/s');
        $where = [];
        if (!empty($lottery_id)) {
            $where[] = ["lottery_id", "=", $lottery_id];
        }
        if ($status != '' && in_array($status, [0, 1])) {
            $where[] = ["status", "=", $status];
        }
        if (!empty($qishu)) {
            $where[] = ["qishu", "=", $qishu];
        }
        if($username){
            $uid = UserModel::where([['id|username|mail|phone', '=', $username]])->value("id");
            $where[] = ["uid", "=", $uid];
        }
        $lottery_names = SLotteryModel::column('name', 'id');
        $lottery_type = SLotteryModel::column('lottery_type', 'id');
        [$data, $total] = $this->getDataList(new SLotteryOrderModel(), $where);
        $options_text = config('lottery.options_text');
        $users = [];
        foreach ($data AS &$value) {
            $value['lottery_name'] = $lottery_names[$value['lottery_id']] ?? $value['lottery_id'];
            $option_text = [];
            if (isset($options_text[$lottery_type[$value['lottery_id']]])) {
                $option_text = $options_text[$lottery_type[$value['lottery_id']]];
            }
            $value['option_text'] = ($option_text[$value['odd_group']] ?? $value['odd_group']) . "[".($option_text[$value['odd_name']] ?? $value['odd_name'])."]";

            if (!isset($users[$value['uid']])) {
                $users[$value['uid']] = UserModel::where('id', $value['uid'])->field("id,username,mail,phone")->find();
            }
            $value['username'] = $users[$value['uid']]['username'] ?? '';
            $value['mail'] = $users[$value['uid']]['mail'] ?? '';
            $value['phone'] = $users[$value['uid']]['phone'] ?? '';
            $value = Utils::hideUserInfo($value);
        }
        return $this->success([
            'total' => $total,
            'data' => $data,
            'where' => $where,
            'lotterys' => Utils::getOptions('s_lottery', ['id', 'name']),
        ]);
    }
}