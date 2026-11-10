<?php

namespace app\api\factory_v2;

use app\common\exception\MessageException;
use app\common\lib\Util;
use app\common\model\DefaultHeadModel;
use app\common\model\UserAgentModel;
use app\common\model\UserExtraModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\service\BetService;
use app\common\service\MemberService;
use app\common\service\ReportService;
use think\facade\Db;
use think\facade\Request;

class AgentFactory{

    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->uid = $uid;
    }

    //我的推荐
    public function recommend_run(){
        $params = $this->param;
        $startDate = $params['start_date']?? '';
        $endDate = $params['end_date']?? '';
        $startTime = strtotime($startDate);
        $endTime = strtotime($endDate.' 23:59:59');
        $conditionBetweenDate = [$startDate, $endDate];
        $conditionBetweenDate1 = [date('Ymd', $startTime), date('Ymd', $endTime)];
        $conditionBetweenTime = [$startTime, $endTime];


        $userDetail = UserModel::where('id', '=', $this->uid)->find();
        $returnData = [];
        #1、我的邀请码
        $returnData['invite_code'] = $userDetail['rec_code'];

        #2、我的推荐人邀请码
        $parentCode = '';
        if( $userDetail['rec_id'] ){
             $parentCode = UserModel::where('id', '=', $userDetail['rec_id'])->value('rec_code');
        }
        $returnData['parent_code'] = $parentCode;

        #3、佣金余额
        $returnData['total_commission'] = Db::name('third_stats_fyfy')
            ->whereBetween('sff_w', $conditionBetweenDate1)
            ->where('sff_uid', '=', $this->uid)->sum('sff_fy');

        #4、邀请链接
        $returnData['invite_url'] = MemberService::createShareUrl($userDetail['rec_code']);

        #5、我的佣金
        $_arr = [];
        #一、佣金总额
        $_arr['total_commission'] = $returnData['total_commission'];
        #二、已领取佣金
        $_condition = [];
        $_condition[] = ['sff_uid', '=', $this->uid];
        $_condition[] = ['sff_payed', '=', 2];
        $_arr['received_commission'] = Db::name('third_stats_fyfy')
            ->whereBetween('sff_w', $conditionBetweenDate1)
            ->where($_condition)->sum('sff_fy');
        #三、最后一次佣金（昨天佣金）
        $_condition = [];
        $_condition[] = ['sff_uid', '=', $this->uid];
        $_condition[] = ['sff_payed', '=', 2];
        $_condition[] = ['sff_w', '=', date('Ymd', strtotime('-1 day'))];
        $lastCommission = Db::name('third_stats_fyfy')
            ->whereBetween('sff_w', $conditionBetweenDate1)
            ->where($_condition)->sum('sff_fy');
        if( $lastCommission ){
            $_arr['last_commission'] = $lastCommission['sff_fy'];
        }else{
            $_arr['last_commission'] = 0;
        }
        $returnData['commission'] = $_arr;

        #6、我的收入
        $_arr = [];
        #一、直属下级⽤户数
        $_arr['child_user_num'] = UserModel::where('rec_id', '=', $this->uid)->count();
        #二、团队投注总额
        $table = BetService::getThirdStatsTable();
        $_arr['total_bet'] = Db::name($table['_stats_global'])->where('tree', 'like', $userDetail['tree'].'%')
            ->whereBetween('su_dt', $conditionBetweenDate1)->sum('su_valid_bet');
        #三、直属⽤户投注总额
        $_arr['child_bet'] = Db::name($table['_stats_global'])->where('rec_id', '=', $userDetail['id'])
            ->whereBetween('su_dt', $conditionBetweenDate1)->sum('su_valid_bet');
        #四、其他⽤户的投注总额（团队投注总额 - 直属⽤户投注总额）
        $_arr['other_bet'] = $_arr['total_bet'] - $_arr['child_bet'];
        $returnData['income'] = $_arr;

        #7、直属统计
        $_arr = [];
        #一、直属⽤户投注总额
        $_arr['child_bet'] = $returnData['income']['child_bet'];
        #二、投注笔数
        $_arr['child_bet_num'] = Db::name($table['_stats_global'])
            ->whereBetween('su_dt', $conditionBetweenDate1)
            ->where('rec_id', '=', $userDetail['id'])->sum('su_betnum');
        $returnData['child_statistics'] = $_arr;

        $rsps['data'] = $returnData;
        $rsps['code'] = 1;
        return $rsps;
    }

    //我的佣金
    public function commission_run(){
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit']?? 10;
        $start_time = $this->param['start_time']?? '';
        $end_time = $this->param['end_time']?? '';

        $condition = [];
        $condition[] = ['sff_uid', '=', $this->uid];
        $condition[] = ['sff_payed', '=', 2];
        if( $start_time ){
            $condition[] = ['sff_statstime', '>=', strtotime($start_time)];
        }
        if( $end_time ){
            $condition[] = ['sff_statstime', '<=', strtotime($end_time.' 23:59:59')];
        }
        $field = 'sff_id as id,sff_statstime as time,sff_bet as valid_bet,sff_fy as commission,sff_utype';
        $data = Db::name('third_stats_fyfy')
            ->where($condition)
            ->field($field)
            ->page($page, $limit)
            ->order('sff_paytime', 'desc')
            ->select()->toArray();
        foreach ($data as &$v){
            //获取分佣用户数
            if($v['sff_utype'] == 1){ // 外部代理
                $sql = userLevelSql($this->uid);
            } else {
                $sql = unLevelSql($this->uid, 1);
            }
            unset($v['sff_utype']);
            $_whereStr = "sffl_uid in (".$sql.")";
            $v['user_num'] = Db::name('third_stats_fyfylog')
                ->where('sffl_dt', '=', date('Ymd', $v['time']))
                ->where($_whereStr)->group('sffl_uid')->count();

            $v['valid_bet'] = round($v['valid_bet'], 2);
            $v['commission'] = round($v['commission'], 2);
            $v['time'] = date('Y-m-d H:i:s', $v['time']);
        }

        $rsps['data'] = $data;
        $rsps['commission'] = Db::name('third_stats_fyfy')->where('sff_uid', '=', $this->uid)->sum('sff_fy');
        $rsps['code'] = 1;
        return $rsps;
    }

    //佣金详情
    public function commissionDetail_run(){
        $id = $this->param['id'] ?? 0;
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit']?? 10;
        $data = Db::name('third_stats_fyfy')
            ->where('sff_id', '=', $id)
            ->find();
        $list = [];
        if( $data ){

            if($data['sff_utype'] == 1){ // 外部代理
                $sql = userLevelSql($data['sff_uid']);
            } else {
                $sql = unLevelSql($data['sff_uid'], 1);
            }
            $field = 'b.id,b.username,b.mail,b.phone,b.rec_code,a.sffl_dt,a.sffl_bet as valid_bet,a.sffl_back as commission';
            $list = Db::name('third_stats_fyfylog')->alias('a')
                ->join('user b', 'a.sffl_uid=b.id')
                ->field($field)
                ->where('a.sffl_back', '>', 0)
                ->where('a.sffl_dt', '=', $data['sff_w'])
                ->where("a.sffl_uid in (".$sql.")")
                ->page($page, $limit)
                ->order('a.sffl_dt', 'desc')
                ->select()->toArray();
            foreach ($list as &$v){
                $v['valid_bet'] = round($v['valid_bet'], 2);
                $v['commission'] = round($v['commission'], 2);
                $v['sffl_dt'] = date('Y-m-d H:i:s', strtotime($v['sffl_dt']));
            }
        }
        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }

    //我的收入
    public function income_run(){
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit']?? 10;
        $start_time = $this->param['start_time']?? '';
        $end_time = $this->param['end_time']?? '';
        $username = $this->param['username']?? '';

        $agentDetail = UserAgentModel::where('uid', '=', $this->uid)->find();
        if( !$agentDetail ){
            ajax_return('代理不存在');
        }
        //获取分佣用户数
        if($agentDetail['sff_utype'] == 0){ // 外部代理
            $sql = userLevelSql($this->uid);
        } else {
            $sql = unLevelSql($this->uid, 1);
        }
        $condition = [];
        if( $start_time ){
            $condition[] = ['b.add_time', '>=', strtotime($start_time)];
        }
        if( $end_time ){
            $condition[] = ['b.add_time', '<=', strtotime($end_time.' 23:59:59')];
        }
        $condition = $this->getUserNameCondition($username, $condition, 'b');
        $field = 'b.id,b.username,b.mail,b.phone,b.rec_code,b.add_time,sum(a.sffl_bet) as valid_bet,sum(a.sffl_back) as commission';
        $list = Db::name('third_stats_fyfylog')->alias('a')
            ->join('user b', 'a.sffl_uid=b.id')
            ->field($field)
            ->where($condition)
            ->where("a.sffl_uid in (".$sql.")")
            ->group('a.sffl_uid')
            ->page($page, $limit)
            ->order('add_time desc')
            ->select()->toArray();
        foreach ($list as &$v) {
            //查询下级用户
            $v['child_user_num'] = UserModel::where('rec_id', '=', $v['id'])->count();
            //添加时间
            $v['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
            $v['valid_bet'] = round($v['valid_bet'], 2);
            $v['commission'] = round($v['commission'], 2);
        }
        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;

    }

    //所有数据
    public function statistics_run(){
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit']?? 10;
        $start_time = $this->param['start_time']?? '';
        $end_time = $this->param['end_time']?? '';
        $username = $this->param['username']?? '';

        $condition = [];
        if( $start_time ){
            $condition[] = ['add_time', '>=', strtotime($start_time)];
        }
        if( $end_time ){
            $condition[] = ['add_time', '<=', strtotime($end_time.' 23:59:59')];
        }
        $condition = $this->getUserNameCondition($username, $condition);
        $userDetail = UserModel::where('id', '=', $this->uid)->find();
        $sql = UserModel::where('tree', 'like', $userDetail['tree'].'%')->field('id')->buildSql();
        $childSql = UserModel::where('rec_id', '=', $this->uid)->field('id')->buildSql();
        $whereSql = 'id in ('.$sql.')';
        $field = 'id,username,phone,mail,rec_code,add_time';
        $list = UserModel::where($whereSql)
            ->where($condition)
            ->field($field)
            ->page($page, $limit)
            ->order('add_time desc')
            ->select()->toArray();
        $table = BetService::getThirdStatsTable();
        foreach ($list as &$v) {
            $v['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
            //总充值
            $v['total_recharge'] = MemberService::getRechargeByUid($v['id']);
            //总流水
            $v['total_bet'] = Db::name($table['_stats_global'])->where('su_uid', '=', $v['id'])->sum('su_valid_bet');
        }
        //数据统计
        //团队充值总额
        $teamRecharge = UserOfflineModel::where('uid in ('.$sql.')')
            ->where('status', 'in', '3,4')
            ->sum('arrival_price');
        //团队首充总人数
        $teamFirstRechargeUser = UserOfflineModel::where('uid in ('.$sql.')')
            ->where('status', 'in', '3,4')
            ->group('uid')
            ->count();
        $statistics = [];
        #1、直属充值总额
        $statistics['child_recharge'] = UserOfflineModel::where('uid in ('.$childSql.')')
            ->where('status', 'in', '3,4')
            ->sum('arrival_price');

        #2、直属⾸充⼈数
        $statistics['child_user_num'] = UserOfflineModel::where('uid in ('.$childSql.')')
            ->where('status', 'in', '3,4')
            ->where('first', '=', 1)
            ->group('uid')
            ->count();
        #3、其他累积总充值
        $statistics['other_recharge'] = $teamRecharge - $statistics['child_recharge'];
        #4、其他⾸充⼈数
        $statistics['other_first_user_num'] = $teamFirstRechargeUser - $statistics['child_user_num'];
        #5、总充值累积总额 包含直属与其他的累积总充值
        $statistics['total_recharge'] = $teamRecharge;
        #6、总⾸充⼈数
        $statistics['total_first_user_num'] = $teamFirstRechargeUser;
        $rsps['data'] = $list;
        $rsps['statistics'] = $statistics;
        $rsps['code'] = 1;
        return $rsps;
    }

    //直接投注（下级投注）
    public function childBet_run(){
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit']?? 10;
        $start_time = $this->param['start_time']?? '';
        $end_time = $this->param['end_time']?? '';
        $username = $this->param['username']?? '';

        $condition = [];
        if( $start_time ){
            $condition[] = ['add_time', '>=', strtotime($start_time)];
        }
        if( $end_time ){
            $condition[] = ['add_time', '<=', strtotime($end_time.' 23:59:59')];
        }
        $condition = $this->getUserNameCondition($username, $condition);
        $condition[] = ['rec_id', '=', $this->uid];
        $field = 'id,username,phone,mail,rec_code,add_time';
        $list = UserModel::where($condition)
            ->field($field)
            ->page($page, $limit)
            ->order('add_time desc')
            ->select()->toArray();
        $table = BetService::getThirdStatsTable();
        foreach ($list as &$v) {
            $v['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
            //投注笔数
            $v['bet_num'] = Db::name($table['_stats_global'])->where('su_uid', '=', $v['id'])->sum('su_betnum');
        }

        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }

    //直接会员（下级会员）
    public function childMember_run(){
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit']?? 10;
        $start_time = $this->param['start_time']?? '';
        $end_time = $this->param['end_time']?? '';
        $username = $this->param['username']?? '';

        $condition = [];
        if( $start_time ){
            $condition[] = ['add_time', '>=', strtotime($start_time)];
        }
        if( $end_time ){
            $condition[] = ['add_time', '<=', strtotime($end_time.' 23:59:59')];
        }
        $condition = $this->getUserNameCondition($username, $condition);
        $condition[] = ['rec_id', '=', $this->uid];
        $field = 'id,username,phone,mail,rec_code,add_time,status';
        $list = UserModel::where($condition)
            ->field($field)
            ->page($page, $limit)
            ->order('add_time desc')
            ->select()->toArray();
        foreach ($list as &$v) {
            $v['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
            //是否充值
            $v['is_recharge'] = MemberService::getRechargeByUid($v['id']) > 0 ? 1 : 0;
        }

        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }

    //直属奖金
    public function childBonus_run(){
        $this->uid = 60;
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit']?? 10;
        $start_time = $this->param['start_time']?? '';
        $end_time = $this->param['end_time']?? '';
        $username = $this->param['username']?? '';

        $condition = [];
        if( $start_time ){
            $condition[] = ['add_time', '>=', strtotime($start_time)];
        }
        if( $end_time ){
            $condition[] = ['add_time', '<=', strtotime($end_time.' 23:59:59')];
        }
        $condition = $this->getUserNameCondition($username, $condition);
        $condition[] = ['rec_id', '=', $this->uid];
        $field = 'id,username,phone,rec_code,mail,add_time';
        $list = UserModel::where($condition)
            ->field($field)
            ->page($page, $limit)
            ->order('add_time desc')
            ->select()->toArray();
        foreach ($list as &$v) {
            $v['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
            $bonusData = ReportService::getBonuses(0, 99999999999, $v['id']);
            //奖金总额
            $v['total_bonus'] = $bonusData['amount'];
            //活动奖金
            $v['action_bonus'] = $bonusData['action_amount'];
            //反水
            $v['back_bonus'] = Db::name('third_stats_back')
                ->where('sb_payed', '=', 2)
                ->where('sb_uid', '=', $v['id'])
                ->sum('sb_backmoney');
            //返佣
            $v['commission_bonus'] = Db::name('third_stats_fyfy')
                ->where('sff_payed', '=', 2)
                ->where('sff_uid', '=', $v['id'])
                ->sum('sff_fy');
        }

        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }

    //返佣比例
    public function commissionRate_run(){
        $rate = env('FYFY_BILLING_LEVEL_OUTSIDE_RATE');
        $rateArr = [];
        if( $rate ){
            foreach (explode(',', $rate) as $k => $v){
                $rateArr[] = [
                    'level' => $k + 1,
                    'rate' => $v,
                ];
            }
        }
        $rsps['data'] = $rateArr;
        $rsps['code'] = 1;
        return $rsps;
    }

    //直属开户（下级开户）
    public function childAccount_run(){
        $info = $this->param;
        $username = $this->param['username']?? '';
        $password = $this->param['password']?? '';
        //密码注册
        try {
            validate(\app\common\validate\RegisterValidate::class)->scene('create')->check($info);
        } catch (\think\exception\ValidateException $e) {
            // 验证失败 输出错误信息
            ajax_return($e->getError());
        }
        $userDetail = UserModel::where('id', '=', $this->uid)->find();


        $add = [];
        //判断是邮箱还是手机号
        if( checkPhone($username) ){
            //手机号注册
            $add['phone'] = $username;
            //判断手机号是否注册
            if( UserModel::where('phone', '=', $username)->value('id') ){
                ajax_return('手机号已存在');
            }
        }elseif( strexists($username,'@') ){
            //邮箱注册
            $add['mail'] = $username;
            //判断邮箱是否注册
            if( UserModel::where('mail', '=', $username)->value('id') ) {
                ajax_return('邮箱已存在');
            }
        }else{
            ajax_return('仅支持邮箱或手机号注册');
        }
        $agent = UserModel::get_agent_id($this->uid);
        $rec_id = $agent[0];
        $rec_id2 = $agent[1];
        $rec_id3 = $agent[2];
        $add['mail_bind_status'] = 0;
        $add['phone_bind_status'] = 0;
        $add['reg_ip'] = Util::get_ip();
        $add['add_time'] = time();
        $add['lebal'] = 1;
        $add['rec_id'] = $rec_id;//推广人id
        $add['rec_id2'] = $rec_id2;
        $add['rec_id3'] = $rec_id3;
        $add['password'] = Util::userMd5Password($password);
        $add['username'] = Util::random(30);
        $add['head_img'] = DefaultHeadModel::getHeadOne();//默认头像
        $add['reg_url'] = getAppUrl();
        $add['rec_code']=Util::rec_code();
        Db::startTrans();
        try {
            $user = UserModel::create($add);
            if( !$user ){
                Db::rollback();
                ajax_return('网络异常');
            }
            $id = $user->id;
            if(empty($userDetail->tree)){
                $tree = "/".$id."/";
            }else{
                $tree = $userDetail->tree.$id."/";
            }
            //添加额外信息表
            $res = UserExtraModel::create(['uid' => $id]);
            if( !$res ){
                Db::rollback();
                ajax_return('网络异常');
            }

            $tree1 = explode("/", $tree);
            $new_tree = array_filter($tree1);

            $user->tree = $tree;
            $user->rec_level = count($new_tree);
            $res = $user->save();
            if( !$res ){
                Db::rollback();
                ajax_return('网络异常');
            }
            // 发布用户注册事件
            event(new \app\event\UserRegisterEvent($user));
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            ajax_return('网络异常');
        }
        $rsps['data'] = [];
        $rsps['msg'] = 'success';
        $rsps['code'] = 1;
        return $rsps;
    }

    //用户名筛选条件数组
    private function getUserNameCondition($username, $condition = [], $alias = ''){
        if( $username !== '' ){
            $ret = checkPhone($username);
            $ret2 = strexists($username,'@');
            if( $alias ){
                if( $ret || $ret2 ){
                    //是邮箱或者号码
                    $condition[] = [$alias . '.mail|'. $alias . '.rec_code|'. $alias .'.phone|' . $alias .'.id', '=', $username];
                }else{
                    if( is_numeric($username) ){
                        $condition[] = [$alias . '.id', '=', $username];
                    }else {
                        $condition[] = [$alias . '.username', '=', '%' . $username . '%'];
                    }
                }
            }else{
                if( $ret || $ret2 ){
                    //是邮箱或者号码
                    $condition[] = ['mail|phone|id|rec_code', '=', $username];
                }else{
                    if( is_numeric($username) ){
                        $condition[] = ['id', '=', $username];
                    }else{
                        $condition[] = ['username', '=', '%'.$username.'%'];
                    }
                }
            }

        }
        return $condition;
    }

}