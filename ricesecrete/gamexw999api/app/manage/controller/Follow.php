<?php
namespace app\manage\controller;
use app\common\model\{UserMsgModel,UserModel,UserDistributionModel,UserAdminLogModel,DistributionConfigModel,UserFollowModel};
use app\common\Utils;
use app\manage\factory\UserFactory;
use app\manage\factory\UserReportsFactory;
use think\facade\Session;
use think\facade\View;
use think\Request;
use think\facade\Db;
use app\common\lib\Respons;
use app\common\model\UserReportsModel;
use app\common\model\AdminModel;
use app\common\model\AdminRoleModel;
use app\common\redis\CommomRedis;
use app\common\lib\Util;

class Follow
{
    protected $middleware = ['ManageCheckLogin',"Language"];

    //会员报表
    public function userreport(Request $request)
    {
        $rsps = (new UserReportsFactory($request->token))->list();
        return $rsps;
    }

    //批量派发
    public function assignsave(Request $request){
        $par = $request->param();
        if(empty($par['admin_id']) || empty($par['ids'])){
            return Respons::run(0,[],lang("数据不能为空"));
        }
        $info = AdminModel::where(['id'=>$par['admin_id'],'status'=>1])->field('role_id,username')->find();
        if(!$info){
            return Respons::run(0,[],lang("管理员不存在"));
        }
        $time = time();
        $role_name = AdminRoleModel::where(['id'=>$info['role_id']])->value('role_name');
        foreach ($par['ids'] as $v){
            $foll = UserFollowModel::where(['uid'=>$v,'salesman_id'=>$par['admin_id'],'televised_status'=>1])->value('id');
            if($foll){
                return Respons::run(0,[],lang("用户UID【{:uid}】重复派发", ['uid' => $v]));
            }
            $add = [
                'uid'=>$v,
                'salesman_id'=>$par['admin_id'],
                'role_id'=>$info['role_id'],
                'add_time'=>$time,
                'televised_status'=>1,
            ];
            $id = UserFollowModel::insertGetId($add);
            $data = [
                'admin_group'=>$role_name,
                'admin_name'=>$info['username'],
                'admin_id'=>$par['admin_id'],
                'role_id'=>$info['role_id'],
                'televised_status'=>1,
                'assign_time'=>$time,
                'follow_id'=>$id,
            ];
            UserReportsModel::where([['uid','=',$v]])->update($data);
        }
        $ids = implode(',',$par['ids']);
        $data['uid'] = $ids;
        $data['assign_time'] = date('Y-m-d H:i:s',$data['assign_time']);
        return Respons::run(1,[],lang("操作成功"),'', $data,  new UserReportsModel(), '回访用户批量派发');
    }


    //赠金派发设置
    public function distributionconfig(Request $request){
        $par = $request->param();
        if ($par['max_price'] < 0 || $par['water'] < 0 || $par['timeout'] < 0) {
            ajax_return("请输入正确的数值", 0);
        }
        $upd = [
            'type'=>$par['type'],
            'max_price'=>$par['max_price'],
            'water'=>$par['water'],
            'timeout'=>$par['timeout'],
        ];
        $info = DistributionConfigModel::where('id',1)->find();
        DistributionConfigModel::update($upd,['id'=>1]);
        $upd['id'] = 1;
        return Respons::run(1,[],lang("操作成功"),$info, $upd,  new DistributionConfigModel(), '赠金派发设置');
    }

    //回访列表
    public function pendingfollow(Request $request){
        $rsps = (new UserReportsFactory($request->token))->treat_list();
        return $rsps;
    }

