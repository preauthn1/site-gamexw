<?php

namespace app\job;

use app\api\factory\ActionFactory;
use app\common\model\GameBetsModel;
use app\common\model\GamesModel;
use app\common\model\IntegralMxModel;
use app\common\model\UserCkModel;
use app\common\model\UserMsgModel;
use app\common\model\UserOfflineModel;
use app\common\model\ActionModel;
use app\common\model\ActionApplyModel;
use app\common\model\UserModel;
use app\common\service\AgentService;
use app\common\service\MemberService;
use app\common\service\WalletService;
use app\common\Utils;
use Carbon\Carbon;
use think\queue\Job;

class ActivityTask {
    public $uid = 0;
    private function isActive($params) {
        $action = $params['activity'] ?? null;
        $user = $params['user'] ?? null;
        $recharge = $params['recharge'] ?? null;
        $from_user_id = isset($params['from_user_id']) ? intval($params['from_user_id']) : 0;
        if (!$action || !$user) {
            $this->output("活动或用户不存在");
            return false;
        }
        $this->uid = $user['id'];
        // 判断是否禁止参与活动
        if (MemberService::tagBan($this->uid, 'is_activity')) {
            $this->output("[{$user['id']}]禁止参与活动，请联系客服");
            return false;
        }

        //首充或复充必须要充值数据
        if( in_array($action['base_type'], [1, 2]) && !$recharge ){
            $this->output("无充值数据");
            return false;
        }

        //获取域名或用户对应的代理对应代理条件
        //判断是否需要agent为0条件
        $registerDomain = MemberService::getRegisterDomain($user['id']);
        $isNeedZeroWhere = AgentService::judgeIsNeedZeroWhere($user['id']);
        if( !$isNeedZeroWhere ){
            //域名为代理站的则不查询代理id为0的
            if( $action['agent_id'] == '0' ){
                $this->output("[{$user['id']}]禁止参与总站活动，请联系客服");
                return false;
            }
        }

        // 0 所有用户，1新用户，2老用户
        // 新用户为活动申请开始时间之后注册的用户，
        // 老用户为活动申请开始时间之前注册的用户
        if (isset($action['user_type']) && $action['user_type'] > 0) {
            if ($action['user_type'] == 1) {
                if ($user['add_time'] < $action['t_apply_start_time']) {
                    $this->output("{$user['id']}注册时间早于活动[{$action['dis_code']}]开始时间，禁止参与");
                    return false;
                }
            } else {
                if ($user['add_time'] > $action['t_apply_start_time']) {
                    $this->output("{$user['id']}注册时间晚于活动[{$action['dis_code']}]开始时间，禁止参与");
                    return false;
                }
            }
        }
        if ($action['zg_level']!="all" && $user['level_id']!=$action['zg_level']) {
            $this->output("[{$user['id']}]申请资格会员等级[{$user['level_id']}]->[{$action['zg_level']}]不满足");
            return false;
        }
        $user_tags = MemberService::getUserTagId($this->uid);
        //全部活动通用代理线限制
        foreach (explode(',', $action['agent_id']) as $v){
            if(isset($action['agent_id']) && $action['agent_id'] > 0){
                $tree = UserModel::where([["tree",'like','%/'.$v.'/%']])->column("id");
                if(!in_array($this->uid,$tree) && $action['is_consist'] == 2){
                    $this->output("[{$user['id']}]未达到活动要求,无法参与");
                    return false;
                }
                if(in_array($this->uid,$tree) && $action['is_consist'] == 1){
                    $this->output("[{$user['id']}]未达到活动要求,无法参与2");
                    return false;
                }
            }
        }
//        if($action['base_type'] == 6){
//        }else{
//            if (isset($action['agent_id']) && $action['agent_id'] > 0) {
//                $tree1 = explode("/", $user['tree']);
//                if (!in_array($tree1[1], explode(',', $action['agent_id']))) {
//                    $this->output("[{$user['id']}]代理线条件不符合");
//                    return false;
//                }
//            }
//        }
        if (isset($action['tag_id']) && $action['tag_id'] > 0) {
            if (!in_array($action['tag_id'], $user_tags)) {
                $this->output("[{$user['id']}]用户标签条件不符合");
                return false;
            }
        }
        //有选择活动则只能参与充值选择的活动
        if( isset($recharge['not_auto_action']) && $recharge['not_auto_action'] > 1 ){
            //大于1为充值时有选择活动
            if( $recharge['not_auto_action'] != $action['id'] ){
                $this->output("[{$user['id']}]充值选择活动不正确");
                return false;
            }
        }
        //首充和复充的充值时间必须要在活动时间内
        if( in_array($action['base_type'], [1, 2]) ){
            if( !$recharge ){
                $this->output("首充/复充没有充值数据");
                return false;
            }
            if( $action['t_apply_start_time'] >= $recharge['update_time'] || $action['t_apply_end_time'] <= $recharge['update_time'] ){
                $this->output("首充/复充的充值数据没有在活动时间内");
                return false;
            }
        }
        // 首充活动
        if ($action['base_type'] == 1) {
            // 首充活动强制用户领取一次
            $action['base_apply_dr_max'] = 1;
        }
        // 再存活动
        else if ($action['base_type'] == 2 ) {
            //如果设置了首充第二天后才能参与
            if( $action['is_first_tomorrow_join'] == 1 ){
                $_condition = [];
                $_condition[] = ['first', '=', 1];
                $_condition[] = ['uid', '=', $user['id']];
                $_condition[] = ['status', 'in', '3,4'];
                $firstTime = UserOfflineModel::where($_condition)->value('update_time');
                if( !$firstTime || $firstTime >= Carbon::now()->startOfDay()->timestamp ){
                    $this->output("该活动设置首充第二天后才能参与");
                    return false;
                }
            }
            // 未设置充值要求
            if ($action['zg_cz_type'] == "all") {
                $this->output("[{$user['id']}]充值要求不满足");
                return false;
            }
            //判断充值要求时
            if($action['zg_cz_type'] == 2){
                if (empty($recharge)) {
                    $this->output("单笔充值要求下没有充值数据");
                    return false;
                }
                // 已领取过奖励
                $my_ck_id = UserCkModel::where('action_id', $action['id'])->where('ck_r_id', $recharge['id'])->find();
                if (!empty($my_ck_id)) {
                    $this->output("单笔充值要求下已领取奖励");
                    return false;
                }
            } elseif ($action['zg_cz_type'] == 3) { // 上周
                $toweek_count = ActionApplyModel::whereWeek("add_time")->where("action_id", $action['id'])->where("uid", $user['id'])->count();
                // 每星期限制领取一次
                if ($toweek_count > 0) {
                    $this->output("充值要求上周时本周已领取奖励");
                    return false;
                }
            } elseif (in_array($action['zg_cz_type'], [4, 7])) { // 昨天
                $today_count = ActionApplyModel::whereDay("add_time")->where("action_id", $action['id'])->where("uid", $user['id'])->count();
                // 每天限制领取一次
                if ($today_count > 0) {
                    $this->output("充值要求昨日充值/昨日累计充值时今天已领取");
                    return false;
                }
            } elseif ($action['zg_cz_type'] == 5) { // 今日累计
                $condition = [];
                $condition[] = ['add_time', '>=', strtotime('today')];
                $condition[] = ['add_time', '<=', time()];
                $today_count = ActionApplyModel::where($condition)->where("action_id", $action['id'])->where("uid", $user['id'])->count();
                // 每天限制领取一次
                if ($today_count > 0) {
                    $this->output("充值要求今日累计时今天已领取");
                    return false;
                }
            } else {
                // 累计充值活动强制用户领取一次
                $action['base_apply_dr_max'] = 1;
            }
        }
        //一般优惠
        else if( $action['base_type'] == 3 ){
            //不验证
        }
        //体验金
        else if( $action['base_type'] == 4 ){
            //不验证，已弃用
        }
        //展示活动
        else if( $action['base_type'] == 5 ){
            $this->output("展示活动没有奖励");
            return false;
        }
        // 游戏救援金
        elseif ($action['base_type'] == 6) {
            $wh = [
                ['uid','=',$user['id']],
                ['action_id','=',$action['id']],
                ['ps_status','in','0,1']
            ];
            if($action['date_type'] == 1){
                $msg = '昨日';
                $info = ActionApplyModel::whereDay("add_time")->where($wh)->value("id");
            }else{
                $msg = '上周';
                $info = ActionApplyModel::whereWeek("add_time")->where($wh)->value("id");
            }
            if($info){
                $this->output("[{$user['id']}]".$msg."救援金已存在");
                return false;
            }
        }
        //推荐奖励
        elseif ($action['base_type'] == 7){
            return true;
        }else {
            if ($action['zg_cz_type'] != "all") {
                $olpay=UserOfflineModel::is_pay($user['id']);
                if (!isset($olpay['id'])) {
                    $this->output("没有充值数据");
                    return false;
                }
            }
        }
        if (isset($action['mutually_exclusive_id']) && $action['mutually_exclusive_id'] > 0) {
            $mutually_exclusive_action = ActionApplyModel::where("action_id", $action['mutually_exclusive_id'])->where("uid",$this->uid)->count();
            if (!empty($mutually_exclusive_action)) {
                $this->output("[{$user['id']}]无法同时参与互斥活动[{$action['mutually_exclusive_id']} -> {$action['id']}]");
                return false;
            }
        }
        // 个人上限
        $apply_count = ActionApplyModel::where("action_id", $action['id'])->where("uid",$this->uid)->count();
        if (!($action['base_apply_dr_max'] > $apply_count) && $action['base_type'] != 6) {
            $this->output("[{$user['id']}]申请次数已满，无法再次申请");
            return false;
        }
        // 总申请上限
        $apply_count = ActionApplyModel::where("action_id", $action['id'])->count();
        if (!($action['base_apply_number_max'] > $apply_count)) {
            $this->output("[{$action['id']}]申请次数已满，无法再次申请");
            return false;
        }
        //验证用户每日领取次数
        if( isset($action['everyday_person_num']) && $action['everyday_person_num'] > 0 ){
            //获取已申请次数
            $applyNum = (new ActionFactory())->getUserApplyNum($this->uid, $action['id']);
            if( $applyNum >= $action['everyday_person_num'] ){
                $this->output("[{$user['id']}]今日领取次数已达上线，请明日再来");
                return false;
            }
        }
        //判断是否设置有参与指定活动后才能参与
        if( isset($action['previous_action_id']) && $action['previous_action_id'] > 0 ){
            if( (new ActionFactory())->getUserApplyNum($this->uid, $action['previous_action_id'], $action['t_apply_start_time'], strtotime('today')) < 1 ){
                $this->output("[{$user['id']}昨天前未参与上个活动，无法申请该活动");
                return false;
            }
        }

        return true;
    }


