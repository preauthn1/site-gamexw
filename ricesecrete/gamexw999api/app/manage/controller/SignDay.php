<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\SignModel;
use app\common\model\SignDayModel;
use app\common\Utils;
use app\common\validate\TaskValidate;
use think\exception\ValidateException;
use think\facade\Db;
use think\Request;
class SignDay
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition=[];
        $signDayModel =new SignDayModel();
        if( isset($param['sign_id']) && $param['sign_id'] !== '' ){
            $condition[] = ['sign_id', '=', $param['sign_id']];
        }
        //签到天数
        if( isset($param['day']) && $param['day'] !== '' ){
            $condition[] = ['day', '=', $param['day']];
        }
        //奖励模式筛选
        if( isset($param['reward_mode']) && $param['reward_mode'] !== '' && in_array($param['reward_mode'], SignModel::getRewardModelArr()) ){
            $condition[] = ['reward_mode', '=', $param['reward_mode']];
        }
        //奖励类型筛选
        if( isset($param['reward_type']) && $param['reward_type'] !== '' && in_array($param['reward_type'], SignModel::getRewardTypeArr()) ){
            $condition[] = ['reward_type', '=', $param['reward_type']];
        }
        $paginate=$signDayModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            $info['create_time'] = $info['update_time'] = time();
            $res = SignDayModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new SignDayModel(),lang('签到天数添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $signDayModel = new SignDayModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $signDayModel->where('id',$info['id'])->find();
            $res=$signDayModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$signDayModel,lang('签到天数修改'));
        }
        if (isset($param['find'])) {
            $res=$signDayModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $signDayModel =new SignDayModel();
            $res=$signDayModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$signDayModel,lang('签到天数删除'));
        }
    }
}