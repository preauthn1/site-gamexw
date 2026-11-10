<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\TaskContentModel;
use app\common\model\UrlModel;
use app\common\Utils;
use app\common\validate\TaskContentValidate;
use think\exception\ValidateException;
use think\Request;
class TaskContent
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $taskContentModel =new TaskContentModel();
        if (isset($param['task_id']) && $param['task_id']!=='') {
            $where[]=["task_id","=",$param['task_id']];
        }
        $rsps['data']=$taskContentModel
            ->where($where)
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['code']=1;
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            //验证数据
            try {
                validate(TaskContentValidate::class)->scene('add')->check($info);
            }catch (ValidateException $e){
                return Respons::run(0, [], $e->getError());
            }
            //判断是否存在
            if( Utils::judgeDataExist(new TaskContentModel(), $info, ['lang', 'task_id']) ){
                return Respons::run(0, [], '该语言已存在');
            }
            $info['create_time'] = $info['update_time'] = time();
            $res = TaskContentModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new UrlModel(),lang('任务内容添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $taskContentModel = new TaskContentModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            //验证数据
            try {
                validate(TaskContentValidate::class)->scene('add')->check($info);
            }catch (ValidateException $e){
                return Respons::run(0, [], $e->getError());
            }
            //判断是否存在
            if( Utils::judgeDataExist(new TaskContentModel(), $info, ['lang', 'task_id', 'id'], $info['id']) ){
                return Respons::run(0, [], '该语言已存在');
            }
            $data = $taskContentModel->where('id',$info['id'])->find();
            $res=$taskContentModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$taskContentModel,lang('任务内容修改'));
        }
        if (isset($param['find'])) {
            $res=$taskContentModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $taskContentModel =new TaskContentModel();
            $res=$taskContentModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$taskContentModel,lang('任务内容删除'));
        }
    }
}