    private function sendActive($params) {
        $action = $params['activity'] ?? null;
        $user = $params['user'] ?? null;
        $recharge = $params['recharge'] ?? null;
        $from_user_id = isset($params['from_user_id']) ? intval($params['from_user_id']) : 0;
        if (!$action || !$user) {
            return false;
        }

        $ActionApplyModel = new ActionApplyModel();
        $ck_amount = 0;
        $ck_r_id = 0;
        if (!empty($recharge) && isset($recharge['id'])) {
            $ck_r_id = $recharge['id'];
        }
        $add_apply = [
            'add_time' => time(),
            'uid' => $this->uid,
            'action_id' => $action['id']
        ];
        // 再存活动
        if (in_array($action['base_type'], [2, 1])) {
            $reg_json = json_decode($action['reg_json'],true);//充值规则
            if (empty($reg_json)) {
                $this->output("[{$user['id']}]->[{$action['id']}]充值规则无效");
                return false;
            }
            $last_names = array_column($reg_json,'min_reg');
            array_multisort($last_names,SORT_DESC,$reg_json);//最低 降序
            $where = [
                ['uid', '=', $this->uid],
                ['status', 'IN', '3,4'],
                ['add_time', '>=', $action['t_apply_start_time']]
            ];
            if( $action['zg_cz_type'] != 7 ){
                //除去昨日累计不包含首充的
                // 首充活动 or 单笔充值活动
                if( $action['base_type'] == 1 ){
                    //首充
                    $where[] = ['first', '=', 1];
                }else{
                    $where[] = ['first', '<>', 1];
                }
            }

            // 首充活动 or 单笔充值活动 or 今日单笔
            $is_first_or_sigle = in_array($action['zg_cz_type'], [2, 6]) || $action['base_type'] == 1;
            if($is_first_or_sigle){
                $ck_amount = $recharge['arrival_price'] ?? 0;
            } elseif ($action['zg_cz_type'] == 3) { // 上周
                $where[] = ['add_time', '>=', Carbon::now()->subWeek()->startOfWeek()->timestamp];
                $where[] = ['add_time', '<=', Carbon::now()->subWeek()->endOfWeek()->timestamp];
            } elseif (in_array($action['zg_cz_type'], [4, 7])) { // 昨天
                $where[] = ['add_time', '>=', Carbon::yesterday()->startOfDay()->timestamp];
                $where[] = ['add_time', '<', Carbon::yesterday()->endOfDay()->timestamp];
            }elseif ($action['zg_cz_type'] == 5){ //今日累计
                $where[] = ['add_time', '>=', strtotime('today')];
                $where[] = ['add_time', '<=', time()];
            }
            if ( !$is_first_or_sigle ) {
                $ck_amount = UserOfflineModel::where($where)->sum('arrival_price');
            }

            if (empty($ck_amount)) {
                $this->output("[{$user['id']}]->[{$action['id']}]存款金额[{$ck_amount}]无效");
                return false;
            }
            $ck_amount = round($ck_amount);
            $now_jl = [];//奖励方式
            foreach ($reg_json as $key => $value) {
                if ($ck_amount >= $value['min_reg'] && $ck_amount <= $value['max_reg']) {
                    $now_jl = $value;
                }
            }

            if (empty($now_jl)) {
                $this->output("[{$user['id']}]->[{$action['id']}]存款金额未达到活动要求");
                return false;
            }
            if (!isset($now_jl['integral_amount'])|| $now_jl['integral_amount'] == '') {
                $now_jl['integral_amount'] = 0;
            }
            if (!isset($now_jl['bl_or_amount']) || $now_jl['bl_or_amount'] == '') {
                $now_jl['bl_or_amount'] = 0;
            }
            $add_ck = [
                'add_time' => time(),
                'uid' => $this->uid,
                'ck_r_id' => $ck_r_id,
                'action_id' => $action['id'],
                'ck_amount' => $ck_amount,
                //1 固定， 2 比例
                'ck_bl' => ($now_jl['type'] == 2) ? $now_jl['bl_or_amount'] : 0,
                'ck_hl_amount' => ($now_jl['type'] == 2) ? round($ck_amount * $now_jl['bl_or_amount'] / 100, 2) : $now_jl['bl_or_amount'],
                'ck_integral_rate' => ($now_jl['type'] == 2) ? $now_jl['integral_amount'] : 0,
                'ck_integral' => ($now_jl['type'] == 2) ? round($ck_amount * $now_jl['integral_amount'] / 100, 2) : $now_jl['integral_amount'],
            ];

            $ck_id = UserCkModel::insertGetId($add_ck);
            //流水是否加上本金计算
            if( $action['savings_is_include_fee'] == 1 ){
                //包含本金
                $water = $action['water'] * ($add_ck['ck_hl_amount'] + $ck_amount);
            }else{
                //不包含本金
                $water = $action['water'] * $add_ck['ck_hl_amount'];
            }
            if(!empty($ck_id)){
                $upd_ck['ck_number'] = date("YmdHi") . $ck_id;
                $upd_ck['id'] = $ck_id;
                UserCkModel::update($upd_ck);//存款编号
                $add_apply['user_ck_id'] = $ck_id;
                $add_apply['ck_bl'] = $add_ck['ck_bl'];
                $add_apply['ck_hl_amount'] = $add_ck['ck_hl_amount'];
                $add_apply['integral_rate'] = $add_ck['ck_integral_rate'];
                $add_apply['integral'] = $add_ck['ck_integral'];
                $add_apply['water'] = $water;
            }else{
                $this->output("[{$user['id']}]->[{$action['id']}]UserCkModel添加失败");
                return false;
            }
        }
        elseif($action['base_type'] == 6){
            $t_apply_start_time = date('Ymd',$action['t_apply_start_time']);
            $t_apply_end_time = date('Ymd',$action['t_apply_end_time']);
            $where = [];$whereStr = '';
            $where[] = ['su_uid','=',$this->uid];
            $where[] = ['su_dt','between',[$t_apply_start_time,$t_apply_end_time]];
            if($action['date_type'] == 1){
                $andtime = date('Ymd',Carbon::yesterday()->timestamp);
                $where [] = ['su_dt','=',$andtime];
            }else{
                $andtime = date('Ymd',Carbon::now()->startOfWeek()->timestamp);
                $endtime = date('Ymd',Carbon::now()->endOfWeek()->timestamp);
                $where [] =  ['su_dt','between',[$andtime,$endtime]];
            }
            $game_type = json_decode($action['game_type'],true) ??[];
            $level_json = json_decode($action['level_json'],true) ??[];
            if($game_type){
                $sql = GamesModel::where([['g_type','in',$game_type]])->field('g_id')->buildSql();
                $whereStr = 'su_gid in ('.$sql .')';
            }
            $tz_loss = getTransportTzloss($where,$whereStr);
            if($tz_loss <= 0){
                $this->output("[{$user['id']}]->[{$action['id']}]暂无救援金");
                return false;
            }
            $action = (new ActionFactory())->jyjdata($action, $user);
            $price = $action['jyj_price'];
            $add_apply['water'] = $action['water'] * $price;
            $add_apply['ck_hl_amount']=$price;
            if($price <= 0){
                $this->output("[{$user['id']}]->[{$action['id']}]暂无救援金");
                return false;
            }
        }
        else if($action['base_type'] == 7){
            if( $user['rec_id'] == 0 ){
                $this->output("[{$user['id']}]无上级，活动结束");
                return false;
            }

            // 个人上限
            $apply_count = ActionApplyModel::where("action_id", $action['id'])->where("uid",$user['rec_id'])->count();
            if (!($action['base_apply_dr_max'] > $apply_count) ) {
                $this->output("[{$user['id']}]申请次数已满，无法再次申请");
                return false;
            }
            // 总申请上限
            $apply_count = ActionApplyModel::where("action_id", $action['id'])->count();
            if (!($action['base_apply_number_max'] > $apply_count)) {
                $this->output("[{$action['id']}]申请次数已满，无法再次申请");
                return false;
            }
            //验证用户每日领取次数
            if( isset($action['everyday_person_num']) && $action['everyday_person_num'] > 0 ){
                //获取已申请次数
                $applyNum = (new ActionFactory())->getUserApplyNum($user['rec_id'], $action['id']);
                if( $applyNum >= $action['everyday_person_num'] ){
                    $this->output("[{$user['id']}]今日领取次数已达上线，请明日再来");
                    return false;
                }
            }

            // 判断上级是否禁止参与活动
            if (MemberService::tagBan($user['rec_id'], 'is_activity')) {
                $this->output("[{$user['rec_id']}]禁止参与活动，请联系客服");
                return false;
            }
            $action['month_activity'] = json_decode($action['month_activity'],true);
            $recharge = UserOfflineModel::where('update_time','>=',$action['t_apply_start_time'])->where('uid', $user->id)->whereIn('status', [3,4])->select();
            if (empty($recharge)){
                $this->output("[{$user['id']}]充值记录异常->[{$action['id']}]充值记录为null");
                return false;
            }

            $award = [];
            // 验证充值记录是否达标
            foreach ($action['month_activity'] AS $ac){
                $recharge_total = [];
                // 统计每日充值金额及充值天数
                foreach ($recharge AS $item){
                    if (isset($recharge_total[date('Ymd',$item['update_time'])])){
                        $recharge_total[date('Ymd',$item['update_time'])] += $item['apply_price'];
                    }else{
                        $recharge_total[date('Ymd',$item['update_time'])] = $item['apply_price'];
                    }
                }
                $data = [];
                foreach ($recharge_total AS $key => $value){
                    if ($value >= $ac['money']){
                        $data[$key] = $value;
                    }
                }
                $recharge_total = $data;
                // 验证用户天数是否达标
                if (count($recharge_total) >= $ac['day']){
                    // 验证是否领取过奖励
                    $action_record = $ActionApplyModel->where('contributor',$user['id'])->where('consecutive_days',$ac['day'])->where('action_id',$action['id'])->find();
                    if (empty($action_record)){
                        $award = $ac; // 未领取
                        break;
                    }
                }
            }
            // 验证是否达到领取条件
            if (empty($award)){
                $this->output("[{$user['id']}]充值记录未达标");
                return false;
            }
            $add_apply['contributor']       = $add_apply['uid'];
            $add_apply['uid']               = $user['rec_id'];
            $add_apply['water']             = $action['water'] * $award['bonus'];
            $add_apply['consecutive_days']  = $award['day'];
            $add_apply['ck_hl_amount']      = $award['bonus'];
        }
        else {
            $add_apply['gd_amount']=$action['jl_bl_price'];
            $add_apply['water'] = $action['water'] * $action['jl_bl_price'];
        }

        $queueStatus = true;#阻断状态是是否能发放
        $add_apply['queue_status'] = ActionApplyModel::QUEUE_STATUS_NORMAL;

        //验证实名认证
        if( !MemberService::judgeUserIsCanReceiveDiscount($this->uid) ){
            $add_apply['queue_status'] = ActionApplyModel::QUEUE_STATUS_REAL_NAME_BLOCK;
            $queueStatus = false;
//            $this->output("[{$user['id']}]未实名认证，无法领取");
//            return false;
        }

        //双钱包验证是否能重复领取
        if( !WalletService::judgeUserIsRePeatReceive(0, $user) ){
            $add_apply['queue_status'] = ActionApplyModel::QUEUE_STATUS_BALANCE_BLOCK;
            $queueStatus = false;
//            $this->output("[{$user['id']}]账户还存在彩金，不能重复领取领取");
//            return false;
        }

        $apply_id = $ActionApplyModel->insertGetId($add_apply);
        //自动审核
        if($apply_id && $action['auto_review'] == 1 && $queueStatus){
            $actionFactory = new ActionFactory();
            $actionFactory->auto_review($apply_id);
            $this->output("[{$user['id']}]->[{$action['id']}]自动发放奖励");
        }
        return $apply_id;
    }

