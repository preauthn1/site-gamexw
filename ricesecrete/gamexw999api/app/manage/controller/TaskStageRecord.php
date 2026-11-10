<?php


namespace app\manage\controller;


use app\common\lib\Respons;
use app\common\model\TaskStageModel;
use app\common\model\TaskStageRecordModel;
use app\common\Utils;

class TaskStageRecord
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition = [];
        $condition1 = [];
        $taskStageRecord =new TaskStageRecordModel();
        //类型筛选
        if( isset($param['type']) && $param['type'] !== '' ){
            $condition[] = ['tc.type', '=', $param['type']];
        }
        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $condition[]=["tr.create_time",">=", Utils::getQueryDateTime($time[0])];
            $condition[]=["tr.create_time","<=", Utils::getQueryDateTime($time[1], true)];
        }
        //语言筛选
        if( isset($param['lang']) && $param['lang'] !== '' ){
//            $condition[] = ['tc.lang', '=', $param['lang']];
        }
        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $condition[]=["u.id|u.phone","=", $param['username']];
            } else {
                $condition[]=["u.username|u.mail|u.phone","=", $param['username']];
            }
        }
        //领取状态筛选
        if( isset($param['receive_status']) && $param['receive_status'] !== '' ){
            $condition[] = ['tr.receive_status', '=', $param['receive_status']];
        }
        $rsps['data']=$taskStageRecord->alias('tr')
            ->join('user u', 'u.id=tr.uid', 'left')
            ->join('task_stage tc', "tc.id=tr.stage_id", 'left')
            ->where($condition)->where($condition1)
            ->field('tr.*,u.username as user_username,tr.activity,tc.type')
            ->order("tr.id desc")
            ->paginate($limit,false,['query' => $param]);
        //统计
        //完成用户数量
        $data ['finish_user_count'] = $taskStageRecord->alias('tr')
            ->join('user u', 'u.id=tr.uid', 'left')
            ->join('task_stage tc', "tc.id=tr.stage_id", 'left')
            ->where($condition)->where($condition1)
            ->group('tr.uid')->count();
        //完成金额
        $data['finish_amount'] = $taskStageRecord->alias('tr')
            ->join('user u', 'u.id=tr.uid', 'left')
            ->join('task_stage tc', "tc.id=tr.stage_id", 'left')
            ->where($condition)->where($condition1)
            ->sum('reward_amount');
        //完成积分
        $data['finish_integral'] = $taskStageRecord->alias('tr')
            ->join('user u', 'u.id=tr.uid', 'left')
            ->join('task_stage tc', "tc.id=tr.stage_id", 'left')
            ->where($condition)->where($condition1)
            ->sum('reward_integral');
        $rsps['code']=1;
        $rsps['statistics'] = $data;
        return Respons::run($rsps);
    }
}