    //回访列表编辑
    public function save(Request $request){
        $param = $request->param();
        $cof = DistributionConfigModel::where('id',1)->find();
        if (isset($param['find'])) {
            $res =  UserReportsModel::where([['uid','=',$param['id']]])->find();
            $res['water'] = $cof['water'];
            return Respons::run(1,$res);
        }
        if (isset($param['edit'])) {
            $admin_id = $request->token->uid;
            $uid = $param['info']['uid'];
            $info=$param['info'];
            $field = 'uid,is_connected,sms_status,intention_status,is_distribution,televised_status,feedback,remark,televised,sms_time,follow_id';
            $res =  UserReportsModel::where([['uid','=',$uid]])->field($field)->find();
            if($info['is_distribution'] == 2 && $info['price'] > 0){
                if (!is_numeric($info['price']) || !is_numeric($info['water'])) {
                    return Respons::run(0,[],lang('数据格式错误'));
                }
                if($info['price'] < 0 || $info['water'] < 0){
                    ajax_return("请输入正确的数值", 0);
                }

                if ($info['price']<=0 || $info['price']=='') {
                    return Respons::run(0,[],lang('请输入派发金额'));
                }

                if($cof['max_price'] > 0 && $info['price'] > $cof['max_price']){
                    ajax_return("派发金额不能超过".$cof['max_price'], 0);
                }

                $ud = UserDistributionModel::where(['follow_id'=>$res['follow_id'],'salesman_id'=>$admin_id,'give_status'=>0])->value('id');
                if($ud){
                    ajax_return("赠金记录已存在，请勿重复派发", 0);
                }

                UserDistributionModel::where(['uid'=>$uid,'give_status'=>0])->update(['give_status'=>2]);

                $info['water'] = intval($info['water']);
                $data = [
                    'uid'=>$uid,
                    'price'=>$info['price'],
                    'type'=>$info['type'],
                    'water'=>$info['water'],
                    'add_time'=>time(),
                    'salesman_id'=>$admin_id,
                    'follow_id'=>$res['follow_id'],
                ];
                if($cof['type'] == 0){
                    UserDistributionModel::insertGetId($data);
                }else{
                    if($info['type'] == 1){
                        UserModel::cz_balance($uid,$info['price'],"+",true,'赠金派发', 37, $info['water'], 1, 0, true, 101);
                        $fee = sprintf("%.2f",$info['price']*$info['water']);
                        $data['give_status'] = 1;
                        $data['give_time'] = time();
                        UserAdminLogModel::add_log($uid, "赠金派发, 金额: {$info['price']}, 流水: {$fee}", Session::get("adminInfo.name"));
                    }
                    $msg = $info['type'] == 1 ? '赠金已到账,到账金额 {:price}':'赠金已发放,发放金额 {:price},请手动领取赠金';
                    $data['status'] = 2;
                    $id = UserDistributionModel::insertGetId($data);
//                    UserMsgModel::add_msg($uid, "赠金派发", $msg, 30, $id,['price'=> Util::i18n_number_format($info['price'])]);
                }
                Respons::run(1,[],lang("操作成功"),'', $info,  new UserDistributionModel(), '用户赠金派发');
            }else{
                UserDistributionModel::where(['uid'=>$uid,'give_status'=>0])->update(['give_status'=>2]);
            }
            $upd = [
                'is_connected'=>$info['is_connected'],
                'sms_status'=>$info['sms_status'],
                'intention_status'=>$info['intention_status'],
                'is_distribution'=>$info['is_distribution'],
                'televised_status'=>$info['televised_status'],
                'feedback'=>$info['feedback'],
                'remark'=>$info['remark'],
            ];
            $upd['televised'] = in_array($info['televised_status'],[2,3]) ? time():0;
            $upd['sms_time']  = $info['sms_status'] == 1 ? time():0;
            UserReportsModel::where([['uid','=',$uid]])->update($upd);
            $upd2 = $upd;
            $upd2['admin_id'] = $admin_id;
            UserFollowModel::where([['id','=',$res['follow_id']]])->update($upd2);
            $upd['uid'] = $uid;
            return Respons::run(1,[],lang("操作成功"),$res, $upd,  new UserReportsModel(), '回访用户编辑');
        }
    }

    //赠金列表
    public function distribute(Request $request){
        $rsps = (new UserReportsFactory($request->token))->distribute_list();
        return $rsps;
    }

    //审核
    public function distributesave(Request $request){
        $info = $request->param('info');
        if ($info['ids']== '' || empty($info['ids'])) {
            return Respons::run(0,[],lang('请选择用户'));
        }

        if ($info['price'] != '' && $info['price'] < 0 || $info['water'] != '' && $info['water'] < 0) {
            return Respons::run(0,[],lang('请输入正确的数值'));
        }

        $info['water'] = intval($info['water']);
        foreach($info['ids'] as $val) {
            $da = UserDistributionModel::where([["id",'=',$val],['status','=',0],['give_status','<>',2]])->find();
            if (!isset($da)) {
                return Respons::run(0,[],lang('数据不存在'));
            }
            $data = [
                'add_time'=>time(),
                'status'=>$info['status'],
                'admin_id'=>$request->token->uid,
            ];
            if($info['status'] == 1) {
                if ($info['price'] > 0) {
                    $data['price'] = $info['price'];
                    $price = $info['price'];
                }else{
                    $price = $da['price'];
                }
                if ($info['water'] > 0) {
                    $data['water'] = $info['water'];
                    $water = $info['water'];
                }else{
                    $water = $da['water'];
                }

                if ($da['type'] == 1) {
                    UserModel::cz_balance($da['uid'], $price, "+", true, '赠金派发', 37, $water, 1, $val, true, 101);
                    $fee = sprintf("%.2f", $price * $water);
                    $data['give_status'] = 1;
                    $data['give_time'] = time();
                    UserAdminLogModel::add_log($da['uid'], "赠金派发, 金额: {$price}, 流水: {$fee}", Session::get("adminInfo.name"));
                }
//                $msg = $da['type'] == 1 ? '赠金已到账,到账金额 {:price}':'赠金已发放,发放金额 {:price},请手动领取赠金';
//                UserMsgModel::add_msg($da['uid'], "赠金派发", $msg, 30, $val, ['price' => Util::i18n_number_format($price)]);
            }else{
                $data['give_status'] = 2;
            }
            UserDistributionModel::update($data,['id'=>$val]);
        }
        $info['id'] = implode(',',$info['ids']);
        Db::commit();
        return Respons::run(1,[],lang("操作成功"),'', $info,  new UserDistributionModel(), '批量赠金派发');
    }

    //回访报表
    public function reports(Request $request){
        $rsps = (new UserReportsFactory($request->token))->reports();
        return $rsps;
    }

    //回访派发记录
    public function followlist(Request $request){
        $rsps = (new UserReportsFactory($request->token))->follow_list();
        return $rsps;
    }
}
