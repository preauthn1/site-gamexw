<?php


namespace app\manage\controller;


use app\common\lib\Respons;
use app\common\model\TaskStageModel;
use app\common\model\TaskTypeModel;
use app\common\Utils;
use think\facade\Db;
use think\Request;

class TaskStage
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    public function lists(Request $request){
        $param= $request->param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where = [];
        $taskStage = new TaskStageModel();
        //名称筛选
        if (isset($param['name']) && $param['name']!=='') {
            $where[]=["name","like","%".$param['name']."%"];
        }
        //类型筛选
        if (isset($param['type']) && $param['type']!=='') {
            $where[]=["type","=",$param['type']];
        }
        //状态
        if (isset($param['status']) && $param['status']!=='') {
            $where[]=["status","=",$param['status']];
        }
        //语言筛选
        $paginate=$taskStage
            ->where($where)
            ->order("type ASC")
            ->paginate($limit,false,['query' => $param]);
        foreach ($paginate AS &$item){
            $item['reg_json'] = json_decode($item['reg_json'],true);
        }
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        $rsps['tags']= Utils::getOptions('tag');
        $rsps['vip'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        $rsps['type'] = Utils::getOptions('task_type');
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
        $param = $request->param();
        if (isset($param['add'])) {
            $info=$param['info'];
            unset($info['base_type']);
            unset($info['id']);
            $info['reg_json'] = json_encode($info['reg_json']);
            $info['create_time'] = $info['update_time'] = time();
            $typeId = $info['type_id']??0;
            unset($info['type_id']);
            $res = TaskStageModel::insertGetId($info);
            //选择分类后将分类对应的阶段任务id改成当前阶段的
            if( $typeId ){
                TaskTypeModel::update([
                    'stage_id' => $res
                ], ['id' => $typeId]);
            }
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new TaskStageModel(),lang('阶段任务添加'));
        }
    }

    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $taskStageModel = new TaskStageModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $taskStageModel->where('id',$info['id'])->find();
            $info['reg_json'] = json_encode($info['reg_json']);
            $typeId = $info['type_id']??0;
            unset($info['type_id']);
            $res=$taskStageModel->update($info);
            //选择分类后将分类对应的阶段任务id改成当前阶段的
            if( $typeId ){
                TaskTypeModel::update([
                    'stage_id' => $info['id']
                ], ['id' => $typeId]);
            }
            return Respons::run($res?1:0,'','',$data,$info,$taskStageModel,lang('阶段任务修改'));
        }
        if (isset($param['find'])) {
            $res=$taskStageModel->where("id",$param['id'])->find();
            //获取对应的分类id
            $res['type_id'] = TaskTypeModel::where('stage_id', '=', $param['id'])->value('id');
            $res['reg_json'] = json_decode($res['reg_json'],true);
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $TaskStageModel =new TaskStageModel();
            $res=$TaskStageModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$TaskStageModel,lang('阶段任务删除'));
        }
    }

    // 开启/关闭阶段奖励
    public function mode(Request $request){
        $param = $request->param();
        if (isset($param['status'])) {
            $id=$param['id'];
            $TaskStageModel =new TaskStageModel();
            $res=$TaskStageModel->where("id","in",$id)->update(['status'=>$param['status']]);
            return Respons::run($res?1:0,'','',$param,'',$TaskStageModel,lang('任务'.$param['status']==1?'开启':'关闭'));
        }
    }
}