<?php

namespace app\manage\controller;

use app\common\FastGame\Base\Game;
use app\common\lib\Respons;
use app\model\ThirdConfigs;
use app\model\UserBalanceTranslog;
use think\Request;

class ThirdLog
{

    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function lists()
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $userBalanceTransLogModel = new UserBalanceTranslog();
        //UID
        if (isset($param['ubtl_uid']) && $param['ubtl_uid'] !== '') {
            $condition[] = ['ubtl_uid', '=', $param['ubtl_uid']];
        }
        if (isset($param['ubtl_tcid']) && $param['ubtl_tcid'] !== '') {
            $condition[] = ['ubtl_tcid', '=', $param['ubtl_tcid']];
        }
        if (isset($param['ubtl_type']) && $param['ubtl_type'] !== '') {
            $condition[] = ['ubtl_type', '=', $param['ubtl_type']];
        }
        if (isset($param['ubtl_status']) && $param['ubtl_status'] !== '') {
            $condition[] = ['ubtl_status', '=', $param['ubtl_status']];
        }
        $ThirdConfigsModel = new ThirdConfigs();
        $third_configs_arr = $ThirdConfigsModel->column('tc_name', 'tc_id');
        //语言筛选
        $paginate = $userBalanceTransLogModel
            ->where($condition)
            ->field('*')
            ->order("ubtl_id desc")
            ->paginate($limit, false, ['query' => $param])->each(function ($v, $index) use ($third_configs_arr) {
                $v['tc_name'] = $third_configs_arr[$v['ubtl_tcid']] ?? '';
                //处理时间
                $v['ubtl_intime'] = date('Y-m-d H:i:s', $v['ubtl_intime']);
                $v['ubtl_errproc'] = ($v['ubtl_errproc'] && is_numeric($v['ubtl_errproc'])) ? date('Y-m-d H:i:s', $v['ubtl_errproc']) : $v['ubtl_errproc'];
            });
        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //重新处理
    public function roll(Request $request)
    {
        $param = $request->param();
        $userBalanceTransLogModel = new UserBalanceTranslog();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $data = $userBalanceTransLogModel->where('ubtl_id', $info['ubtl_id'])->find();
            //判断是平台转游戏 还是游戏转平台
            if ($data->ubtl_type == 4 && ($data->ubtl_status == 2 || $data->ubtl_status == 1)) {
                //游戏转平台
                $result = \app\common\FastGame\Base\Game::rollbackWithdrawThird($info['ubtl_id'], $info['is_restart'], $info['ubtl_errprocinfo'] ?: '');
                //从平台转出余额
                $data = \app\common\FastGame\Base\Game::withdrawThird($info['ubtl_tcid'], $info['ubtl_uid']);
            } elseif ($data->ubtl_type == 3 && ($data->ubtl_status == 2 || $data->ubtl_status == 1)) {
                //平台转游戏
                $result = \app\common\FastGame\Base\Game::rollbackDepositThird($info['ubtl_id'], $info['is_restart'], $info['ubtl_errprocinfo'] ?: '');
            } else {
                return Respons::run(0, [], '该数据暂不能处理');
            }
            if ($result !== true) {
                return Respons::run(0, [], $result);
            }
            return Respons::run(1, '', '', $data, $info, $userBalanceTransLogModel, lang('重新处理用户余额转账日志'));
        }
        if (isset($param['find'])) {
            $res = $userBalanceTransLogModel->where("ubtl_id", $param['id'])->find();
            //查询三方余额
            if ($res) {
                $res['third_balance'] = Game::getThirdBalance($res['ubtl_tcid'], $res['ubtl_uid']) ?: 0;
            }
            return Respons::run(1, $res);
        }
    }

}