    private function getActivitys($type, $actionId = 0) {
        $now = time();
        if( $actionId > 0 ){
            $where = [
                ['id', '=', $actionId],
            ];
        }else{
            $where = [
                ['auto_apply', '=', 1],
                ['base_type', '=', $type],
            ];
        }

        $list = ActionModel::where($where)
            ->whereTime('t_show_start_time', "<=", $now)
            ->whereTime('t_show_end_time', ">=", $now)
            ->whereTime('t_apply_start_time', "<=", $now)
            ->whereTime('t_apply_end_time', ">=", $now)
            ->select();
        return $list;
    }

    public function type1(Job $job, $data) {
        if (empty($data['uid']) || empty($data['recharge_id'])) {
            $job->delete(); //删除
            return;
        }
        $user = UserModel::where('id', $data['uid'])->find();
        if (empty($user)) {
            $this->output("{$data['uid']}用户信息不存在");
            $job->delete(); //删除
            return;
        }
        $recharge = UserOfflineModel::where('id', $data['recharge_id'])->where('first', 1)->where('uid', $user->id)->whereIn('status', [3,4])->find();

        //TODO 注释因为后面满足等级条件后又会赠送，导致一次充值多次赠送
//        if (empty($recharge)) {
//            //没有数据可能是登录或者其他方式触发的，没有recharge_id,重新查询一次
//            $recharge = UserOfflineModel::where('first', 1)->where('uid', $user->id)->whereIn('status', [3,4])->find();
//            if (empty($recharge)) {
//                $this->output("{$data['recharge_id']}充值记录不存在");
//                $job->delete(); //删除
//                return;
//            }
//        }
        $list = $this->getActivitys(1, $data['action_id'] ?? 0);
        if ($list->isEmpty()) {
            $job->delete(); //删除
            return;
        }
        $this->run($list, $user, $recharge);
        $job->delete();
    }
    public function type2(Job $job, $data) {
        if (empty($data['uid'])) {
            $job->delete(); //删除
            return;
        }
        $user = UserModel::where('id', $data['uid'])->find();
        if (empty($user)) {
            $this->output("{$data['uid']}用户信息不存在");
            $job->delete(); //删除
            return;
        }
        //TODO 注释因为后面满足等级条件后又会赠送，导致一次充值多次赠送
//        if (isset($data['recharge_id'])) {
            $recharges = UserOfflineModel::where('id', $data['recharge_id'])->where('first', '<>', 1)->where('uid', $user->id)->whereIn('status', [3,4])->select();
//        }else{
//            //没有充值id，为其他地方触发，重新查询一次
//            $recharges = UserOfflineModel::where('first', '<>', 1)->where('uid', $user->id)->whereIn('status', [3,4])->select();
//        }

        $list = $this->getActivitys(2, $data['action_id'] ?? 0);
        if ($list->isEmpty()) {
            $job->delete(); //删除
            return;
        }
        foreach ($recharges as $recharge){
            $this->run($list, $user, $recharge);
        }
        $job->delete();
    }

