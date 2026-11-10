<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\PayAgentModel;
use app\common\model\PayAgentRecordModel;
use app\common\model\UserOfflineModel;
use app\common\Utils;
use app\common\validate\PayAgentValidate;
use app\manage\factory\PayAgentFactory;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class PayAgent {

    protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function lists(\think\Request $request){
        $rsps = (new PayAgentFactory($request->token->uid))->lists();
        return $rsps;
    }

    //添加
    public function add(){
        $param=Request::param();
        if (isset($param['add'])) {
            $info = $param['info'];
            try {
                validate(PayAgentValidate::class)->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }

            if(!checkString($info['password'])){
                return Respons::run(0,[], "密码只能数字和字母组合");
            }
            if($info['username'] == $info['password']){
                return Respons::run(0,[], "密码不能与用户名相同");
            }

            $info['password'] = think_ucenter_md5($info['password'], config("custom.member_key"));
            $res = PayAgentModel::create($info);
            $info['id'] = $res;
            return Respons::run($res ? 1 : 0,'','','', $info, new PayAgentModel(), lang('添加银商账号'));
        }
        return View::fetch('pay_agent/add');
    }

    //编辑
    public function edit(){
        $param=Request::param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            try {
                validate(PayAgentValidate::class)->scene('edit')->check($info);
            } catch (\think\exception\ValidateException $e) {
                // 验证失败 输出错误信息
                return Respons::run(0, [], $e->getError());
            }
            $PayAgentModel = new PayAgentModel();
            $upd['id'] = $info['id'];
            $upd['realname'] = $info['realname'];
            $upd['nickname'] = $info['nickname'];
            $upd['status'] = $info['status'];

            if(isset($info['password'])) {
                if(!checkString($info['password'])){
                    return Respons::run(0,[], "密码只能数字和字母组合");
                }
                if($info['username'] == $info['password']){
                    return Respons::run(0,[], "密码不能与用户名相同");
                }
                $upd['password'] = think_ucenter_md5($info['password'], config("custom.member_key"));
            }
            $data = $PayAgentModel->where('id',$info['id'])->find();
            if (empty($data)) {
                return Respons::run(0, [], '数据有误');
            }
            $res = $PayAgentModel->update($upd);
            return Respons::run($res?1:0,'','',$data,$upd,$PayAgentModel,lang('银商账号修改'));
        }

        if (isset($param['find'])) {
            $PayAgentModel =new PayAgentModel();
            $res=$PayAgentModel->where("id",$param['id'])->withoutField('password')->find();
            return Respons::run(1,$res);
        }
        View::assign("id",$param['id']);
        return View::fetch('pay_agent/edit');
    }

    //删除
    public function delete(){
        $param=Request::param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            if (empty($id)) {
                return Respons::run(0, [], '数据有误');
            }
            $res = PayAgentModel::destroy(explode(",", $id));
            return Respons::run($res?1:0, null,'',$param,'',new PayAgentModel(),lang('银商账号删除'));

        }
    }

    // 银商上分
    public function recharge() {
        $param = Request::instance()->param();
        if(Request::isPost()){
            $info = $param['info'];
            $money = floatval($info['money']);
            $remark = $info['remark'];
            if ($money <= 0) {
                return Respons::run(0, [], '充值金额有误');
            }
            $ainfo = PayAgentModel::where("id", $info['id'])->find();
            if (empty($ainfo)) {
                return Respons::run(0, [], '银商信息不存在');
            }
            Db::startTrans();
            try {
                $agent = PayAgentModel::where("id", $ainfo['id'])->lock(true)->find();
                //增加余额明细
                PayAgentRecordModel::create([
                    'agent_id' => $agent['id'],
                    'money' => $money,
                    'before_money' => $agent->balance,
                    'remark' => $remark,
                    'ip' => request()->ip()
                ]);

                //更新余额
                $agent->balance += $money;
                $agent->save();
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return Respons::run(0, [], $e->getMessage());
            }
            return Respons::run(1, '','', [
                'id' => $ainfo['id'],
                'balance' => $ainfo['balance'],
            ],[
                'id' => $ainfo['id'],
                'balance' => "{$agent->balance} | 充值金额:{$money}",
            ], new PayAgentModel(),lang('银商账号充值'));
        }
        View::assign("id", $param['id']);
        return View::fetch('pay_agent/recharge');
    }

    //明细
    public function record(){
        $param = Request::instance()->param();
        if(Request::isAjax()){
            $page = $param['page'];
            $limit = $param['limit'];
            $offset = ($page-1)*$limit;
            $where = [];
            $where[] = ['agent_id', '=', $param['id']];
            if(!empty($param['date']) && isset($param['date'])){
                $date=explode(' - ',$param['date']);
                $where[]=["create_time",">=", Utils::getQueryDateTime($date[0])];
                $where[]=["create_time","<=", Utils::getQueryDateTime($date[1], true)];
            }
            $rsps['data'] = PayAgentRecordModel::where($where)
                ->order("id desc")
                ->limit($offset, $limit)->select();

            foreach($rsps['data'] as &$val){

            }
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        View::assign("id", $param['id']);
        return View::fetch('pay_agent/record');
    }

}