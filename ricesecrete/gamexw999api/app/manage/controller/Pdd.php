<?php
namespace app\manage\controller;
use app\common\lib\Respons;
use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableModel;
use app\common\model\PddTurntableRecordModel;
use app\common\model\PddWithdrawalRecordModel;
use app\common\model\SignRecordModel;
use app\common\Utils;
use app\common\validate\TaskValidate;
use app\manage\factory\PddInviteRecordFactory;
use app\manage\factory\PddwithdrawalRecordFactory;
use think\exception\ValidateException;
use think\facade\Db;
use think\Request;
class Pdd
{
    protected $middleware = ['ManageCheckLogin',"Language"];
    //列表
    public function lists(\think\Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $pddTurntableModel =new PddTurntableModel();
        $condition = [];
        if( isset($param['lang']) && $param['lang'] !== '' ){
            $condition[] = ['lang', '=', $param['lang']];
        }
        if( isset($param['status']) && $param['status'] !== '' ){
            $condition[] = ['status', '=', $param['status']];
        }
        $paginate=$pddTurntableModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        $rsps['langs'] = Utils::getOptions('lang', ['lang', 'lang'], ['status' => 1]);
        return Respons::run($rsps);
    }

    //添加
    public function add(Request $request){
    	$param = $request->param();
    	if (isset($param['add'])) {
            $info=$param['info'];
            unset($info['id']);
            $info['create_time'] = $info['update_time'] = time();
            $info['start_time'] = $info['start_time'] ? strtotime($info['start_time']) : '';
            $info['end_time'] = $info['end_time'] ? strtotime($info['end_time']) : '';
            $res = PddTurntableModel::insertGetId($info);
            $info['id'] = $res;
            return Respons::run($res?1:0,'','','',$info,new PddTurntableModel(),lang('pdd转盘添加'));
        }
    }

