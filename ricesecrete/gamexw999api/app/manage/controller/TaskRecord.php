<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\TaskModel;
use app\common\model\TaskRecordModel;
use app\common\Utils;
use app\manage\factory\TaskRecordFactory;
use think\facade\Db;

class TaskRecord
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition = [];
        $condition1 = [];
        $taskRecordModel =new TaskRecordModel();
        //任务标题筛选
        if (isset($param['title']) && $param['title']!=='') {
            $condition[]=["tc.title","like",'%'.$param['title'].'%'];
        }
        //类型筛选
        if( isset($param['type']) && $param['type'] !== '' ){
            $condition[] = ['tr.type', '=', $param['type']];
        }
        //任务条件筛选
        if( isset($param['task_condition']) && $param['task_condition'] !== '' ){
            $condition1 = 'find_in_set('.$param['task_condition'].',tr.task_condition)';
        }
        //奖励类型筛选
        if( isset($param['reward_type']) && $param['reward_type'] !== '' ){
            $condition[] = ['tr.reward_type', '=', $param['reward_type']];
        }
        //领取状态筛选
        if( isset($param['receive_status']) && $param['receive_status'] !== '' ){
            $condition[] = ['tr.receive_status', '=', $param['receive_status']];
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
        if (isset($param['export']) && $param['export'] == 1) {
            return (new TaskRecordFactory($request->token->uid))->exportList($condition,'');
        }
        $rsps['data']=$taskRecordModel->alias('tr')
            ->join('user u', 'u.id=tr.uid', 'left')
            ->join('task_content tc', "tc.task_id=tr.task_id", 'left')
            ->where($condition)->where($condition1)
            ->field('tr.*,u.username as user_username,tc.title as task_title')
            ->order("tr.id desc")
            ->group('tr.id')
            ->paginate($limit,false,['query' => $param])->each(function ($v, $index)use($param){
                //任务标题
                $condition  = [];
                $condition[] = ['lang', '=', $param['lang']];
                $condition[] = ['task_id', '=', $v['id']];
                $v['task_title'] = Db::name('task_content')->where($condition)->value('title');
            });
        //统计
        //完成用户数量
        $data   ['finish_user_count'] = $taskRecordModel->alias('tr')
            ->join('user u', 'u.id=tr.uid', 'left')
            ->join('task_content tc', "tc.task_id=tr.task_id", 'left')
            ->where($condition)->where($condition1)
            ->group('tr.uid')->count();
        //完成金额
        $amountArr = $taskRecordModel->alias('tr')
            ->join('user u', 'u.id=tr.uid', 'left')
            ->join('task_content tc', "tc.task_id=tr.task_id", 'left')
            ->where($condition)->where($condition1)
            ->where('tr.reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)
            ->group('tr.id')->column('tr.reward_amount_or_integral');
        $data['finish_amount'] = array_sum($amountArr);
        $rsps['code']=1;
        $rsps['statistics'] = $data;
        return Respons::run($rsps);
    }

}