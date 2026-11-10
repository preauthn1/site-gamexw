<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\ActionTypeModel;
use app\common\model\ReceiveCodeModel;
use app\common\model\TagModel;
use app\common\model\UrlModel;
use app\common\Utils;
use think\facade\View;
use think\Request;
class ReceiveCode
{

    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $receiveCodeModel =new ReceiveCodeModel();
        if (isset($param['title']) && $param['title']!='') {
            $where[]=["title","like","%".$param['title']."%"];
        }
        //领取码
        if( isset($param['code']) && $param['code'] !== '' ){
            $where[] = ['code', '=', $param['code']];
        }
        //领取条件
        if( isset($param['receive_condition']) && $param['receive_condition'] !== '' ){
            $where[] = ['receive_condition', '=', $param['receive_condition']];
        }
        //条件时间
        if( isset($param['condition_date_type']) && $param['condition_date_type'] !== '' ){
            $where[] = ['condition_date_type', '=', $param['condition_date_type']];
        }
        //金额类型
        if( isset($param['amount_type']) && $param['amount_type'] !== '' ){
            $where[] = ['amount_type', '=', $param['amount_type']];
        }
        //状态
        if( isset($param['status']) && $param['status'] !== '' ){
            $where[] = ['status', '=', $param['status']];
        }
        $rsps['data']=$receiveCodeModel
            ->where($where)
            ->order("id desc")
            ->filter(function ($data){
                $w = [];
                $w[] = ['id', 'in', $data['tag_list']];
                $data['tag_list'] = TagModel::where($w)->column('name, color');
            })
            ->paginate($limit,false,['query' => $param]);
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
            if( $res = $this->judgeIsAdd($info) ){
                return $res;
            }
            $info['code'] = Util::random(6);
            $w = [];
            $w[] = ['name', 'in', $info['tag_list']];
            $ids = TagModel::where($w)->column('id');
            $info['tag_list'] = implode(',', $ids);
            $res = ReceiveCodeModel::create($info);
            $info['id'] = $res->id;
            return Respons::run($res?1:0,'','','',$info,new ReceiveCodeModel(),lang('活动类型添加'));
        }
    }


    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $receiveCodeModel = new ReceiveCodeModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            if( $res = $this->judgeIsAdd($info) ){
                return $res;
            }
            $data = $receiveCodeModel->where('id',$info['id'])->find();
            if( $data['receive_num'] != $info['receive_num'] ){
                ajax_return('可领取次数不能修改');
            }
            if( $data['reward_amount'] != $info['reward_amount'] ){
                ajax_return('奖励金额不能修改');
            }
            $w = [];
            $w[] = ['name', 'in', $info['tag_list']];
            $ids = TagModel::where($w)->column('id');
            $info['tag_list'] = implode(',', $ids);
            $res=$receiveCodeModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$receiveCodeModel,lang('活动类型修改'));
        }
        if (isset($param['find'])) {
            $res=$receiveCodeModel->where("id",$param['id'])->find();
            $w = [];
            $w[] = ['id', 'in', $res['tag_list']];
            $ids = TagModel::where($w)->column('name');
            $res['tag_list'] = $ids;
            return Respons::run(1,$res);
        }
    }

    //判断是否能添加
    public function judgeIsAdd($info){
        if( $info['amount_type'] == ReceiveCodeModel::AMOUNT_TYPE_TOTAL && strpos($info['reward_amount'], '-') !== false ){
            return Respons::run(0, [], '累计类型只能设置总金额');
        }
        return false;
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $receiveCodeModel =new ReceiveCodeModel();
            $res=$receiveCodeModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$receiveCodeModel,lang('活动类型删除'));
        }
    }
}