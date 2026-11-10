<?php

namespace app\manage\controller;

use app\api\factory\RocketActivityFactory;
use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptParams;
use app\common\model\RocketActivityBoxLogModel;
use app\common\model\RocketActivityFuelLogModel;
use app\common\model\RocketActivityModel;
use app\common\model\RocketActivityRecordModel;
use app\common\model\SignDayModel;
use app\common\model\SignModel;
use app\common\Utils;
use think\Request;

class RocketActivity
{
    protected $middleware = ['ManageCheckLogin', "Language"];
    //列表
    public function lists(\think\Request $request)
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $rocketActivityModel = new RocketActivityModel();
        //活动名称
        if (isset($param['name']) && $param['name'] !== '') {
            $condition[] = ['name|introduce', 'like', "%" . $param['name'] . "%"];
        }
        $paginate = $rocketActivityModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->filter(function ($data) {
                $data['start_time'] = $data['start_time'] ? date('Y-m-d H:i:s', $data['start_time']) : '';
                $data['end_time'] = $data['end_time'] ? date('Y-m-d H:i:s', $data['end_time']) : '';
            })
            ->paginate($limit, false, ['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        $rsps['tags'] = Utils::getOptions('tag');
        $rsps['vip'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            $info['create_time'] = $info['update_time'] = time();
            $info['start_time'] = strtotime($info['start_time']);
            $info['end_time'] = strtotime($info['end_time']);
            unset($info['id']);
            $res = RocketActivityModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new RocketActivityModel(), lang('火箭活动添加'));
        }
    }

    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $rocketActivityModel = new RocketActivityModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $rocketActivityModel->where('id', $info['id'])->find();
            $info['update_time'] = time();
            $info['start_time'] = strtotime($info['start_time']);
            $info['end_time'] = strtotime($info['end_time']);
            $res = $rocketActivityModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $rocketActivityModel, lang('火箭活动修改'));
        }
        if (isset($param['find'])) {
            $res = $rocketActivityModel->where("id", $param['id'])->find();
            $res['start_time'] = date("Y-m-d H:i:s", $res['start_time']);
            $res['end_time'] = date("Y-m-d H:i:s", $res['end_time']);
            return Respons::run(1, $res);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            $rocketActivityModel = new RocketActivityModel();
            $res = $rocketActivityModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $rocketActivityModel, lang('火箭活动删除'));
        }
    }

    /**
     * 参与记录列表
     * @return \think\response\Json
     * @throws \think\db\exception\DbException
     */
    public function recordLists(Request $request)
    {
        $param = $request->param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;

        $condition = [];
        $rocketActivityRecordModel = new RocketActivityRecordModel();
        //活动id
        if (isset($param['rid']) && $param['rid'] !== '') {
            $condition[] = ['rid', '=', $param['rid']];
        }
        if (isset($param['uid']) && $param['uid'] !== '') {
            $condition[] = ['uid', '=', $param['uid']];
        }
        if (isset($param['now_level']) && $param['now_level'] !== '') {
            $condition[] = ['now_level', '=', $param['now_level']];
        }
        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
            $condition[] = ["create_time", ">=", Utils::getQueryDateTime($time[0])];
            $condition[] = ["create_time", "<=", Utils::getQueryDateTime($time[1], true)];
        }
        if (isset($param['export']) && $param['export'] == 1) {
            $rocketActivityFactory = new RocketActivityFactory($request->token->uid);
            $where1 = "";
            return $rocketActivityFactory->exportList($condition, $where1);
        }
        $paginate = $rocketActivityRecordModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->filter(function ($data) {
                $ac_data = RocketActivityModel::where('id', $data['rid'])->field('name')->find();
                $data['r_name'] = $ac_data['name'];
            })
            ->paginate($limit, false, ['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        //统计
        $statistics = [
            'user_count' => $rocketActivityRecordModel->group('uid')->count(),//参与游戏的用户数量
            'play_count' => $rocketActivityRecordModel->where('now_mileage', '>', 0)->group('uid')->count(),//加过燃料用户数量
            'mileage_sum' => $rocketActivityRecordModel->sum('now_mileage'),//总里程
        ];
        $rsps['statistics'] = $statistics;
        return Respons::run($rsps);
    }

    /**
     * 宝箱领取记录
     * @return \think\response\Json
     * @throws \think\db\exception\DbException
     */
    public function boxLists()
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $rocketActivityBoxLogModel = new RocketActivityBoxLogModel();
        //活动id
        if (isset($param['rid']) && $param['rid'] !== '') {
            $condition[] = ['rid', '=', $param['rid']];
        }
        if (isset($param['uid']) && $param['uid'] !== '') {
            $condition[] = ['uid', '=', $param['uid']];
        }
        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
            $condition[] = ["create_time", ">=", Utils::getQueryDateTime($time[0])];
            $condition[] = ["create_time", "<=", Utils::getQueryDateTime($time[1], true)];
        }
        $paginate = $rocketActivityBoxLogModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->filter(function ($data) {
                $ac_data = RocketActivityModel::where('id', $data['rid'])->field('name')->find();
                $data['r_name'] = $ac_data['name'];
            })
            ->paginate($limit, false, ['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    /**
     * 燃料加注记录
     * @return \think\response\Json
     * @throws \think\db\exception\DbException
     */
    public function fuelLists()
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $rocketActivityFuelLogModel = new RocketActivityFuelLogModel();
        //活动id
        if (isset($param['rid']) && $param['rid'] !== '') {
            $condition[] = ['rid', '=', $param['rid']];
        }
        if (isset($param['uid']) && $param['uid'] !== '') {
            $condition[] = ['uid', '=', $param['uid']];
        }
        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
            $condition[] = ["create_time", ">=", Utils::getQueryDateTime($time[0])];
            $condition[] = ["create_time", "<=", Utils::getQueryDateTime($time[1], true)];
        }
        $paginate = $rocketActivityFuelLogModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->filter(function (&$data) {
                $ac_data = RocketActivityModel::where('id', $data['rid'])->field('name')->find();
                $data['r_name'] = $ac_data['name'];
            })
            ->paginate($limit, false, ['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }
}