<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\model\LotteryConfigModel;
use app\common\model\TaskModel;
use app\common\Utils;
use think\Request;

class LotteryConfig
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request)
    {
        $param = \think\facade\Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $lotteryConfigModel = new LotteryConfigModel();
        //类型
        if (isset($param['theme_type']) && $param['theme_type'] !== '') {
            $condition[] = ['theme_type', '=', $param['theme_type']];
        }
        if (isset($param['task_condition']) && $param['task_condition'] !== '') {
            $condition = 'find_in_set(' . $param['task_condition'] . ',task_condition)';
        }
        $paginate = $lotteryConfigModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->filter(function($data){
                $data['starttime'] = date("Y-m-d H:i:s",$data['starttime']);
                $data['endtime'] = date("Y-m-d H:i:s",$data['endtime']);
            })->paginate($limit, false, ['query' => $param]);

        $rsps['data'] = $paginate;
        $rsps['code'] = 1;
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request)
    {
        $param = $request->param();
        if (isset($param['add'])) {
            $info = $param['info'];
            unset($info['id']);
            $info['create_time'] = $info['update_time'] = time();
            //处理任务条件
            $info['task_condition'] = $this->handleTaskCondition($info['task_condition']);
            //任务条件中含有累计充值、累计下注、邀请人数时任务数量为1
            if(
                in_array(TaskModel::CUMULATIVE_RECHARGE_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::CUMULATIVE_BET_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::ONLINE_TIME_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::GAME_ONLINE_TIME_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::APP_LOGIN_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::ADD_BANK_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::INVITE_PEOPLE_TASK_CONDITION, $info['task_condition'])
            ){
                $info['task_num'] = 1;
            }
            //处理任务条件
            $info['task_condition'] = implode(',', $info['task_condition']);
            $info['starttime'] = strtotime($info['starttime']);
            $info['endtime'] = strtotime($info['endtime']);

            $res = LotteryConfigModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0, '', '', '', $info, new LotteryConfigModel(), lang('转盘添加'));
        }
    }

    //编辑
    public function edit(Request $request)
    {
        $param = $request->param();
        $lotteryConfigModel = new LotteryConfigModel();
        if (isset($param['edit'])) {
            $info = $param['info'];
            //处理任务条件
            $info['task_condition'] = $this->handleTaskCondition($info['task_condition']);
            //任务条件中含有累计充值和累计下注时任务数量为1
            if(
                in_array(TaskModel::CUMULATIVE_RECHARGE_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::CUMULATIVE_BET_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::ONLINE_TIME_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::GAME_ONLINE_TIME_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::APP_LOGIN_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::ADD_BANK_TASK_CONDITION, $info['task_condition']) ||
                in_array(TaskModel::INVITE_PEOPLE_TASK_CONDITION, $info['task_condition'])
            ){
                $info['task_num'] = 1;
            }
            //处理任务条件
            $info['task_condition'] = implode(',', $info['task_condition']);
            $data = $lotteryConfigModel->where('id', $info['id'])->find();
            $info['starttime'] = strtotime($info['starttime']);
            $info['endtime'] = strtotime($info['endtime']);
            $res = $lotteryConfigModel->update($info);
            return Respons::run($res ? 1 : 0, '', '', $data, $info, $lotteryConfigModel, lang('转盘修改'));
        }
        if (isset($param['find'])) {
            $data = $lotteryConfigModel->where("id", $param['id'])->find();
            //处理任务条件（后台前端使用过）
            $_taskCondition = explode(',', $data['task_condition']);
            $taskCondition = [];
            foreach ($_taskCondition as $v){
                if( $v == TaskModel::MIN_BET_TASK_CONDITION ){
                    array_push($taskCondition, '单笔最低下注');
                }elseif( $v == TaskModel::MIN_RECHARGE_TASK_CONDITION ){
                    array_push($taskCondition, '单笔最低充值');
                }elseif( $v == TaskModel::MIN_MULTIPLE_TASK_CONDITION ){
                    array_push($taskCondition, '最低倍数');
                }elseif( $v == TaskModel::CUMULATIVE_RECHARGE_TASK_CONDITION ){
                    array_push($taskCondition, '累计充值');
                }elseif( $v == TaskModel::ONLINE_TIME_TASK_CONDITION ){
                    array_push($taskCondition, '在线时长');
                }elseif( $v == TaskModel::CUMULATIVE_BET_TASK_CONDITION ){
                    array_push($taskCondition, '累计下注');
                }elseif( $v == TaskModel::GAME_ONLINE_TIME_TASK_CONDITION ){
                    array_push($taskCondition, '游戏时长');
                }elseif( $v == TaskModel::INVITE_PEOPLE_TASK_CONDITION ){
                    array_push($taskCondition, '邀请人数');
                }elseif( $v == TaskModel::APP_LOGIN_TASK_CONDITION ){
                    array_push($taskCondition, 'APP登录');
                }elseif( $v == TaskModel::ADD_BANK_TASK_CONDITION ){
                    array_push($taskCondition, '添加银行卡');
                }
            }
            $data['task_condition'] = $taskCondition;
            $data['starttime'] = date("Y-m-d H:i:s",$data['starttime']);
            $data['endtime'] = date("Y-m-d H:i:s",$data['endtime']);
            return Respons::run(1, $data);
        }
    }

    //删除
    public function delete(Request $request)
    {
        $param = $request->param();
        if (isset($param['delete'])) {
            $id = $param['id'];
            if ($id == 1) {
                return Respons::run(0, '', '此数据不允许删除');
            }
            $lotteryConfigModel = new LotteryConfigModel();
            $res = $lotteryConfigModel->where("id", "in", $id)->delete();
            return Respons::run($res ? 1 : 0, '', '', $param, '', $lotteryConfigModel, lang('转盘删除'));
        }
    }

    //处理任务条件
    public function handleTaskCondition($taskCondition){
        $arr = [];
        foreach ($taskCondition as $v){
            if( $v == '单笔最低下注' ){
                $arr[] = TaskModel::MIN_BET_TASK_CONDITION;
            }elseif ( $v == '单笔最低充值' ){
                $arr[] = TaskModel::MIN_RECHARGE_TASK_CONDITION;
            }elseif ( $v == '最低倍数' ){
                $arr[] = TaskModel::MIN_MULTIPLE_TASK_CONDITION;
            }elseif ( $v == '累计充值' ){
                $arr[] = TaskModel::CUMULATIVE_RECHARGE_TASK_CONDITION;
            }elseif ( $v == '在线时长' ){
                $arr[] = TaskModel::ONLINE_TIME_TASK_CONDITION;
            }elseif ( $v == '累计下注' ){
                $arr[] = TaskModel::CUMULATIVE_BET_TASK_CONDITION;
            }elseif ( $v == '游戏时长' ){
                $arr[] = TaskModel::GAME_ONLINE_TIME_TASK_CONDITION;
            }elseif ( $v == '邀请人数' ){
                $arr[] = TaskModel::INVITE_PEOPLE_TASK_CONDITION;
            }elseif ( $v == 'APP登录' ){
                $arr[] = TaskModel::APP_LOGIN_TASK_CONDITION;
            }elseif ( $v == '添加银行卡' ){
                $arr[] = TaskModel::ADD_BANK_TASK_CONDITION;
            }
        }
        return $arr;
    }
}