    //编辑
    public function edit(Request $request){
        $param = $request->param();
        $pddTurntableModel = new PddTurntableModel();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $data = $pddTurntableModel->where('id',$info['id'])->find();
            $res=$pddTurntableModel->update($info);
            return Respons::run($res?1:0,'','',$data,$info,$pddTurntableModel,lang('pdd转盘修改'));
        }
        if (isset($param['find'])) {
            $res=$pddTurntableModel->where("id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }

    //删除
    public function delete(Request $request){
        $param = $request->param();
        if (isset($param['delete'])) {
            $id=$param['id'];
            $pddTurntableModel =new PddTurntableModel();
            $res=$pddTurntableModel->where("id","in",$id)->delete();
            return Respons::run($res?1:0,'','',$param,'',$pddTurntableModel,lang('pdd转盘删除'));
        }
    }

    //邀请记录
    public function inviteRecord(Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition=[];
        $pddInviteRecordModel =new PddInviteRecordModel();
        //UID
        if( isset($param['uid']) && $param['uid'] !== '' ){
            $condition[] = ['pir.uid', '=', $param['uid']];
        }
        //类型
        if( isset($param['type']) && $param['type'] !== '' ){
            $condition[] = ['pir.type', '=', $param['type']];
        }
        //用户username
        if( isset($param['username']) && $param['username'] !== '' ){
            $condition[] = ['pir.username', 'like', '%'.$param['username'].'%'];
        }
        //邀请用户id
        if( isset($param['invite_uid']) && $param['invite_uid'] !== '' ){
            $condition[] = ['pir.invite_uid', '=', $param['invite_uid']];
        }
        //邀请用户username
        if( isset($param['invite_username']) && $param['invite_username'] !== '' ){
            $condition[] = ['pir.invite_username', 'like', '%'.$param['invite_username'].'%'];
        }
        //抽奖状态
        if( isset($param['status']) && $param['status'] !== '' ){
            $condition[] = ['pir.status', '=', $param['status']];
        }
        //取款状态
        if( isset($param['is_withdrawal']) && $param['is_withdrawal'] !== '' ){
            $condition[] = ['pir.is_withdrawal', '=', $param['is_withdrawal']];
        }
        //筛选时间
        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $condition[]=["pir.create_time",">=", Utils::getQueryDateTime($time[0])];
            $condition[]=["pir.create_time","<=", Utils::getQueryDateTime($time[1], true)];
        }
        //是否有充值
        if (isset($param['is_recharge']) && $param['is_recharge'] !== '') {
            if( $param['is_recharge'] == 1 ){
                //有充值
                $condition[] = ['u.sc_balance', '>', 0];
            }else{
                //没有充值
                $condition[] = ['u.sc_balance', '=', 0];
            }
        }
        $condition1 = '';
        //是否是第一次抽奖
        if( isset($param['is_first']) && $param['is_first'] !== '' ){
            if( $param['is_first'] == 1 ){
                //第一次抽奖
                $condition1 = 'pir.uid=pir.invite_uid';
            }else{
                //不是第一次抽奖
                $condition1 = 'pir.uid!=pir.invite_uid';
            }
        }
        //导出数据
        if (isset($param['export']) && $param['export'] == 1) {
            return (new PddInviteRecordFactory($request->token->uid))->exportList($condition,$condition1);
        }
        //语言筛选
        $paginate=$pddInviteRecordModel->alias('pir')->join('user u', 'u.id=pir.invite_uid')
            ->where($condition)->where($condition1)
            ->field('pir.*')
            ->order("pir.id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        //统计
        $statistics = [
            'take_user_count' => $pddInviteRecordModel->alias('pir')->join('user u', 'u.id=pir.invite_uid')->where($condition)->where($condition1)->group('uid')->count(),//参与用户
            'invite_user_count' => $pddInviteRecordModel->alias('pir')->join('user u', 'u.id=pir.invite_uid')->where($condition)->where($condition1)->group('invite_uid')->count(),//邀请用户数
            'finish_user_count' => $pddInviteRecordModel->alias('pir')->join('user u', 'u.id=pir.invite_uid')->where($condition)->where($condition1)->where('withdrawal_amount', '>', 0)->group('uid')->count(),//完成用户数
            'finish_amount' => $pddInviteRecordModel->alias('pir')->join('user u', 'u.id=pir.invite_uid')->where($condition)->where($condition1)->sum('withdrawal_amount'),//完成金额
        ];
        $rsps['statistics'] = $statistics;
        return Respons::run($rsps);
    }

    //抽奖记录
    public function turntableRecord(){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition=[];
        $pddTurntableRecordModel =new PddTurntableRecordModel();
        if( isset($param['invite_record_id']) && $param['invite_record_id'] !== '' ){
            $condition[] = ['invite_record_id', '=', $param['invite_record_id']];
        }
        //类型
        if( isset($param['type']) && $param['type'] !== '' ){
            $condition[] = ['type', '=', $param['type']];
        }
        //取款状态
        if( isset($param['is_withdrawal']) && $param['is_withdrawal'] !== '' ){
            $condition[] = ['is_withdrawal', '=', $param['is_withdrawal']];
        }
        //筛选时间
        if (isset($param['date']) && $param['date']!='') {
            $time=explode(" - ",$param['date']);
            $condition[]=["create_time",">=", Utils::getQueryDateTime($time[0])];
            $condition[]=["create_time","<=", Utils::getQueryDateTime($time[1], true)];
        }
        //语言筛选
        $paginate=$pddTurntableRecordModel
            ->where($condition)
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        //统计
        $statistics = [
            'take_user_count' => $pddTurntableRecordModel->where($condition)->group('uid')->count(),//参与用户
            'finish_amount' => $pddTurntableRecordModel->where($condition)->sum('withdrawal_amount'),//完成金额
        ];
        $rsps['statistics'] = $statistics;
        return Respons::run($rsps);
    }

    //提款记录
    public function withdrawalRecord(Request $request){
        $param= \think\facade\Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $condition=[];
        $pddWithdrawalRecordModel =new PddWithdrawalRecordModel();
        //UID
        if( isset($param['uid']) && $param['uid'] !== '' ){
            $condition[] = ['uid', '=', $param['uid']];
        }
        //用户username
        if( isset($param['username']) && $param['username'] !== '' ){
            $condition[] = ['username', 'like', '%'.$param['username'].'%'];
        }
        //导出数据
        if (isset($param['export']) && $param['export'] == 1) {
            return (new PddwithdrawalRecordFactory($request->token->uid))->exportList($condition,'');
        }
        //语言筛选
        $paginate=$pddWithdrawalRecordModel
            ->where($condition)
            ->field('*')
            ->order("id desc")
            ->paginate($limit,false,['query' => $param]);
        $rsps['data'] = $paginate;
        $rsps['code']=1;
        //统计
        $statistics = [
            'user_count' => $pddWithdrawalRecordModel->where($condition)->group('uid')->count(),//参与用户
            'finish_amount' => $pddWithdrawalRecordModel->where($condition)->sum('amount'),//完成金额
        ];
        $rsps['statistics'] = $statistics;
        return Respons::run($rsps);
    }
}