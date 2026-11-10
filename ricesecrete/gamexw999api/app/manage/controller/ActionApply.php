<?php
namespace app\manage\controller;
use app\common\model\ActionApplyModel;
use app\common\model\GamesModel;
use app\common\model\IntegralMxModel;
use app\common\model\UserAwardCfgsModel;
use app\common\model\UserAwardLogsModel;
use app\common\model\UserCkModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\lib\Respons;
use app\common\service\MemberService;
use app\common\service\MessageService;
use app\common\service\WalletService;
use app\manage\factory\ActionApplyFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;
use think\facade\Db;
use app\common\redis\CommomRedis;
class ActionApply extends Base
{	
	protected $middleware = ['ManageCheckLogin',"Language"];

    //列表
    public function list(\think\Request $request){
        $rsps = (new ActionApplyFactory($request->token->uid))->list();
        return $rsps;
    }

    //一键派送
    public function distribute(Request $request) {
        $id = $request->param('id');
        $ids = explode(",", $id);
        if (empty($ids)) {
            return $this->error('请选择要派发的数据');
        }
        foreach ($ids as $v){
            $this->ps($v);
        }
        return $this->success();
    }


    //派发奖励
    public function ps($id = ''){
        $param = $this->request->param();
        if($id){
            $param['info']['ps_status'] = 1;
            $param['info']['id'] = $id;
        }
        $info = $param['info'];
        $CommomRedis= new CommomRedis();
        if (!$CommomRedis->lock('applyps'.$info['id'])) {
            return Respons::run(0,[],lang('请勿频繁访问'));
        }
        Db::startTrans();
        try {
            $ActionApplyModel = new ActionApplyModel();
            $UserCkModel = new UserCkModel();
            $UserModel = new UserModel();
            $apply=$ActionApplyModel
                ->alias("a")
                ->join("dq_user b","a.uid=b.id")
                ->join("dq_action c","a.action_id=c.id")
                ->where("a.id",$info['id'])
                ->field("a.*,c.base_type,c.water_type,c.water_type_ids,c.water as action_water,c.wallet_water_type")
                ->find();
            if (!isset($apply['id'])) {
                return Respons::run(0,'',lang("数据不存在"));
            }
            if ($apply['ps_status']==1) {
                return Respons::run(0,'',lang("请勿重复派送"));
            }
            //获取用户数据
            $userDetail = UserModel::uinfo($apply['uid']);
            //双钱包判断当前用户是否可以重复领取优惠
            if( !WalletService::judgeUserIsRePeatReceive(0, $userDetail) && $apply['wallet_water_type'] == 1 ){
                ajax_return(lang('用户[{:uid}]账户还存在彩金，无法派送', ['uid' => $apply['uid']]));
            }

            //验证实名认证
            if( !MemberService::judgeUserIsCanReceiveDiscount($apply['uid']) ){
                ajax_return("[{$userDetail['id']}]未实名认证，无法领取");
            }
            //TODO $TYPE中空格不能去除
            $type = ['', '注册首存','再存优惠','一般优惠','体验金','','游戏救援金','推荐奖励'];
            $msg_content = '';
            if($info['ps_status'] == 1){
                $msg = $type[$apply['base_type']] ?? '';
                $amount = $apply['ck_hl_amount'];

                $actionWater = $apply['action_water'];
                if( in_array($apply['water_type'], [1, 2]) ){
                    //指定流水不加用户流水
                    $actionWater = 0;
                }
                $priceType = 1;
                if( $apply['wallet_water_type'] == 0  ){
                    //流水钱包类型（0真金1彩金），真金时重置priceType
                    $priceType = 0;
                }
                if ($apply['base_type']==1 || $apply['base_type']==2) {
                    $ck=$UserCkModel->where("id",$apply['user_ck_id'])->find();
                    if (!isset($ck['id'])) {
                        return Respons::run(0,'',lang("存款数据不存在"));
                    }
                    if ($ck['ck_status']==1) {
                        return Respons::run(0,'',lang("存款数据已派送，请勿重复派送"));
                    }
                    if ($ck['ck_status']==2) {
                        return Respons::run(0,'',lang("存款数据已拒绝派送，请勿重复操作"));
                    }
                    $msg=$apply['base_type']==1?'注册首存奖励':'再存优惠奖励';
                    $UserModel->cz_balance($apply['uid'],$ck['ck_hl_amount'],"+",true,$msg, 5, $actionWater, $priceType, $apply['id'], true, 101);
                    $upd_ck['id']=$ck['id'];
                    $upd_ck['ck_status'] = $info['ps_status'];
                    $UserCkModel->update($upd_ck);
                }elseif($apply['base_type']==3){
                    $msg = "优惠固定奖励";
                    $amount = $apply['gd_amount'];
                    $UserModel->cz_balance($apply['uid'],$apply['gd_amount'],"+",true,$msg, 5, $actionWater, $priceType, $apply['id'], true, 101);
                }elseif($apply['base_type']==4){
                    $UserModel->cz_balance($apply['uid'],$apply['tyj_amount'],"+",true,"体验金", 5, $actionWater, $priceType, $apply['id'], true, 101);
                    $day="+".$apply['tyj_date'].' day';
                    $upd_user['id']=$apply['uid'];
                    $upd_user['ty_balance']=$apply['tyj_amount'];
                    $upd_user['ty_end_date']=strtotime($day);
                    $UserModel->update($upd_user);
                    $amount = $apply['tyj_amount'];
                }elseif($apply['base_type']==6){
                    $UserModel->cz_balance($apply['uid'],$apply['ck_hl_amount'], "+", true, '游戏救援金', 5, $actionWater, $priceType, $apply['id'], true, 101);
                }elseif($apply['base_type']==7){
                    $UserModel->cz_balance($apply['uid'],$apply['ck_hl_amount'], "+", true, '推荐奖励', 5, $actionWater, $priceType, $apply['id'], true, 101);
                }
                $upd['ps_deta'] = time();
                $upd['ps_status'] = $info['ps_status'];
                $ActionApplyModel->where("id",$apply['id'])->update($upd);
                if( in_array($apply['water_type'], [1, 2]) ){
                    UserAwardCfgsModel::createData($apply, $amount);
                    MemberService::addUserWaterDetail($apply['uid'], $apply['water'], 2, '活动优惠', $apply['id'], $amount, $apply['action_water']);
                }
                if ($apply['integral']) {
                    IntegralMxModel::cz_mx($apply['uid'], 1, $apply['integral'], '活动积分奖励');
                    MessageService::sendJsonMsg([
                        'msg' => "活动积分奖励",
//                        'amount' => $apply['integral']
                        'amount' => 0
                    ], $apply['uid'], 'activity', 5, "活动优惠");
                }
                if ($msg) {
                    MessageService::sendJsonMsg([
                        'msg' => $msg,
                        'amount' => $amount
                    ], $apply['uid'], 'activity', 5, "活动优惠");
                }
                $msg_content = '审批通过。请查收。';
            }else if($info['ps_status'] == 2){
                $upd = [];
                $upd['ps_deta'] = time();
                $upd['ps_status'] = $info['ps_status'];
                $ActionApplyModel->where("id",$apply['id'])->update($upd);
                $msg_content = '审批未通过。具体原因请联系客服。';
            }
            UserMsgModel::add_msg($apply['uid'],"优惠审批",lang($type[$apply['base_type']-1].$msg_content),2,$apply['id']);
            // 提交事务
            Db::commit();
            return Respons::run(1,'','',$apply,$upd,$ActionApplyModel,lang('优惠申请派送状态修改'));
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return Respons::run(0,[],$e->getMessage());
        }
    }


}
