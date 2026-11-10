<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\lib\Uploads;
use app\common\model\AdminModel;
use app\common\model\AdminRoleModel;
use app\common\model\ServiceModel;
use app\common\model\UserAgentModel;
use app\common\model\UserDistributionModel;
use app\common\model\DistributionConfigModel;
use app\common\model\UserFollowModel;
use app\common\model\UserModel;
use app\common\model\UserReportsModel;
use app\common\Utils;
use think\facade\Db;
use think\facade\Request;

class UserReportsFactory
{
    private $uid;//用户id
    //验证
    public function __construct($token){
        $this->uid=$token->uid;
        $this->name=$token->name;
    }

    public function list(){
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $where = [];

        if (isset($param['order']) && $param['order'] != '') {
            if($param['field'] == 'offline_day'){
                $param['field'] = 'login_time';
                $order = $param['field'].' '.($param['order'] == 'DESC' ? 'ASC':'DESC');
            }else{
                $order = $param['field'].' '.$param['order'];
            }
        } else {
            $order = 'login_time asc,televised_status asc';
        }

        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $where[]=["uid|phone", "=", $param['username']];
            } else {
                $where[]=["username|mail|phone", "=", $param['username']];
            }
        }

        $fields = ['deposit_nums', 'deposit_total', 'withdrawal_nums', 'withdrawal_total'];

        foreach ($fields AS $field) {
            if (isset($param[$field]) && $param[$field] !== '') {
                if (strexists($param[$field], "-")) {
                    $arr = explode("-", $param[$field]);
                    if (isset($arr[0]) && $arr[0] > 0) {
                        $where[]=["{$field}", ">=", intval($arr[0])];
                    }
                    if (isset($arr[1]) && $arr[1] > 0) {
                        $where[]=["{$field}", "<=", intval($arr[1])];
                    }
                } else {
                    $where[]=["{$field}", "=", $param[$field]];
                }
            }
        }

        if (isset($param['reg_time']) && $param['reg_time']!='') {
            $date = explode(' - ',$param['reg_time']);
            $where[]=["reg_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["reg_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        if (isset($param['first_deposit_time']) && $param['first_deposit_time']!='') {
            $date = explode(' - ',$param['first_deposit_time']);
            $where[]=["first_deposit_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["first_deposit_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        if (isset($param['recent_withdraw_money_time']) && $param['recent_withdraw_money_time']!='') {
            $date = explode(' - ',$param['recent_withdraw_money_time']);
            $where[]=["recent_withdraw_money_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["recent_withdraw_money_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        if (isset($param['recent_deposit_money_time']) && $param['recent_deposit_money_time']!='') {
            $date = explode(' - ',$param['recent_deposit_money_time']);
            $where[]=["recent_deposit_money_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["recent_deposit_money_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        if (isset($param['login_time']) && $param['login_time']!='') {
            $date = explode(' - ',$param['login_time']);
            $where[]=["login_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["login_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        if (isset($param['min_give']) && $param['min_give']!='') {
            $uid = UserDistributionModel::field('uid,sum(price) as price')->group('uid')->having('price >= '.$param['min_give'])->select()->column('uid');
            $where[]=['uid','in',$uid];
        }

        if (isset($param['max_give']) && $param['max_give']!='') {
            $uid = UserDistributionModel::field('uid,sum(price) as price')->group('uid')->having('price <= '.$param['max_give'])->select()->column('uid');
            $where[]=['uid','in',$uid];
        }

        if (isset($param['offline_day']) && $param['offline_day']!='') {
            $andtime = strtotime(date('Y-m-d 00:00:00', strtotime("-". $param['offline_day']."day")));
            $endtime = strtotime(date('Y-m-d 23:59:59', strtotime("-". $param['offline_day']."day")));
            $where[]= [['login_time','>=',$andtime],['login_time','<=',$endtime]];
        }

        $timeout = Db::name('distribution_config')->where('id', 1)->value('timeout');
        $uids = [];
        if (isset($param['televised_status']) && $param['televised_status']!='') {
            if(!in_array($param['televised_status'],[4,5])){
                if ($param['televised_status'] == 1) {
                    $list = Db::name('user_reports')->where([['televised_status', '=', 1]])->field('uid,assign_time')->select();
                    foreach ($list as $v) {
                        $timeouts = $v['assign_time'] + ($timeout * 3600);
                        if (time() < $timeouts) {
                            $uids[] = $v['uid'];
                        }
                    }
                    $where[] = ['uid', 'in', $uids];
                }else{
                    $where[]=['televised_status','=',$param['televised_status']];
                }

            }else {
                if ($param['televised_status'] == 4) {
                    $list = Db::name('user_reports')->where([['televised_status', '=', 1]])->field('uid,assign_time')->select();
                    foreach ($list as $v) {
                        $timeouts = $v['assign_time'] + ($timeout * 3600);
                        if (time() > $timeouts) {
                            $uids[] = $v['uid'];
                        }
                    }
                } else if ($param['televised_status'] == 5) {
                        $list = Db::name('user_reports')->where([['televised_status', '=', 2]])->field('uid,televised,login_time')->select();
                        foreach ($list as $v) {
                            if ($v['login_time'] < $v['televised']) {
                                $uids[] = $v['uid'];
                            }
                        }
                }
                $where[] = ['uid', 'in', $uids];
            }
        }else{
            $where[]=['televised_status','=',0];
        }

        if (isset($param['televised']) && $param['televised']!='') {
            $date = explode(' - ',$param['televised']);
            $where[]=["televised",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["televised","<=", Utils::getQueryDateTime($date[1], true)];
        }

        //派发时间
        if (isset($param['distribution_time']) && $param['distribution_time']!='') {
            $date = explode(' - ',$param['distribution_time']);
            $wh[]=["add_time",">=", Utils::getQueryDateTime($date[0])];
            $wh[]=["add_time","<=", Utils::getQueryDateTime($date[1], true)];
            $uid_sql = UserDistributionModel::where($wh)->distinct(true)->field('uid')->buildSql();
            $where[]=['uid','exp',Db::raw('in'.$uid_sql)];
        }

        //电访组别
        if (isset($param['role_id']) && $param['role_id']!='') {
            $where[]=['role_id','=',$param['role_id']];
        }

        //是否接通
        if (isset($param['is_connected']) && $param['is_connected']!='') {
            $where[]=['is_connected','=',$param['is_connected']];
        }

        //短信状态
        if (isset($param['sms_status']) && $param['sms_status']!='') {
            $where[]=['sms_status','=',$param['sms_status']];
        }

        //赠金领取状态
        if (isset($param['give_status']) && $param['give_status']!='') {
            if($param['give_status'] == 0){  //未派发
                $uid_sql = UserDistributionModel::field('uid')->group('uid')->buildSql();
                $where[]=['uid','exp',Db::raw('not in'.$uid_sql)];
            }else if($param['give_status'] == 1) {  //未领取
                $uid_sql = UserDistributionModel::where(['give_status'=>0])->field('uid')->buildSql();
                $where[]=['uid','exp',Db::raw('in'.$uid_sql)];
            }else if($param['give_status'] == 2) {  //已领取
                $uid_sql = UserDistributionModel::where(['give_status'=>1])->field('uid')->buildSql();
                $where[]=['uid','exp',Db::raw('in'.$uid_sql)];
            }else if($param['give_status'] == 3) {  //已失效
                $uid_sql = UserDistributionModel::where(['give_status'=>2])->field('uid')->buildSql();
                $where[]=['uid','exp',Db::raw('in'.$uid_sql)];
            }
        }
        $give_status = [1,2,3];
        if (isset($param['export']) && $param['export'] == 1) {

            return $this->exportList($where,"");
//
//            $total = UserReportsModel::where($where)->count();
//            if (empty($where) || $total > 50000) {
//                $result['code'] = 0;
//                $result['msg'] = '导出数据太大，请增加更多筛选条件';
//                return Respons::run($result);
//            }
//            $data = UserReportsModel::where($where)->order($order)->select();
//            $list = [];
//            $televised_status = ['未安排','待电访','已电访','已电访2次以上','待电访(已超时)','已电访(未回归)'];
//            $is_connected = ['未安排','未接通','已接通'];
//            $sms_status = ['未发送','已发送'];
//            $intention_status = ['未评估','低','中','高'];
//            $phone_status = ['未完成','已完成'];
//            $status = ['禁用','正常'];
//            $is_distribution = ['未处理','否','是'];
//            $give_title = ['未领取','已领取','已失效'];
//            foreach($data as &$value) {
//                $value['reg_time'] = empty($value['reg_time']) ? '' : date("Y-m-d H:i:s", $value['reg_time']);
//                $value['first_deposit_time'] = empty($value['first_deposit_time']) ? '' : date("Y-m-d H:i:s", $value['first_deposit_time']);
//                $value['recent_withdraw_money_time'] = empty($value['recent_withdraw_money_time']) ? '' : date("Y-m-d H:i:s", $value['recent_withdraw_money_time']);
//                $value['recent_deposit_money_time'] = empty($value['recent_deposit_money_time']) ? '' : date("Y-m-d H:i:s", $value['recent_deposit_money_time']);
//                $value['login_time'] = empty($value['login_time']) ? '' : date("Y-m-d H:i:s", $value['login_time']);
//                $value['televised'] = empty($value['televised']) ? '' : date("Y-m-d H:i:s", $value['televised']);
//                $value['agent_status'] = UserAgentModel::where(['uid'=>$value['uid']])->value('id') ? '已申请':'未申请';
//                $value['distribution_price'] = UserDistributionModel::where(['uid'=>$value['uid']])->sum('price');
//                $value['sms_time'] = empty($value['sms_time']) ? '' : date("Y-m-d H:i:s", $value['sms_time']);
//                $distribution = UserDistributionModel::where(['uid'=>$value['uid']])->order('id desc')->field('add_time,give_time,give_status')->find();
//                $value['distribution_time'] = empty($distribution['add_time']) ? '' : date("Y-m-d H:i:s", $distribution['add_time']);
//                $value['give_time'] = empty($distribution['give_time']) ? '' : date("Y-m-d H:i:s", $distribution['give_time']);
//                $value['give_status'] = empty($distribution) ? '未派发': $give_title[$distribution['give_status']];
//                $value['is_connected'] = $is_connected[$value['is_connected']];
//                $value['sms_status'] = $sms_status[$value['sms_status']];
//                $value['intention_status'] = $intention_status[$value['intention_status']];
//                $value['phone_status'] = $phone_status[$value['phone_status']];
//                $value['status'] = $status[$value['status']];
//                $value['is_distribution'] = $is_distribution[$value['is_distribution']];
//                $timeouts = $value['assign_time'] + ($timeout * 3600);
//                if ($value['televised_status'] == 1 && time() > $timeouts) {
//                    $value['televised_status'] = 4;
//                } else if ($value['televised_status'] == 2 && $value['login_time'] < $value['televised']) {
//                    $value['televised_status'] = 5;
//                }
//                $value['televised_status'] = $televised_status[$value['televised_status']];
//                $value['assign_time'] = empty($value['assign_time']) ? '' : date("Y-m-d H:i:s", $value['assign_time']);
//                $list[] = $value;
//            }

        } else {
            $list = UserReportsModel::where($where)
                ->order($order)
                ->limit($offset, $limit)
                ->select();
            $rsps['total'] = UserReportsModel::where($where)->count();
            $data = [];
            foreach($list as $k => $value) {
                $value['reg_time'] = empty($value['reg_time']) ? '' : date("Y-m-d H:i:s", $value['reg_time']);
                $value['first_deposit_time'] = empty($value['first_deposit_time']) ? '' : date("Y-m-d H:i:s", $value['first_deposit_time']);
                $value['recent_withdraw_money_time'] = empty($value['recent_withdraw_money_time']) ? '' : date("Y-m-d H:i:s", $value['recent_withdraw_money_time']);
                $value['recent_deposit_money_time'] = empty($value['recent_deposit_money_time']) ? '' : date("Y-m-d H:i:s", $value['recent_deposit_money_time']);
                $value['offline_day'] = empty($value['login_time']) ? 0:intval((time() - $value['login_time'])/86400);
                $value['login_time'] = empty($value['login_time']) ? '' : date("Y-m-d H:i:s", $value['login_time']);
                $value['agent_status'] = UserAgentModel::where(['uid'=>$value['uid']])->value('id') ? 1:0;
                $value['distribution_price'] = UserDistributionModel::where(['uid'=>$value['uid']])->sum('price');
                $value['sms_time'] = empty($value['sms_time']) ? '' : date("Y-m-d H:i:s", $value['sms_time']);
                $distribution = UserDistributionModel::where(['uid'=>$value['uid']])->order('id desc')->field('add_time,give_time,give_status')->find();
                $value['distribution_time'] = empty($distribution['add_time']) ? '' : date("Y-m-d H:i:s", $distribution['add_time']);
                $value['give_time'] = empty($distribution['give_time']) ? '' : date("Y-m-d H:i:s", $distribution['give_time']);
                $value['give_status'] = empty($distribution) ? 0 : $give_status[$distribution['give_status']];

                $timeouts = $value['assign_time'] + ($timeout * 3600);
                if ($value['televised_status'] == 1 && time() > $timeouts) {
                    $value['status_show'] = 1;
                } else if ($value['televised_status'] == 2 && $value['login_time'] < $value['televised']) {
                    $value['status_show'] = 2;
                }
                $value['televised'] = empty($value['televised']) ? '' : date("Y-m-d H:i:s", $value['televised']);
                $value['assign_time'] = empty($value['assign_time']) ? '' : date("Y-m-d H:i:s", $value['assign_time']);
                $data[] = $value;
            }
            $rsps['data'] = $data;
            $rsps['admin'] = Utils::getOptions('admin', ['id', 'username'],[['status','=',1],['id','not in','1,2']]);
            $rsps['admin_role'] = Utils::getOptions('admin_role', ['id', 'role_name'],[['id','<>',1]]);
            $rsps['distribution_config'] = DistributionConfigModel::where('id',1)->find();
        }


//        if (isset($param['export']) && $param['export'] == 1) {
//            $header = [
//                'uid' => lang('UID'),
//                'phone' => lang('手机号'),
//                'mail' => lang('邮箱'),
//                'lebal' => lang('用户标签'),
//                'reg_time' => lang('注册时间'),
//                'first_deposit_time' => lang('首存时间'),
//                'recent_withdraw_money_time' => lang('最近出金时间'),
//                'recent_deposit_money_time' => lang('最近入金时间'),
//                'login_time' => lang('最近登陆时间'),
//                'deposit_nums' => lang('入金次数'),
//                'deposit_total' => lang('入金总额'),
//                'withdrawal_nums' => lang('出金次数'),
//                'withdrawal_total' => lang('出金总额'),
//                'total_gift' => lang('赠送金额'),
//                'action_discount' => lang('活动优惠'),
//                'lottery_total' => lang('抽奖'),
//                'rebate_total' => lang('返佣'),
//                'rebate1_total' => lang('返水'),
//                'promotion_bonus' => lang('推广奖金'),
//                'mission_rewards' => lang('任务奖励'),
//                'vip_bonus' => lang('VIP周月礼金'),
//                'level' => lang('会员等级'),
//                'rec_id' => lang('上级代理UID'),
//                'reg_ip' => lang('注册ip'),
//                'status' => lang('用户状态'),
//                'balance' => lang('余额'),
//                'televised' => lang('电访时间'),
//                'televised_status' => lang('电访进度'),
//                'sms_status' => lang('短信状态'),
//                'intention_status' => lang('客户意向状态'),
//                'remark' => lang('备注'),
//                'admin_group' => lang('电访组别'),
//                'admin_name' => lang('电访人员'),
//                'sms_time' => lang('短信发送时间'),
//                'feedback' => lang('反馈内容'),
//                'is_connected' => lang('电访状态'),
//                'assign_time' => lang('电访人员派发时间'),
//                'is_distribution' => lang('是否要领取赠金'),
//            ];
//
//            $file_name = genCsv($header, $list,lang('会员报表记录').date('YmdHis',time()), false);
//            $s3_url = Uploads::uploadS3(public_path() . $file_name);
//            if (is_null($where)) {
//                Utils::addOptLogByHandle('会员报表导出');
//            }
//            $result['data'] = $s3_url ?: toMedia($file_name, '/');
//            $result['code']=1;
//            return Respons::run($result);
//        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function treat_list(){
        $param=Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $where = [];
        $where[] = ['admin_id','>',0];
        $role_id = AdminModel::where(['id'=>$this->uid])->value('role_id');
        if($role_id != 1){
            $where[] = ['admin_id','=',$this->uid];
        }

        if (isset($param['order']) && $param['order'] != '') {
            $order = $param['field'].' '.$param['order'];
        } else {
            $order = 'assign_time desc,televised_status asc';
        }

        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $where[]=["uid|phone", "=", $param['username']];
            } else {
                $where[]=["username|mail|phone", "=", $param['username']];
            }
        }

        //电访组别
        if (isset($param['role_id']) && $param['role_id']!='') {
            $where[]=['role_id','=',$param['role_id']];
        }

        //电访派发时间
        if (isset($param['add_time']) && $param['add_time']!='') {
            $date = explode(' - ',$param['add_time']);
            $where[]=["assign_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["assign_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        //电访进度
        $uids = [];
        $timeout = Db::name('distribution_config')->where('id', 1)->value('timeout');
        if (isset($param['televised_status']) && $param['televised_status']!='') {
            if(!in_array($param['televised_status'],[4,5])){
                if ($param['televised_status'] == 1) {
                    $list = Db::name('user_reports')->where([['televised_status', '=', 1]])->field('uid,assign_time')->select();
                    foreach ($list as $v) {
                        $timeouts = $v['assign_time'] + ($timeout * 3600);
                        if (time() < $timeouts) {
                            $uids[] = $v['uid'];
                        }
                    }
                    $where[] = ['uid', 'in', $uids];
                }else{
                    $where[]=['televised_status','=',$param['televised_status']];
                }
            }else {
                if ($param['televised_status'] == 4) {
                    $list = Db::name('user_reports')->where([['televised_status', '=', 1]])->field('uid,assign_time')->select();
                    foreach ($list as $v) {
                        $timeouts = $v['assign_time'] + ($timeout * 3600);
                        if (time() > $timeouts) {
                            $uids[] = $v['uid'];
                        }
                    }
                } else if ($param['televised_status'] == 5) {
                    $list = Db::name('user_reports')->where([['televised_status', '=', 2]])->field('uid,televised')->select();
                    foreach ($list as $v) {
                        $login_time = Db::name('user_reports')->where([['uid', '=', $v['uid']]])->value('login_time');
                        if ($login_time < $v['televised']) {
                            $uids[] = $v['uid'];
                        }
                    }
                }
                $where[] = ['uid', 'in', $uids];
            }
        }

        //是否接通
        if (isset($param['is_connected']) && $param['is_connected']!='') {
            $where[]=['is_connected','=',$param['is_connected']];
        }

        //短信状态
        if (isset($param['sms_status']) && $param['sms_status']!='') {
            $where[]=['sms_status','=',$param['sms_status']];
        }

        //赠金领取状态
        if (isset($param['give_status']) && $param['give_status']!='') {
            if($param['give_status'] == 0){  //未派发
                $uid_sql = UserDistributionModel::field('uid')->group('uid')->buildSql();
                $where[]=['uid','exp',Db::raw('not in'.$uid_sql)];
            }else if($param['give_status'] == 1) {  //未领取
                $uid_sql = UserDistributionModel::where(['give_status'=>0])->group('uid')->field('uid')->buildSql();
                $where[]=['uid','exp',Db::raw('in'.$uid_sql)];
            }else if($param['give_status'] == 2) {  //已领取
                $uid_sql = UserDistributionModel::where(['give_status'=>1])->group('uid')->field('uid')->buildSql();
                $where[]=['uid','exp',Db::raw('in'.$uid_sql)];
            }else if($param['give_status'] == 3) {  //已失效
                $uid_sql = UserDistributionModel::where(['give_status'=>2])->group('uid')->field('uid')->buildSql();
                $where[]=['uid','exp',Db::raw('in'.$uid_sql)];
            }
        }

        if (isset($param['televised']) && $param['televised']!='') {
            $date = explode(' - ',$param['televised']);
            $where[]=["televised",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["televised","<=", Utils::getQueryDateTime($date[1], true)];
        }

        $list = Db::name('user_reports')->where($where)->order($order)->limit($offset, $limit)->select();
        $rsps['total'] = Db::name('user_reports')->where($where)->count();
        $data = [];
        $give_status = [1,2,3];
        foreach($list as $value) {
            $timeouts = $value['assign_time'] + ($timeout * 3600);
            if ($value['televised_status'] == 1 && time() > $timeouts) {
                $value['status_show'] = 1;
            } else if ($value['televised_status'] == 2 && $value['login_time'] < $value['televised']) {
                $value['status_show'] = 2;
            }
            $dis = UserDistributionModel::where(['uid'=>$value['uid']])->order('add_time desc')->field('give_status,add_time,give_time')->find();
            $value['give_status'] = empty($dis) ? 0 : $give_status[$dis['give_status']];
            $value['distribution_time'] = empty($dis['add_time']) ? '' : date("Y-m-d H:i:s", $dis['add_time']);
            $value['give_time'] = empty($dis['give_time']) ? '' : date("Y-m-d H:i:s", $dis['give_time']);
            $value['login_time'] = empty($value['login_time']) ? '' : date("Y-m-d H:i:s", $value['login_time']);
            $value['reg_time'] = empty($value['reg_time']) ? '' : date("Y-m-d H:i:s", $value['reg_time']);
            $value['add_time'] = empty($value['assign_time']) ? '' : date("Y-m-d H:i:s", $value['assign_time']);
            $value['televised'] = empty($value['televised']) ? '' : date("Y-m-d H:i:s", $value['televised']);
            $value['sms_time'] = empty($value['sms_time']) ? '' : date("Y-m-d H:i:s", $value['sms_time']);
            $value['agent_status'] = UserAgentModel::where(['uid'=>$value['uid']])->value('id') ? 1:0;
            $data[] = $value;
        }
        $rsps['data'] = $data;
        $rsps['admin_role'] = Utils::getOptions('admin_role', ['id', 'role_name'],[['id','<>',1]]);
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function distribute_list()
    {
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $where = [];

        if (isset($param['order']) && $param['order'] != '') {
            $order = 'a.'.$param['field'].' '.$param['order'];
        } else {
            $order = 'a.id desc';
        }

        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $where[]=["b.uid|b.phone", "=", $param['username']];
            } else {
                $where[]=["b.username|b.mail|b.phone", "=", $param['username']];
            }
        }

        //电访组别
        if (isset($param['role_id']) && $param['role_id']!='') {
            $where[]=['b.role_id','=',$param['role_id']];
        }

        //电访派发时间
        if (isset($param['assign_time']) && $param['assign_time']!='') {
            $date = explode(' - ',$param['assign_time']);
            $where[]=["b.assign_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["b.assign_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        //电访时间
        if (isset($param['televised']) && $param['televised']!='') {
            $date = explode(' - ',$param['televised']);
            $where[]=["b.televised",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["b.televised","<=", Utils::getQueryDateTime($date[1], true)];
        }

        //赠金派发时间
        if (isset($param['add_time']) && $param['add_time']!='') {
            $date = explode(' - ',$param['add_time']);
            $where[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
        }

        //赠金领取状态
        if (isset($param['give_status']) && $param['give_status']!='') {
            $where[]=['a.give_status','=',$param['give_status']];
        }

        //审核状态
        if (isset($param['status']) && $param['status']!='') {
            $where[]=['a.status','=',$param['status']];
        }

        $timeout = Db::name('distribution_config')->where('id', 1)->value('timeout');

        $field = 'b.level,b.username,b.phone,b.mail,b.rec_id,b.televised_status,b.is_connected,b.intention_status,b.status,b.is_distribution,b.reg_time,b.first_deposit_time,b.login_time,b.recent_withdraw_money_time,b.recent_deposit_money_time,b.televised,b.sms_time,b.admin_group,b.admin_name,b.feedback,b.remark';
        $list = Db::name('user_distribution')->alias('a')
            ->join('user_reports b','a.uid=b.uid')
            ->where($where)
            ->order($order)
            ->field('a.*,a.status as status2,'.$field)
            ->limit($offset, $limit)
            ->select();
        $rsps['total'] = Db::name('user_distribution')->alias('a')->join('user_reports b','a.uid=b.uid')->where($where)->count();
        $data = [];
        foreach($list as $value) {
            $timeouts = $value['add_time'] + ($timeout * 3600);
            if ($value['televised_status'] == 1 && time() > $timeouts) {
                $value['status_show'] = 1;
            } else if ($value['televised_status'] == 2 && $value['login_time'] < $value['televised']) {
                $value['status_show'] = 2;
            }
            $value['reg_time'] = empty($value['reg_time']) ? '' : date("Y-m-d H:i:s", $value['reg_time']);
            $value['first_deposit_time'] = empty($value['first_deposit_time']) ? '' : date("Y-m-d H:i:s", $value['first_deposit_time']);
            $value['recent_withdraw_money_time'] = empty($value['recent_withdraw_money_time']) ? '' : date("Y-m-d H:i:s", $value['recent_withdraw_money_time']);
            $value['recent_deposit_money_time'] = empty($value['recent_deposit_money_time']) ? '' : date("Y-m-d H:i:s", $value['recent_deposit_money_time']);
            $value['login_time'] = empty($value['login_time']) ? '' : date("Y-m-d H:i:s", $value['login_time']);
            $value['televised'] = empty($value['televised']) ? '' : date("Y-m-d H:i:s", $value['televised']);
            $value['sms_time'] = empty($value['sms_time']) ? '' : date("Y-m-d H:i:s", $value['sms_time']);
            $value['add_time'] = empty($value['add_time']) ? '' : date("Y-m-d H:i:s", $value['add_time']);
            $value['give_time'] = empty($value['give_time']) ? '' : date("Y-m-d H:i:s", $value['give_time']);
            $value['admin'] = AdminModel::where(['id'=>$value['admin_id']])->value('username');
            $value['agent_status'] = UserAgentModel::where(['uid'=>$value['uid']])->value('id') ? 1:0;
            $data[] = $value;
        }
        $rsps['data'] = $data;
        $rsps['admin_role'] = Utils::getOptions('admin_role', ['id', 'role_name'],[['id','<>',1]]);
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function reports(){
        $param = Request::param();
        $where = $where2 = $where3= [];

        if (isset($param['username']) && $param['username']!='') {
            if (is_numeric($param['username'])) {
                $where[]=["uid|phone", "=", $param['username']];
            } else {
                $where[]=["username|mail|phone", "=", $param['username']];
            }
            $dis_sql = Db::name('user_reports')->where($where)->field('uid')->buildSql();
            $where2[] = ['uid','exp',Db::raw('in'.$dis_sql)];
            $where3[] = ['a.uid','exp',Db::raw('in'.$dis_sql)];
        }

        $start_time = time();
        $end_time = time() - 7*86400;
        if (!empty($param['date'])) {
            $date_arr = explode(' - ', $param['date']);
            $end_time = Utils::getQueryDateTime($date_arr[0]);
            $start_time = Utils::getQueryDateTime($date_arr[1], true);
        }

        $arr = [];
        for($i = $start_time; $i >= $end_time; $i-=86400){
            $andtime =  strtotime(date("Y-m-d 00:00:00", $i));
            $endtime = strtotime(date("Y-m-d 23:59:59", $i));
            $wheres = [['televised','>=', $andtime],['televised','<=', $endtime]];
            $wheres2 = [['add_time','>=', $andtime],['add_time','<=', $endtime]];
            $wheres3 = [['a.add_time','>=', $andtime],['a.add_time','<=', $endtime]];
            $arr[] = [
                'day'=>date("Y-m-d", $i),
                'televised_count'=> Db::name('user_reports')->where($where)->where($wheres)->where(['is_connected'=>2])->count(), //电访接通人数
                'give_count'=> Db::name('user_distribution')->where($where2)->where($wheres2)->count(), //赠金发放人数
                'receive_count'=> Db::name('user_distribution')->where($where2)->where($wheres2)->where(['give_status'=>1])->count(), //赠金领取人数
                'give_price'=> Db::name('user_distribution')->where($where2)->where($wheres2)->where(['give_status'=>1])->sum('price'), //赠金领取总额
                'cash_count'=>  Db::name('user_distribution')->alias('a')->join('user_offline b','a.uid=b.uid and b.update_time >= a.add_time')
                    ->where($where3)->where($wheres3)->where([['a.give_status','=',1],['b.status','in','3,4']])
                    ->group('b.uid')
                    ->count(), //领取赠金后入金总人數
                'out_count'=> Db::name('user_distribution')->alias('a')->join('user_withdrawal b','a.uid=b.uid and b.update_time >= a.add_time')
                    ->where($where3)->where($wheres3)->where([['a.give_status','=',1],['b.status','in','3']])
                    ->group('b.uid')
                    ->count(), //领取赠金后出金总人數
            ];
        }
        $rsps['data'] = $arr;
        $rsps['code']=1;
        return Respons::run($rsps);
    }


    public function follow_list(){
        $param = Request::param();
        $uid = $param['id'] ?? 0;
        $where = [];
        $where[] = ['uid','=',$uid];
        $list = UserFollowModel::where($where)->order('id desc')->select();
        $arr = [];
        foreach ($list as $v){
            $v['admin_name'] = AdminModel::where(['id'=>$v['admin_id']])->value('username');
            $v['salesman_name'] = AdminModel::where(['id'=>$v['salesman_id']])->value('username');
            $v['role_name'] = AdminRoleModel::where(['id'=>$v['role_id']])->value('role_name');
            $v['add_time'] = empty($v['add_time']) ? '' : date("Y-m-d H:i:s", $v['add_time']);
            $v['televised'] = empty($v['televised']) ? '' : date("Y-m-d H:i:s", $v['televised']);
            $v['sms_time'] = empty($v['sms_time']) ? '' : date("Y-m-d H:i:s", $v['sms_time']);
            $arr[] = $v;
        }

        $rsps['data'] = $arr;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    /**
     * 导出数据
     * @param null $where
     * @param null $whereStr
     * @return \think\response\Json
     */
    public function exportList($where = null, $whereStr = null)
    {
        $params = Request::param();
        $params['where'] = $where;
        $params['whereStr'] = $whereStr;
        $params['positionField'] = 'a.assign_time';
        $params['positionOrder'] = 'desc';
        $params['muid']=$this->uid;

        $isConnectedArr = [0=>'未安排',1=>'未接通',2=>'已接通'];
        $smsStatusArr = [0=>'未发送',1=>'已发送'];
        $intentionStatusArr = [0=>'未评估',1=>'低',2=>'中',3=>'高'];
        $statusArr = [0 => '禁用', 1 => '正常'];
        $isDistributionArr = [0=>'未处理',1=>'否',2=>'是'];
        $rlt = ReportExport::export(new rptParams('用户充值-优惠报表',
            [
                new rptCol('uid', 'UID'),
                'phone:手机号',
                'mail:邮箱',
                'lebal:用户标签',
                new rptCol('reg_time','注册时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                new rptCol('first_deposit_time','首存时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                new rptCol('recent_withdraw_money_time','最近出金时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                new rptCol('recent_deposit_money_time','最近入金时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                new rptCol('login_time','最近入金时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                'deposit_nums:入金次数',
                'deposit_total:入金总额',
                'withdrawal_nums:出金次数',
                'withdrawal_total:出金总额',
                'total_gift:赠送金额',
                'action_discount:活动优惠',
                'lottery_total:抽奖',
                'rebate_total:返佣',
                'rebate1_total:返水',
                'promotion_bonus:推广奖金',
                'mission_rewards:任务奖励',
                'vip_bonus:VIP周月礼金',
                'level:会员等级',
                'rec_id:上级代理UID',
                'reg_ip:注册ip',
                new rptCol('status','用户状态',rptCol::transType_kvtrans,$statusArr),
                'balance:余额',
                new rptCol('televised','电访时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                new rptCol('televised_status','电访进度','app\common\service\ReportDownloadService::televisedStatus'),
                new rptCol('sms_status','短信状态',rptCol::transType_kvtrans,$smsStatusArr),
                new rptCol('intention_status','客户意向状态',rptCol::transType_kvtrans,$intentionStatusArr),
                'remark:备注',
                'admin_group:电访组别',
                'admin_name:电访人员',
                new rptCol('sms_time','短信发送时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                'feedback:反馈内容',
                new rptCol('is_connected','电访状态',rptCol::transType_kvtrans,$isConnectedArr),
                new rptCol('assign_time','电访人员派发时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                new rptCol('is_distribution','是否要领取赠金',rptCol::transType_kvtrans,$isDistributionArr),
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['rec_id', 'uid'],//导出表名称使用的字段信息
            'a.id', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        return UserReportsModel::alias('a')->where($param['where'])->where($param['whereStr'])
            ->field('*');
    }

}