    public function type3(Job $job, $data) {
        if (empty($data['uid'])) {
            $job->delete(); //删除
            return;
        }
        $user = UserModel::where('id', $data['uid'])->find();
        if (empty($user)) {
            $this->output("{$data['uid']}用户信息不存在");
            $job->delete(); //删除
            return;
        }
        $list = $this->getActivitys(3, $data['action_id'] ?? 0);
        if ($list->isEmpty()) {
            $job->delete(); //删除
            return;
        }
        $this->run($list, $user);
        $job->delete();
    }

    public function type6(Job $job, $data) {
        if (empty($data['uid'])) {
            $job->delete(); //删除
            return;
        }
        $user = UserModel::where('id', $data['uid'])->find();
        if (empty($user)) {
            $this->output("{$data['uid']}用户信息不存在");
            $job->delete(); //删除
            return;
        }
        $list = $this->getActivitys(6);
        if ($list->isEmpty()) {
            $job->delete(); //删除
            return;
        }
        $this->run($list, $user);
        $job->delete();
    }

    public function type7(Job $job, $data) {
        if (empty($data['uid'])) {
            $job->delete(); //删除
            return;
        }
        $user = UserModel::where('id', $data['uid'])->find();
        if (empty($user)) {
            $this->output("{$data['uid']}用户信息不存在");
            $job->delete(); //删除
            return;
        }

        $list = $this->getActivitys(7, $data['action_id'] ?? 0);
        if ($list->isEmpty()) {
            $job->delete(); //删除
            return;
        }
        $this->run($list, $user);
        $job->delete();
    }

    private function output($msg = '') {
        echo $msg . ";,时间:" . date("Y-m-d H:i:s") . "\n";
    }

    private function run($list, $user, $recharge = null) {
        foreach ($list as $activity) {
            try {
                $params = ['activity' => $activity, 'user' => $user, 'recharge' => $recharge];
                $this->output("{$user['id']}开始参与活动[{$activity->id}]判断");
                if (!$this->isActive($params)) {
                    $this->output("{$user['id']}不符合[{$activity->id}]规则");
                    continue;
                }
                $r = $this->sendActive($params);
                if ($r) {
                    //修改已申请人数
                    $activity->base_y_apply_dr += 1;
                    $activity->save();
                }
            } catch (\Exception $e) {
                Utils::L("activity_task", [$activity->toArray(), $user->toArray(), $recharge ? $recharge->toArray() : null, $e->getMessage(), $e->getTraceAsString()]);
            }
        }
    }
}