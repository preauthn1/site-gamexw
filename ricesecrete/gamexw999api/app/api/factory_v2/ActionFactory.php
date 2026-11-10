<?php

namespace app\api\factory_v2;

use app\common\model\ActionModel;
use app\common\model\ActionApplyModel;
use app\common\model\ActionTypeModel;
use app\common\model\GameBetsModel;
use app\common\model\GamesModel;
use app\common\model\IntegralMxModel;
use app\common\model\UserAwardCfgsModel;
use app\common\model\UserAwardLogsModel;
use app\common\model\UserLevelModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\model\UserCkModel;
use app\common\model\UserOfflineModel;
use app\common\model\OlPayModel;
use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\model\UserTagModel;
use app\common\service\AgentService;
use app\common\service\MemberService;
use app\common\service\MessageService;
use app\common\Utils;
use Carbon\Carbon;
use think\facade\Request;
use think\facade\Db;
use app\common\model\LotteryConfigModel;
use app\common\model\LotteryRecordModel;
use app\common\model\LotteryPrizeModel;
use app\common\model\UserBalanceMxModel;

class ActionFactory
{
    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->uid = $uid;
    }

    //活动申请（现金返还领取）
    public function apply_run()
    {
        $action_id = $this->param['action_id'];
        $ActionModel = new ActionModel();
        $ActionApplyModel = new ActionApplyModel();
        $ApiRedis = new ApiRedis();
        //锁
        if (!$ApiRedis->lock($this->uid)) {
            ajax_return("请勿频繁访问", 0);
        }
        // 判断是否禁止参与活动
        if (MemberService::tagBan($this->uid, 'is_activity')) {
            ajax_return("禁止参与活动，请联系客服", 0);
        }
        $user = UserModel::uinfo($this->uid);
        $action = $ActionModel
            ->alias("a")
            ->where("a.base_show_level='all' or a.base_show_level<=" . $user['level_id'])
            ->where(time() . " >= a.t_show_start_time and " . time() . "<= a.t_show_end_time")
            ->where("a.id", $action_id)
            ->field("a.*")
            ->find();
        if (!isset($action['id'])) {
            ajax_return("数据不存在", 0);
        }
        // 0 所有用户，1新用户，2老用户
        // 新用户为活动申请开始时间之后注册的用户，
        // 老用户为活动申请开始时间之前注册的用户
        if (isset($action['user_type']) && $action['user_type'] > 0) {
            if ($action['user_type'] == 1) {
                if ($user['add_time'] > $action['t_apply_start_time']) {
                    ajax_return("不符合活动申请条件", 0);
                }
            } else {
                if ($user['add_time'] < $action['t_apply_start_time']) {
                    ajax_return("不符合活动申请条件", 0);
                }
            }
        }
        if (!(time() >= $action['t_apply_start_time'] && time() <= $action['t_apply_end_time'])) {
            $msg = time() <= $action['t_apply_end_time'] ? '未开始' : '已结束';
            ajax_return("申请" . $msg, 0);
        }
        if ($action['zg_level'] != "all" && $user['level_id'] != $action['zg_level']) {
            ajax_return("申请资格会员等级不满足", 0);
        }
        if ($action['zg_cz_type'] != "all") {
            $olpay = UserOfflineModel::is_pay($this->uid);
            if (!isset($olpay['id'])) {
                ajax_return("充值要求不满足", 0);
            }
        }
        if (isset($action['mutually_exclusive_id']) && $action['mutually_exclusive_id'] > 0) {
            $mutually_exclusive_action = $ActionApplyModel->where("action_id", $action['mutually_exclusive_id'])->where("uid", $this->uid)->count();
            if (!empty($mutually_exclusive_action)) {
                ajax_return("你无法同时参与互斥活动");
            }
        }

        if (isset($action['tag_id']) && $action['tag_id'] > 0 && $action['base_type'] != 6) {
            $user_tags = MemberService::getUserTagId($this->uid);
            if (!in_array($action['tag_id'], $user_tags)) {
                ajax_return("用户标签条件不符合");
            }
        }
        //验证用户每日领取次数
        if (isset($action['everyday_person_num']) && $action['everyday_person_num'] > 0) {
            //获取已申请次数
            $applyNum = $this->getUserApplyNum($this->uid, $action['id']);
            if ($applyNum >= $action['everyday_person_num']) {
                ajax_return('今日领取次数已达上限，请明日再来');
            }
        }

        // 首存活动，累计充值活动强制用户领取一次
        if ($action['base_type'] == 1 || ($action['base_type'] == 2 && $action['zg_cz_type'] == 1)) {
            $action['base_apply_dr_max'] = 1;
        }

        $apply_count = $ActionApplyModel->where("action_id", $action['id'])->where("uid", $this->uid)->count();
        if (!($action['base_apply_dr_max'] > $apply_count) && $action['base_type'] != 6 && $action['base_type'] != 7) {
            ajax_return("申请次数已满，无法再次申请", 0);
        }
        //判断是否设置有参与指定活动后才能参与
        if (isset($action['previous_action_id']) && $action['previous_action_id'] > 0) {
            if ((new ActionFactory())->getUserApplyNum($this->uid, $action['previous_action_id'], $action['t_apply_start_time'], strtotime('today')) < 1) {
                ajax_return('昨天前未参与上个活动，无法申请该活动');
            }
        }
        //限制代理活动
        if (isset($action['agent_id']) && $action['agent_id'] > 0) {
            foreach (explode(',', $action['agent_id']) as $v) {
                $tree = UserModel::where([["tree", 'like', '%/' . $v . '/%']])->column("id");
                if (!in_array($this->uid, $tree) && $action['is_consist'] == 2) {
                    ajax_return("未达到活动要求,无法参与", 0);
                }
                if (in_array($this->uid, $tree) && $action['is_consist'] == 1) {
                    ajax_return("未达到活动要求,无法参与", 0);
                }
            }
        }
        Db::startTrans();
        try {
            if ($action['base_type'] == 1) {
                $othr_id = $this->apply_first($action, $user);
                $msg_content = "注册首存申请成功";
            } else if ($action['base_type'] == 2) {
                $othr_id = $this->apply_two($action, $user);
                $msg_content = "再存优惠申请成功";
            } else if ($action['base_type'] == 3) {
                $othr_id = $this->apply_three($action);
                $msg_content = "一般优惠申请成功";
            } else if ($action['base_type'] == 4) {
                $othr_id = $this->apply_four($action);
                $msg_content = "体验金申请成功";
            } else if ($action['base_type'] == 6) {
                $othr_id = $this->apply_six($action, $user);
                $msg_content = "游戏救援金申请成功";
            } else if ($action['base_type'] == 7) {
                $othr_id = $this->apply_seven($action, $user);
                $msg_content = "推荐奖励申请成功";
            } else {
                ajax_return("类型错误", 0);
            }
            $upd['base_y_apply_dr'] = $action['base_y_apply_dr'] + 1;
            $upd['id'] = $action['id'];
            $ActionModel->update($upd);//修改已申请人数
            UserMsgModel::add_msg($this->uid, "优惠申请", $msg_content, 2, $othr_id);
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        $ApiRedis->unlock($this->uid);//释放锁
        return $rsps;
    }

    //获取用户今日已申请活动次数
    public function getUserApplyNum($uid, $actionId, $startTime = 0, $endTime = 0)
    {
        if ($startTime == 0) $startTime = strtotime('today');
        if ($endTime == 0) $endTime = time();
        $condition = [];
        $condition[] = ['action_id', '=', $actionId];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['add_time', 'between', [$startTime, $endTime]];
        $condition[] = ['ps_status', 'in', [0, 1]];
        return ActionApplyModel::where($condition)->count();
    }

    //注册首存
    private function apply_first($action, $user)
    {
        $recharge = UserOfflineModel::where(['uid' => $this->uid, 'first' => 1])->where("status in (3,4)")->find();
        if (empty($recharge)) {
            ajax_return("存款金额未达到活动要求", 0);
        }
        $ck_r_id = $recharge['id'];
        $ck_amount = $recharge['arrival_price'];
        $reg_json = json_decode($action['reg_json'], true);
        if (empty($reg_json)) {
            ajax_return("暂无数据", 0);
        }
        $last_names = array_column($reg_json, 'min_reg');
        array_multisort($last_names, SORT_DESC, $reg_json);//最低 降序

        $now_jl = [];//奖励方式
        foreach ($reg_json as $key => $value) {
            if ($ck_amount >= $value['min_reg'] && $ck_amount <= $value['max_reg']) {
                $now_jl = $value;
            }
        }

        if (empty($now_jl)) {
            ajax_return("存款金额未达到活动要求", 0);
        }
        if (!isset($now_jl['integral_amount'])) {
            $now_jl['integral_amount'] = 0;
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
        if (!empty($ck_id)) {
            $upd_ck['ck_number'] = date("YmdHi") . $ck_id;
            $upd_ck['id'] = $ck_id;
            UserCkModel::update($upd_ck);//存款编号

            $add_apply['add_time'] = time();
            $add_apply['uid'] = $this->uid;
            $add_apply['action_id'] = $action['id'];
            $add_apply['user_ck_id'] = $ck_id;
            $add_apply['ck_bl'] = $add_ck['ck_bl'];
            $add_apply['ck_hl_amount'] = $add_ck['ck_hl_amount'];
            $add_apply['integral_rate'] = $add_ck['ck_integral_rate'];
            $add_apply['integral'] = $add_ck['ck_integral'];
            $add_apply['water'] = $action['water'] * $add_ck['ck_hl_amount'];
            $apply_id = ActionApplyModel::insertGetId($add_apply);
            //自动审核
            if ($apply_id && $action['auto_review'] == 1) {
                $this->auto_review($apply_id);
            }
            return $apply_id;
        } else {
            return false;
        }
    }


    //再存优惠
    private function apply_two($action, $user)
    {
        $where = [
            ['uid', '=', $this->uid],
//            ["receive", '=', 0],
            ['status', 'IN', '3,4'],
            ['first', '<>', 1],
            ['add_time', '>=', $action['t_apply_start_time']]
        ];
        $ck_amount = null;
        $ck_r_id = 0;
        if ($action['zg_cz_type'] == 2) {
            $my_ck_id = UserCkModel::where('action_id', $action['id'])->where('uid', $this->uid)->column('ck_r_id');
            $recharge = UserOfflineModel::where($where)->whereNotIn('id', $my_ck_id)->order('arrival_price desc')->find();
            if (empty($recharge)) {
                ajax_return("申请次数已满，无法再次申请", 0);
            }
            $ck_r_id = $recharge['id'];
            $ck_amount = $recharge['arrival_price'];
        } elseif ($action['zg_cz_type'] == 3) { // 上周
            $where[] = ['add_time', '>=', Carbon::now()->subWeek()->startOfWeek()->timestamp];
            $where[] = ['add_time', '<=', Carbon::now()->subWeek()->endOfWeek()->timestamp];
            $toweek_count = ActionApplyModel::whereWeek("add_time")->where("action_id", $action['id'])->where("uid", $this->uid)->count();
            // 每星期限制领取一次
            if ($toweek_count > 0) {
                ajax_return("申请次数已满，无法再次申请", 0);
            }
        } elseif ($action['zg_cz_type'] == 4) { // 昨天
            $where[] = ['add_time', '>=', Carbon::yesterday()->timestamp];
            $where[] = ['add_time', '<', Carbon::today()->timestamp];
            $today_count = ActionApplyModel::whereDay("add_time")->where("action_id", $action['id'])->where("uid", $this->uid)->count();
            // 每天限制领取一次
            if ($today_count > 0) {
                ajax_return("申请次数已满，无法再次申请", 0);
            }
        } elseif ($action['zg_cz_type'] == 5) { //今日累计
            $where[] = ['add_time', '>=', strtotime('today')];
            $where[] = ['add_time', '<=', time()];
            $today_count = ActionApplyModel::whereDay("add_time")->where("action_id", $action['id'])->where("uid", $this->uid)->count();
            // 每天限制领取一次
            if ($today_count > 0) {
                ajax_return("申请次数已满，无法再次申请", 0);
            }

        } elseif ($action['zg_cz_type'] == 6) {//今日单笔
            $where[] = ['add_time', '>=', strtotime('today')];
            $where[] = ['add_time', '<=', time()];
            $ck_amount = UserOfflineModel::where($where)->max('arrival_price');
        }
        if ($action['zg_cz_type'] != 2 && $action['zg_cz_type'] != 6) {
            $ck_amount = UserOfflineModel::where($where)->sum('arrival_price');
        }

        if (empty($ck_amount)) {
//            $ck_amount = 0;
            ajax_return("存款金额未达到活动要求", 0);
        }

        $reg_json = json_decode($action['reg_json'], true);//充值规则
        if (empty($reg_json)) {
            ajax_return("暂无数据", 0);
        }
        $last_names = array_column($reg_json, 'min_reg');
        array_multisort($last_names, SORT_DESC, $reg_json);//最低 降序

        $now_jl = [];//奖励方式
        foreach ($reg_json as $key => $value) {
            if ($ck_amount >= $value['min_reg'] && $ck_amount <= $value['max_reg']) {
                $now_jl = $value;
            }
        }
        if (empty($now_jl)) {
            ajax_return("存款金额未达到活动要求", 0);
        }

        if (!isset($now_jl['integral_amount']) || $now_jl['integral_amount'] == '') {
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
        if (!empty($ck_id)) {
            $upd_ck['ck_number'] = date("YmdHi") . $ck_id;
            $upd_ck['id'] = $ck_id;
            UserCkModel::update($upd_ck);//存款编号
            $add_apply['add_time'] = time();
            $add_apply['uid'] = $this->uid;
            $add_apply['action_id'] = $action['id'];
            $add_apply['user_ck_id'] = $ck_id;
            $add_apply['ck_bl'] = $add_ck['ck_bl'];
            $add_apply['ck_hl_amount'] = $add_ck['ck_hl_amount'];
            $add_apply['integral_rate'] = $add_ck['ck_integral_rate'];
            $add_apply['integral'] = $add_ck['ck_integral'];
            $add_apply['water'] = $action['water'] * $add_ck['ck_hl_amount'];
            $apply_id = ActionApplyModel::insertGetId($add_apply);
//            UserOfflineModel::where($where)->update(['receive' => 1]);
            //自动审核
            if ($apply_id && $action['auto_review'] == 1) {
                $this->auto_review($apply_id);
            }
            return $apply_id;
        } else {
            return false;
        }
    }

    //固定金额
    private function apply_three($action)
    {
        $ActionApplyModel = new ActionApplyModel();
        $add_apply['add_time'] = time();
        $add_apply['uid'] = $this->uid;
        $add_apply['action_id'] = $action['id'];
        $add_apply['gd_amount'] = $action['jl_bl_price'];
        $add_apply['water'] = $action['water'] * $action['jl_bl_price'];
        $apply_id = $ActionApplyModel->insertGetId($add_apply);
        //自动审核
        if ($apply_id && $action['auto_review'] == 1) {
            $this->auto_review($apply_id);
        }
        return $apply_id;
    }

    //固定金额
    private function apply_four($action)
    {
        $ActionApplyModel = new ActionApplyModel();
        $add_apply['add_time'] = time();
        $add_apply['uid'] = $this->uid;
        $add_apply['action_id'] = $action['id'];
        $add_apply['tyj_amount'] = $action['jl_bl_price'];
        $add_apply['tyj_date'] = $action['base_ty_ts'];
        $add_apply['water'] = $action['water'] * $action['jl_bl_price'];
        $apply_id = $ActionApplyModel->insertGetId($add_apply);
        //自动审核
        if ($apply_id && $action['auto_review'] == 1) {
            $this->auto_review($apply_id);
        }
        return $apply_id;
    }

    //游戏救援金领取
    private function apply_six($action, $user)
    {
        if ($action['auto_apply'] == 1) {
            ajax_return("该活动为自动发放，无需手动领取", 0);
        }
        $wh = [
            ['uid', '=', $this->uid],
            ['action_id', '=', $action['id']],
            ['ps_status', 'in', '0,1']
        ];
        $t_apply_start_time = date('Ymd', $action['t_apply_start_time']);
        $t_apply_end_time = date('Ymd', $action['t_apply_end_time']);
        $where = [];
        $whereStr = '';
        $where[] = ['su_uid', '=', $this->uid];
        $where[] = ['su_dt', 'between', [$t_apply_start_time, $t_apply_end_time]];
        if ($action['date_type'] == 1) {
            $andtime = date('Ymd', Carbon::yesterday()->timestamp);
            $where [] = ['su_dt', '=', $andtime];
            $msg = '昨日';
            $info = ActionApplyModel::whereDay("add_time")->where($wh)->value("id");
        } else {
            $andtime = date('Ymd', Carbon::now()->startOfWeek()->timestamp);
            $endtime = date('Ymd', Carbon::now()->endOfWeek()->timestamp);
            $where [] = ['su_dt', 'between', [$andtime, $endtime]];
            $msg = '本周';
            $info = ActionApplyModel::whereWeek("add_time")->where($wh)->value("id");
        }
        if ($info) {
            ajax_return($msg . "救援金已存在");
        }
        $game_type = json_decode($action['game_type'], true) ?? [];
        $level_json = json_decode($action['level_json'], true) ?? [];
        if ($game_type) {
            $sql = GamesModel::where([['g_type', 'in', $game_type]])->field('g_id')->buildSql();
            $whereStr = 'su_gid in (' . $sql . ')';
        }
        $tz_loss = getTransportTzloss($where, $whereStr);
        if ($tz_loss <= 0) {
            ajax_return("暂无救援金");
        }
        //获取金额
        $action = $this->jyjdata($action, $user);
        $price = $action['jyj_price'];
        if ($price <= 0) {
            ajax_return("暂无救援金");
        }
        $add_apply['add_time'] = time();
        $add_apply['uid'] = $this->uid;
        $add_apply['action_id'] = $action['id'];
        $add_apply['water'] = $action['water'] * $price;
        $add_apply['ck_hl_amount'] = $price;
        $apply_id = ActionApplyModel::insertGetId($add_apply);
        //自动审核
        if ($apply_id && $action['auto_review'] == 1) {
            $this->auto_review($apply_id);
        }
        return $apply_id;
    }

    //月活动奖励领取
    private function apply_seven($action, $user)
    {
        if ($action['auto_apply'] == 1) {
            ajax_return("暂无活动", 0);
        }
        $action['month_activity'] = json_decode($action['month_activity']);
        // 获取直属下级Id
        $result = UserModel::where('rec_id', $user['id'])->column('id');

        $sub_user = 0;
        $award = null;
        foreach ($action['month_activity'] as $value) {
            // 获取已领奖的下级
            $apply_user = ActionApplyModel::where('action_id', $action['id'])
                ->where('uid', $user['id'])
                ->where('consecutive_days', $value->day)
                ->whereIn('ps_status', [0, 1])
                ->column('contributor');
            // 验证未领取的下级进行领取
            foreach ($result as $item) {
                // 验证奖励是否已经发放
                if (!in_array($item, $apply_user)) {
                    // 获取用户充值总数
                    $user_recharge = UserOfflineModel::field("FROM_UNIXTIME(update_time,'%Y%m%d') AS days,SUM(apply_price) AS amount")
                        ->where('update_time', '>=', $action['t_apply_start_time'])
                        ->where('uid', $item)
                        ->whereIn('status', [3, 4])
                        ->group("days")
                        ->select();
                    // 验证用户充值总数是否足够
                    if (count($user_recharge) >= $value->day) {
                        $total_num = 0;
                        foreach ($user_recharge as $v) {
                            if ($v['amount'] >= $action['day_recharge_amount'] && $v['days'] != null) {
                                $total_num += 1;
                            }
                        }
                        // 验证有效充值天数是否足够
                        if ($total_num >= $value->day) {
                            $sub_user = $item;
                            $award = $value;
                            break;
                        }
                    }
                }
            }
        }
        if (empty($sub_user)) {
            ajax_return(empty($apply_user) ? "未有已达标的下级" : "达标奖励已发放");
        }

        $add_apply['add_time'] = time();
        $add_apply['uid'] = $this->uid;
        $add_apply['action_id'] = $action['id'];
        $add_apply['contributor'] = $sub_user;
        $add_apply['consecutive_days'] = $award->day;
        $add_apply['water'] = $action['water'] * $award->bonus;
        $add_apply['ck_hl_amount'] = $award->bonus;
        $apply_id = ActionApplyModel::insertGetId($add_apply);
        //自动审核
        if ($apply_id && $action['auto_review'] == 1) {
            $this->auto_review($apply_id);
        }
        return $apply_id;
    }


    //自动通过审核
    public function auto_review($apply_id)
    {
        Db::startTrans();
        try {
            $ActionApplyModel = new ActionApplyModel();
            $UserCkModel = new UserCkModel();
            $UserModel = new UserModel();
            $apply = $ActionApplyModel
                ->alias("a")
                ->join("dq_user b", "a.uid=b.id")
                ->join("dq_action c", "a.action_id=c.id")
                ->where(["a.id" => $apply_id, 'a.ps_status' => 0])
                ->field("a.*,c.base_type,c.water_type,c.water_type_ids,c.water as action_water")
                ->find();
            if ($apply) {
                $msg = '';
                $amount = 0;
//        UserMsgModel::add_msg($pay['uid'], "充值送积分", '积分入账 {:integral}', 11, $pay['id'],['integral'=>$Config['integral']]);
                if ($apply['base_type'] == 1 || $apply['base_type'] == 2) {
                    $ck = $UserCkModel->where(["id" => $apply['user_ck_id'], 'ck_status' => 0])->find();
                    if ($ck) {
                        $amount = $apply['ck_hl_amount'];
                        $msg = $apply['base_type'] == 1 ? '注册首存奖励' : '再存优惠奖励';
                        $UserModel->cz_balance($apply['uid'], $ck['ck_hl_amount'], "+", true, $msg, 5, 0, 1, $apply['id'], true, 101);
                        $upd_ck['id'] = $ck['id'];
                        $upd_ck['ck_status'] = 1;
                        $UserCkModel->update($upd_ck);
                    }
                } elseif ($apply['base_type'] == 3) {
                    $msg = "优惠固定奖励";
                    $amount = $apply['gd_amount'];
                    $UserModel->cz_balance($apply['uid'], $apply['gd_amount'], "+", true, $msg, 5, 0, 1, $apply['id'], true, 101);
                } elseif ($apply['base_type'] == 4) {
                    $msg = "体验金";
                    $amount = $apply['tyj_amount'];
                    $UserModel->cz_balance($apply['uid'], $apply['tyj_amount'], "+", true, $msg, 5, 0, 1, $apply['id'], true, 101);
                    $day = "+" . $apply['tyj_date'] . ' day';
                    $upd_user['id'] = $apply['uid'];
                    $upd_user['ty_balance'] = $apply['tyj_amount'];
                    $upd_user['ty_end_date'] = strtotime($day);
                    $UserModel->update($upd_user);
                } elseif ($apply['base_type'] == 6) {
                    $msg = "游戏救援金";
                    $amount = $apply['ck_hl_amount'];
                    $UserModel->cz_balance($apply['uid'], $apply['ck_hl_amount'], "+", true, $msg, 5, 0, 1, $apply['id'], true, 101);
                } elseif ($apply['base_type'] == 7) {
                    $msg = "推荐奖励";
                    $amount = $apply['ck_hl_amount'];
                    $UserModel->cz_balance($apply['uid'], $apply['ck_hl_amount'], "+", true, $msg, 5, 0, 1, $apply['id'], true, 101);
                }
                if ($apply['integral']) {
                    IntegralMxModel::cz_mx($apply['uid'], 1, $apply['integral'], $msg ?? '活动积分奖励');
                    MessageService::sendJsonMsg([
                        'msg' => "活动积分奖励",
                        'amount' => 0
//                        'amount' => $apply['integral']
                    ], $apply['uid'], 'activity', 5, "活动优惠");
                }
                $upd['ps_deta'] = time();
                $upd['ps_status'] = 1;
                $ActionApplyModel->where("id", $apply['id'])->update($upd);
                if (!in_array($apply['water_type'], [1, 2])) {
                    //普通流水
                    $UserModel->where(['id' => $apply['uid']])->inc("savings", $apply['water'])->update();
                }
                if (in_array($apply['water_type'], [1, 2])) {
                    UserAwardCfgsModel::createData($apply, $amount);
                    MemberService::addUserWaterDetail($apply['uid'], $apply['water'], 2, "活动优惠", $apply['id'], $amount, $apply['action_water']);
                }
                if ($msg) {
                    MessageService::sendJsonMsg([
                        'msg' => $msg,
                        'amount' => $amount
                    ], $apply['uid'], 'activity', 5, "活动优惠");
                }
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }
    }


    //获取充值优惠活动
    public static function getRechargeDiscountActionDetail($userDetail)
    {
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $time = time();
        $condition = [];
        $condition[] = ['t_apply_start_time', '<=', $time];
        $condition[] = ['t_apply_end_time', '>=', $time];
        $condition1 = "(zg_level >= {$userDetail['level_id']} or zg_level = 'all')";#活动等级限制
        $userTagArr = implode(',', UserTagModel::where('uid', '=', $userDetail['id'])->column('tag_id'));
        //用户标签限制
        if ($userTagArr) {
            $condition1 .= " AND (tag_id in ({$userTagArr}) or tag_id = 0)";
        }
        $discountCondition = $condition;
        $first = 0;
        //判断是否是首充
        if (UserOfflineModel::where(["uid" => $userDetail['id'], 'first' => 1])->where([["status", 'in', "3,4"]])->count() == 0) {
            //首充，查找首充优惠的活动
            $condition[] = ['base_type', '=', 1];
            $first = 1;
        } else {
            //再存
            $condition[] = ['base_type', '=', 2];
        }
        $regJsonArr = [];
        //代理线条件
        $condition2 = AgentService::getAgentWhere();
        //获取活动数据（首充和再存）
        $regJson = ActionModel::with('news')->where($condition)
            ->where($condition1)->where($condition2)->field('id,base_type,reg_json')->select();
        if ($regJson) {
            foreach ($regJson as $v) {
                $_regJson = json_decode($v['reg_json'], true);
                $rechargeImage = '';
                foreach ($v->news as $__v) {
                    //判断当前语言，获取当前语言的图
                    if ($__v['lang'] == $lang) {
                        //判断是首充还是再存
                        $rechargeImage = $__v['first_recharge_images'];
                        if ($v['base_type'] == 2) {
                            //再存
                            $rechargeImage = $__v['recharge_images'];
                        }
                    }
                }
                foreach ($_regJson as $_v) {
                    $_v['recharge_image'] = $rechargeImage;
                    $regJsonArr[] = $_v;
                }
            }
        } else {
            //没有首充和再存时候显示一般优惠
            //获取活动数据（一般优惠）
            $discountCondition[] = ['base_type', '=', 3];
            $condition1 .= " and (jl_ck_or_level_s <= {$userDetail['level_id']} and jl_ck_or_level_e >= {$userDetail['level_id']})";#活动等级限制
            $regJsonDiscountList = ActionModel::with('news')->where($discountCondition)
                ->where($condition1)->field('id,jl_bl_price');
            foreach ($regJsonDiscountList as $v) {
                //组合跟首充和再存一样的格式
                $arr = [
                    'type' => '1',
                    'min_reg' => 1,//一般优惠只有等级领取区间，没有最大和最小区间，这里取最小和最大的值
                    'max_reg' => 999999999,
                    'bl_or_amount' => $v,
                    'content' => $v->news,
                    'recharge_image' => '',
                ];
                $regJsonArr[] = $arr;
            }
        }
        $newActionList = [];
        if ($first) {
            //查询活动列表
            $actionList = ActionModel::with('news')
                ->where($condition)->where($condition1)
                ->field('id,reg_json,t_apply_end_time')
                ->order('sort desc,id desc')
                ->select();
            foreach ($actionList as $k => $v) {
                $v->news_title = '';
                $v->news_images = '';
                $v->reg_json = json_decode($v->reg_json, true);
                //判断当前语言，获取当前语言的图
                foreach ($v->news as $_k => $_v) {
                    //判断当前语言，获取当前语言的图
                    if ($_v['lang'] == $lang) {
                        $v->news_title = $_v['news_title'];
                        $v->news_images = $_v['news_images'];
                        break;
                    }
                }
                //没有标题删除活动
                if (!$v->news_title) {
                    continue;
//                    unset($actionList[$k]);
                }
                unset($v->news);
                $v['end_time'] = date("Y-m-d H:i:s");
                $newActionList[] = $v;
            }
//            $actionList = $actionList->toArray();
        } else {
            $actionList = [];
        }

        return ['reg_json' => $regJsonArr, 'action_list' => $newActionList];
    }


    //游戏救援金列表
    public function jyjdetail_run()
    {
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $where = [];
        $where[] = ["a.t_show_start_time", "<=", time()];
        $where[] = ["a.t_show_end_time", ">=", time()];
        $where[] = ["a.base_type", "=", 6];
        if (!empty($lang)) {
            $where[] = ["b.lang", "=", $lang];
        }
        $where_str = [];
        $uinfo = UserModel::where("id", $this->uid)->field("tree,level_id,balance,water_balance")->find();
        $level_id = 0;
        $balance = 0;
        $water_balance = 0;
        if (isset($uinfo['level_id'])) {
            $level_id = $uinfo['level_id'];
            $balance = $uinfo['balance'];
            $water_balance = $uinfo['water_balance'];
        }
        $user_tag = UserTagModel::where(['uid' => $this->uid])->value('tag_id') ?? -1;
        $where_str[] = "(a.is_consist = 2 and (" .
            AgentService::getAgentWhere('agent_id', true, 'a')
            . ") and a.tag_id = IF(a.tag_id > 0,$user_tag,0)) or (a.is_consist = 1 and (" .
            AgentService::getAgentWhere('agent_id', true, 'a', false)
            . ") and a.tag_id <> $user_tag)";

        $action = ActionModel::alias("a")
            ->join("dq_news b", "a.id=b.action_id")
            ->where(implode(" and ", $where_str))
            ->where($where)
            ->field("a.id,a.base_type,a.type_id,a.auto_apply,a.base_name,a.dis_code,a.c_label,a.auto_apply,a.t_apply_start_time,a.t_apply_end_time, b.news_title as c_title, b.news_intro as c_intro, b.news_images as c_img, b.news_content as c_detail,a.is_hot,a.jump_url,a.date_type,a.game_type,a.level_json,a.reg_json,a.rebate_type,a.reward_max")
            ->order("a.sort DESC, a.id desc")
            ->find();
        $userLevelModel = new UserLevelModel();
        $level = $userLevelModel->field('id,water')->select();
        $level_arr = [];
        foreach ($level as $value) {
            $level_arr[$value['id']] = $value['water'];
        }
        //统计返现金额
        $action = $this->jyjdata($action, $uinfo);
        $action['t_apply_start_time'] = date("Y-m-d H:i:s", $action['t_apply_start_time']);
        $action['t_apply_end_time'] = date("Y-m-d H:i:s", $action['t_apply_end_time']);
        $level_json = json_decode($action['level_json'], true) ?? [];
        $reg_json = [];
        $cashback = [];
        if ($action['rebate_type'] == 1) {
            $reg_json = json_decode($action['reg_json'], true) ?? [];
            $proportion = 0;
            $level = 1;
            $max_reg = 0;
            if ($reg_json) {
                foreach ($reg_json as $key => $v) {
                    $reg_json[$key]['type'] = intval($v['type']);
                    $reg_json[$key]['level_name'] = lang('阶段') . ($key + 1);
                    if ($water_balance >= $v['min_reg'] && $water_balance <= $v['max_reg']) {
                        $max_reg = $v['max_reg'];
                        //判断类型
                        if ($v['type'] == 1) {
                            //固定金额奖励
                            $price = $v['bl_or_amount'];
                            $proportion = ($price / $v['max_reg']) * 100;
                        } else {
                            $proportion = $v['bl_or_amount'];
                        }
                        $level = $key + 1;
                    }
                }
            }
            $next_water = isset($reg_json[$level + 1]) ? $reg_json[$level + 1]['min_reg'] : 0;
            $cashback = [
                'level' => $level,
                'level_name' => lang('阶段') . $level,
                'proportion' => $proportion,
                'next_level' => ($level + 1),
                'next_level_name' => lang('阶段') . ($level + 1),
                'now_water' => floatval(sprintf("%.2f", $water_balance)),
                'now_ratio' => floatval(sprintf("%.2f", $max_reg <= 0 ? 0 : $water_balance / $max_reg * 100)),
                'next_water' => floatval(sprintf("%.2f", $next_water)),
            ];
        } else {
            foreach ($level_json as $key => &$value) {
                $value['water'] = $level_arr[$value['level']];
                if (intval($level_id) == intval($value['level'])) {
                    $next_water = isset($level_arr[$key + 1]) ? $level_arr[$level_json[$key + 1]['level']] : $level_arr[$level_json[$key]['level']];
                    $cashback = [
                        'level' => $value['level'],
                        'level_name' => $value['level_name'],
                        'proportion' => $value['proportion'],
                        'next_level' => isset($level_json[$key + 1]) ? $level_json[$key + 1]['level'] : $level_json[$key]['level'],
                        'next_level_name' => isset($level_json[$key + 1]) ? $level_json[$key + 1]['level_name'] : $level_json[$key]['level_name'],
                        'now_water' => floatval(sprintf("%.2f", $water_balance)),
                        'now_ratio' => floatval(sprintf("%.2f", $water_balance / $next_water * 100)),
                        'next_water' => floatval(sprintf("%.2f", $next_water)),
                    ];
                }
            }
        }
        $action['cashback'] = $cashback;
        if ($action['date_type'] == 1) {
            $start_time = date('Y-m-d H:i:s', Carbon::now()->today()->timestamp);
            $end_time = date('Y-m-d H:i:s', Carbon::now()->today()->timestamp + (24 * 3600) - 1);
        } else {
            $start_time = date('Y-m-d H:i:s', Carbon::now()->startOfWeek()->timestamp);
            $end_time = date('Y-m-d H:i:s', Carbon::now()->endOfWeek()->timestamp);
        }
        if (!empty($cashback)) {
            $action['cashback_title'] = [
                'proportion' => $cashback['proportion'],
                'balance' => $balance
            ];
        }
        $action['return_start_time'] = $start_time;
        $action['return_end_time'] = $end_time;
        $action['level_json'] = $level_json;
        $action['reg_json'] = $reg_json;
        unset($action['game_type'], $action['reward_max']);
        $rsps['data'] = $action;
        $rsps['code'] = 1;
        return $rsps;
    }


    public function jyjdata($val, $uinfo)
    {
        $whereStr = '';
        $where2 = [];
        $game_type = json_decode($val['game_type'], true) ?? [];
        $level_json = json_decode($val['level_json'], true) ?? [];
        $t_apply_start_time = date('Ymd', $val['t_apply_start_time']);
        $t_apply_end_time = date('Ymd', $val['t_apply_end_time']);
        $where2[] = ['su_uid', '=', $this->uid];
        $where2[] = ['su_dt', 'between', [$t_apply_start_time, $t_apply_end_time]];
        $wh = [
            ['uid', '=', $this->uid],
            ['action_id', '=', $val['id']],
            ['ps_status', 'in', '0,1']
        ];
        if ($val['date_type'] == 1) {
            $info = ActionApplyModel::whereDay("add_time")->where($wh)->value("id");
        } else {
            $info = ActionApplyModel::whereWeek("add_time")->where($wh)->value("id");
        }
        if ($info) {
            $jyj_type = 1;  //明日预计领取 今日/本周
            if ($val['date_type'] == 1) {
                $andtime = date('Ymd', Carbon::now()->today()->timestamp);
                $where2 [] = ['su_dt', '=', $andtime];
            } else {
                $andtime = date('Ymd', Carbon::now()->startOfWeek()->timestamp);
                $endtime = date('Ymd', Carbon::now()->endOfWeek()->timestamp);
                $where2 [] = ['su_dt', 'between', [$andtime, $endtime]];
            }
        } else {
            $jyj_type = 2; //今日可领取 昨日/本周
            if ($val['date_type'] == 1) {
                $andtime = date('Ymd', Carbon::yesterday()->timestamp);
                $where2 [] = ['su_dt', '=', $andtime];
            } else {
                $andtime = date('Ymd', Carbon::now()->startOfWeek()->timestamp);
                $endtime = date('Ymd', Carbon::now()->endOfWeek()->timestamp);
                $where2 [] = ['su_dt', 'between', [$andtime, $endtime]];

            }
        }
        if ($game_type) {
            $sql = GamesModel::where([['g_type', 'in', $game_type]])->field('g_id')->buildSql();
            $whereStr = 'su_gid in (' . $sql . ')';
        }
        $tz_loss = getTransportTzloss($where2, $whereStr);
        if ($jyj_type == 2 && $tz_loss <= 0) {
            $where3 = [
                ['su_uid', '=', $this->uid],
                ['su_dt', 'between', [date('Ymd', $val['t_apply_start_time']), date('Ymd', $val['t_apply_end_time'])]],
            ];
            if ($val['date_type'] == 1) {
                $andtimes = date('Ymd', Carbon::now()->today()->timestamp);
                $where3 [] = ['su_dt', '=', $andtimes];
            } else {
                $andtimes = date('Ymd', Carbon::now()->startOfWeek()->timestamp);
                $endtimes = date('Ymd', Carbon::now()->endOfWeek()->timestamp);
                $where3 [] = ['su_dt', 'between', [$andtimes, $endtimes]];
            }
            $tz_loss = getTransportTzloss($where3, $whereStr);
            $jyj_type = 1;
        }
        $tz_loss = trim($tz_loss, '-');
        $price = 0;
        if (isset($val['rebate_type']) && $val['rebate_type'] == 1) {
            //按金额奖励
            $regJson = json_decode($val['reg_json'], true);
            foreach ($regJson as $v) {
                if ($tz_loss >= $v['min_reg'] && $tz_loss <= $v['max_reg']) {
                    //判断类型
                    if ($v['type'] == 1) {
                        //固定金额奖励
                        $price = $v['bl_or_amount'];
                    } else {
                        //按比例奖励
                        $price = round($tz_loss * ($v['bl_or_amount'] / 100), 2);
                    }
                    break;
                }
            }
        } else {
            //按VIP等级奖励
            $proportion = 0;
            if ($uinfo) {
                foreach ($level_json as $v) {
                    if ($v['level'] == $uinfo['level_id']) {
                        $proportion = $v['proportion'];
                    }
                }
            }
            if ($proportion > 0) {
                $price = round($tz_loss * $proportion / 100, 2);
            }
        }
        if (isset($val['reward_max']) && $val['reward_max'] > 0) {
            //奖励上限
            $price = min($price, $val['reward_max']);
        }
        $val['jyj_price'] = $price;
        $val['jyj_type'] = $jyj_type;
        return $val;
    }

}