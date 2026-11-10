<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\LotteryOnlineTaskModel;
use app\common\model\LotteryOnlineTaskRecordModel;
use app\common\Utils;
use think\Request;
class LotteryTask
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition=[];
        $lotteryOnlineTaskModel =new LotteryOnlineTaskModel();
        //类型
        if( isset($param['type']) && $param['type'] !== '' ){
            $condition[] = ['type', '=', $param['type']];
        }
        //类型
        if( isset($param['status']) && $param['status'] !== '' ){
            $condition[] = ['status', '=', $param['status']];
        }
        //语言
        if( isset($param['lang']) && $param['lang'] !== '' ){
            $condition[] = ['lang', '=', $param['lang']];
        }
        $paginate=$lotteryOnlineTaskModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        $rsps['tags']= Utils::getOptions('tag');
        $rsps['vip'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            $info['create_time'] = $info['update_time'] = time();
            //一种语言只能创建一个类型
            if ( $result = $this->validateAddOrEdit($info) ){
                return $result;
            }
            $res = LotteryOnlineTaskModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new LotteryOnlineTaskModel(),lang('转盘任务添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $lotteryOnlineTaskModel = new LotteryOnlineTaskModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            //一种语言只能创建一个类型
            if ( $result = $this->validateAddOrEdit($info) ){
                return $result;
            }
            $data = $lotteryOnlineTaskModel->where('id',$info['id'])->find();
            $res=$lotteryOnlineTaskModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$lotteryOnlineTaskModel,lang('转盘任务修改'));
        }
        if (isset($param['find'])) {
            $res=$lotteryOnlineTaskModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //
    public function validateAddOrEdit($info){
        $condition = [];
        $condition[] = ['lang', '=', $info['lang']];
        $condition[] = ['type', '<>', $info['type']];
        if( isset($info['id']) && $info['id'] ){
            $condition[] = ['id', '<>', $info['id']];
        }
        $detail = LotteryOnlineTaskModel::where($condition)->find();
        if( $detail ){
            return Respons::run(0, [], '一种语言只能创建一种类型');
        }
        return null;
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $lotteryOnlineTaskModel =new LotteryOnlineTaskModel();
            $res=$lotteryOnlineTaskModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$lotteryOnlineTaskModel,lang('转盘任务删除'));
        }
    }

    //签到记录
    public function recordLists(){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition=[];
        $lotteryOnlineTaskRecordModel =new LotteryOnlineTaskRecordModel();
        //UID
        if( isset($param['uid']) && $param['uid'] !== '' ){
            $condition[] = ['uid', '=', $param['uid']];
        }
        //任务id
        if( isset($param['task_id']) && $param['task_id'] !== '' ){
            $condition[] = ['task_id', '=', $param['task_id']];
        }
        if( isset($param['type']) && $param['type'] !== '' ){
            $condition[] = ['type', '=', $param['type']];
        }
        //语言筛选
        $paginate=$lotteryOnlineTaskRecordModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        //统计
        $statistics = [
            'receive_amount' => $lotteryOnlineTaskRecordModel->where($condition)->sum('receive_amount'),//领取总金额
            'receive_integral' => $lotteryOnlineTaskRecordModel->where($condition)->sum('receive_integral'),//领取总积分
        ];
        $rsps['statistics'] = $statistics;
        return Respons::run($rsps);
    }
}