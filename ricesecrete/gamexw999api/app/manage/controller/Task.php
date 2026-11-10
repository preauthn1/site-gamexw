<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\TaskModel;
use app\common\model\TaskStageModel;
use app\common\model\TaskTypeModel;
use app\common\Utils;
use app\common\validate\TaskValidate;
use app\model\ThirdConfigs;
use think\exception\ValidateException;
use think\facade\Db;
use think\Request;
class Task
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition=[];
        $taskModel =new TaskModel();
        //类型筛选
        if( isset($param['type']) && $param['type'] !== '' ){
            $condition[] = ['type', '=', $param['type']];
        }
        $condition1 = '';
        //任务类型筛选
        if( isset($param['task_condition']) && $param['task_condition'] !== '' ){
            $condition1 = 'find_in_set('.$param['task_condition'].',task_condition)';
        }
        //奖励类型筛选
        if( isset($param['reward_type']) && $param['reward_type'] !== '' ){
            $condition[] = ['reward_type', '=', $param['reward_type']];
        }
        //任务模式筛选
        if( isset($param['task_mode']) && $param['task_mode'] !== '' ){
            $condition[] = ['task_mode', '=', $param['task_mode']];
        }
        //阶段任务筛选
        if( isset($param['stage_id']) && $param['stage_id'] > 0 ){
            $condition[] = ['stage_id', '=', $param['stage_id']];
        }
        //阶段筛选
        if( isset($param['stage']) && $param['stage'] > 0 ){
            $condition[] = ['stage', '=', $param['stage']];
        }
        //状态
        if( isset($param['status']) && $param['status'] !== '' ){
            $condition[] = ['status', '=', $param['status']];
        }
        //语言筛选
        $paginate=$taskModel
            ->where($condition)->where($condition1)
            ->field('*')
            ->order("sort desc,id desc")
            ->paginate($limit,false,['query' => $param])->each(function ($v, $index)use($param){
                //处理任务条件
                $v['task_condition'] = explode(',', $v['task_condition']);
                //任务标题
                $condition  = [];
                $condition[] = ['lang', '=', $param['lang']];
                $condition[] = ['task_id', '=', $v['id']];
                $v['task_title'] = Db::name('task_content')->where($condition)->value('title');
                if( $v['start_time'] ){
                    $v['start_time'] = date('Y-m-d H:i:s', $v['start_time']);
                }
                if( $v['end_time'] ){
                    $v['end_time'] = date('Y-m-d H:i:s', $v['end_time']);
                }
            });
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        $rsps['tags']= Utils::getOptions('tag');
        $rsps['vip'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $rsps['wufu'] = Utils::getOptions('wufu', ['id', 'name']);
        $rsps['task_type'] = Utils::getOptions('task_type');
        $rsps['stage_task_list'] = Utils::getOptions('task_stage');
        $rsps['stage_list']= TaskStageModel::where('status','1')->field('id,reg_json')->select();
        foreach ($rsps['stage_list'] AS $key => $value){
            $value['reg_json'] = json_decode($value['reg_json'],true);
            $data = [ 'stage_id'=>$value['id'], 'stage'=>[] ];
            foreach ($value['reg_json'] AS $item){
                $data['stage'][] = ['label' => lang("阶段")." ".$item['stage'], 'value' => (int)$item['stage']];
            }
            $rsps['stage_list'][$key] = $data;
        }
        $rsps['gamesThird']= Utils::getOptions('third_configs',['tc_id', 'tc_name'],['tc_status'=>1]);
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
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
            //验证数据
            try {
                validate(TaskValidate::class)->scene('add')->check($info);
            }catch (ValidateException $e){
                return Respons::run(0, [], $e->getError());
            }
            //判断流水类型
            if( isset($info['water_type']) ){
                if( $info['water_type'] == 1 ){
                    //指定平台
                    if( !isset($info['platform_ids']) || !$info['platform_ids'] ){
                        return Respons::run(0,[],'请选择流水对应平台');
                    }
                    $info['water_type_ids'] = implode(',', ThirdConfigs::where('tc_name', 'in', $info['platform_ids'])->column('tc_id'));
                }elseif( $info['water_type'] == 2 ){
                    //指定游戏
                    if( !isset($info['game_ids']) || !$info['game_ids'] ){
                        return Respons::run(0,[],'请选择流水对应游戏');
                    }
                    $info['water_type_ids'] = $info['game_ids'];
                }
            }
            unset($info['game_ids']);
            unset($info['platform_ids']);
            //处理任务条件
            $info['task_condition'] = implode(',', $info['task_condition']);
            $info['start_time'] = strtotime($info['start_time']);
            $info['end_time'] = strtotime($info['end_time']);
            $info['create_time'] = $info['update_time'] = time();
            $res = TaskModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new TaskModel(),lang('任务添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $taskModel = new TaskModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
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
            //验证数据
            try {
                validate(TaskValidate::class)->scene('add')->check($info);
            }catch (ValidateException $e){
                return Respons::run(0, [], $e->getError());
            }
            //判断流水类型
            if( isset($info['water_type']) ){
                if( $info['water_type'] == 1 ){
                    //指定平台
                    if( !isset($info['platform_ids']) || !$info['platform_ids'] ){
                        return Respons::run(0,[],'请选择流水对应平台');
                    }
                    $info['water_type_ids'] = implode(',', ThirdConfigs::where('tc_name', 'in', $info['platform_ids'])->column('tc_id'));
                }elseif( $info['water_type'] == 2 ){
                    //指定游戏
                    if( !isset($info['game_ids']) || !$info['game_ids'] ){
                        return Respons::run(0,[],'请选择流水对应游戏');
                    }
                    $info['water_type_ids'] = $info['game_ids'];
                }
            }
            unset($info['platform_ids']);
            unset($info['game_ids']);
            //处理任务条件
            $info['task_condition'] = implode(',', $info['task_condition']);
            $data = $taskModel->where('id',$info['id'])->find();
            $info['start_time'] = strtotime($info['start_time']);
            $info['end_time'] = strtotime($info['end_time']);
            $res=$taskModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$taskModel,lang('任务修改'));
        }
        if (isset($param['find'])) {
            $res=$taskModel->where("id",$param['id'])->find();
            if( $res ){
                //处理任务条件（后台前端使用过）
                $_taskCondition = explode(',', $res->task_condition);
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
                $res->task_condition = $taskCondition;
                //处理开始和结束时间
                $res->start_time = $res->start_time?date('Y-m-d H:i:s', $res->start_time):'';
                $res->end_time = $res->end_time?date('Y-m-d H:i:s', $res->end_time):'';
                //根据流水类型获取平台ids和游戏ids
                $res['platform_ids'] = [];
                $res['game_ids'] = '';
                if( $res['water_type'] == 1 ){
                    //指定平台
                    $res['platform_ids'] = ThirdConfigs::where('tc_id', 'in', $res['water_type_ids'])->column('tc_name');
                }elseif ( $res['water_type'] == 2 ){
                    //指定游戏
                    $res['game_ids'] = $res['water_type_ids'];
                }
                unset($res['water_type_ids']);
            }
            return Respons::run(1,$res);
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

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $taskModel =new TaskModel();
            $res=$taskModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$taskModel,lang('任务删除'));
        }
    }
}