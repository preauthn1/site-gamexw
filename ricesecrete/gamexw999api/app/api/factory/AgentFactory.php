<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\AgentSettingModel;
use app\common\model\UserAgentModel;
use app\common\model\UserLevelModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\redis\CommomRedis;
use app\common\service\AgentSettingService;
use app\common\service\MemberService;
use app\common\service\MessageService;
use Carbon\Carbon;
use PHPQRCode\QRcode;
use think\facade\Db;
use think\facade\Request;

class AgentFactory
{
    private $param;//请求参数
    private $uid;//用户id
    private $uinfo;//用户数据

    //验证
    public function __construct($uid = 0, $agent = null)
    {
        $this->param = Request::param();
        $this->uid = $uid;
        $this->uinfo = UserModel::where("id", $this->uid)->find();
    }

    /**
     * 刷新用户数据，用于在同一个业务处理批次中用户表数据有更新后重新加载用户数据
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author twt326
     * @date 2022-03-03 4:12
     */
    public function refresh()
    {
        $this->uinfo = UserModel::where("id", $this->uid)->find();
    }

    //微商中心
    public function core_run()
    {
        $ApiRedis = new ApiRedis();
        $data = UserModel::alias('a')->where("a.id", $this->uid)->join('dq_user_agent b', 'b.uid=a.id', 'left')->field("a.username,a.head_img,a.agent_balance,a.tg_balance,b.status")->find();
        $redis_key = md5("core-init-" . $this->uid);
        $data = $ApiRedis->get($redis_key);
        if (empty($data)) {
            $data['agent_balance'] = AgentRakeBackModel::where([['uid', '=', $this->uid], ['status', 'in', '3,4']])->sum('money');
            $data['tg_balance'] = AgentSellLogModel::where(['uid' => $this->uid, 'status' => 3])->sum('money');
            $ApiRedis->set($redis_key, $data, 60);
        }
        $data['total'] = $data['agent_balance'] + $data['tg_balance'];
        $data['agent'] = empty($data['status']) ? 0 : $data['status'];
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function user_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $level = $this->param['level'] ?? 4;
        $isday = $this->param['isday'] ?? 0;
        $date = $this->param['date'] ?? '';
        $username = $this->param['username'] ?? '';
        $agent = $this->uinfo;
        $where = $where1 = [];
        $ApiRedis = new ApiRedis();

        switch (intval($level)) {
            case 1:
                $where[] = ["rec_id", "=", $this->uid];
                break;
            case 2:
                $where[] = ["rec_id2", "=", $this->uid];
                break;
            case 3:
                $where[] = ["rec_id3", "=", $this->uid];
                break;
            default:
                $where[] = ["rec_id|rec_id2|rec_id3", "=", $this->uid];
                break;
        }

        if ($isday == 1) {
            $start_time = strtotime(date("Y-m-d") . " 00:00:00");
            $end_time = strtotime(date("Y-m-d") . " 23:59:59");
            $where[] = ["add_time", ">=", $start_time];
            $where[] = ["add_time", "<=", $end_time];
        } else {
            $time = undate($date);
            if (!empty($time)) {
                $where[] = ["add_time", ">=", $time[0]];
                $where[] = ["add_time", "<=", $time[1]];
            }
        }

        if (!empty($username)) {
            if (is_numeric($username)) {
                $where[] = ["id|rec_code|phone", "=", $username];
                $where1[] = ["id|rec_code|phone", "=", $username];
            } else {
                $where[] = ["username|mail", "LIKE", "%{$username}%"];
                $where1[] = ["username|mail", "LIKE", "%{$username}%"];
            }
        }

        //会员等级名称
        $levelArr = UserLevelModel::where('id', '>', 0)->column('level_name', 'id');

        $table = \app\common\service\BetService::getThirdStatsTable();

        $list = UserModel::where($where)->limit((($pagenow - 1) * $limit), $limit)->field("id,username,rec_code,rec_level,level_id,status,balance,head_img,add_time")->select();
        foreach ($list as $key => &$val) {
            $val['level'] = $val['rec_level'] - $agent['rec_level'];
            $val['add_time'] = date("Y-m-d", $val['add_time']);
            $len = mb_strlen($val['username']);
            $str1 = mb_substr($val['username'], 0, 2);
            $str2 = mb_substr($val['username'], $len - 2, 2);
            $val['username'] = $str1 . '****' . $str2;
            //计算用户总充值
            $price = 0;
            foreach ($val->offline as $v) {
                if (in_array($v['status'], [3, 4])) {
                    $price += $v['arrival_price'];
                }
            }
            $val['total_offline'] = $price;
            unset($val['offline']);

            //投注
            $val['total_bet'] = Db::name($table['_stats_users'])
                ->where('su_uid', '=', $val['id'])
                ->sum('su_bet');
            //总下级
            $val['child_num'] = UserModel::where("rec_id", '=', $val['id'])->count();

            //会员等级名称
            $val['level_name'] = $levelArr[$val['rec_level']] ?? '';

        }
        $data['list'] = $list;
        $redis_key = md5("core-user-count-" . json_encode($where));
        $count = $ApiRedis->get($redis_key);
        if (empty($count)) {
            $count['user_count1'] = UserModel::where($where1)->where("rec_id", $this->uid)->count();
            $count['user_count2'] = UserModel::where($where1)->where("rec_id2", $this->uid)->count();
            $count['user_count3'] = UserModel::where($where1)->where("rec_id3", $this->uid)->count();
            $count['user_count'] = $count['user_count1'] + $count['user_count2'] + $count['user_count3'];

            $count['user_sum_money1'] = floor(UserModel::where($where1)->where("rec_id", $this->uid)->sum('balance') * 100) / 100;
            $count['user_sum_money2'] = floor(UserModel::where($where1)->where("rec_id2", $this->uid)->sum('balance') * 100) / 100;
            $count['user_sum_money3'] = floor(UserModel::where($where1)->where("rec_id3", $this->uid)->sum('balance') * 100) / 100;
            $count['user_sum_money'] = floor(($count['user_sum_money1'] + $count['user_sum_money2'] + $count['user_sum_money3']) * 100) / 100;
            //注册人数
            $count['register_user_count'] = UserModel::where($where)->count();
            //充值人数
            $uid = $this->uid;
            $count['recharge_user_count'] = UserOfflineModel::whereIn("uid", function ($query) use ($where) {
                $query->name("user")->where($where)->field('id');
            })->whereIn("status", [3, 4])->group('uid')->count();
            $ApiRedis->set($redis_key, $count, 60);
        }

        $rsps['data'] = array_merge($data, $count);
        $rsps['code'] = 1;
        return $rsps;
    }

    /*
     * 统计提现
     */
    public function statcash_run()
    {
        $ApiRedis = new ApiRedis();
        $isday = $this->param['isday'] ?? 0;
        $date = $this->param['date'] ?? "";
        $redis_key = md5("cash-" . $isday . "-" . $date . "-" . $this->uid);
        $data = $ApiRedis->get($redis_key);
        if (empty($data)) {
            $where = "status=3";
            if ($isday == 1) {
                $start_time = strtotime(date("Y-m-d") . " 00:00:00");
                $end_time = strtotime(date("Y-m-d") . " 23:59:59");
                $where .= " and update_time > " . $start_time . " and update_time < " . $end_time;
            } else {
                $time = undate($date);
                if (!empty($time)) {
                    $where .= " and update_time > " . $time[0] . " and update_time < " . $time[1];
                }
            }
            $agent = $this->uinfo;
            // 一级提现总额
            $sql = userLevelSql($this->uid, $agent['rec_level'], 1);
            $withdraw_count1 = UserWithdrawalModel::where($where . " and uid in(" . $sql . ")")->sum("w_price");

            // 二级提现总额
            $sql = userLevelSql($this->uid, $agent['rec_level'], 2);
            $withdraw_count2 = UserWithdrawalModel::where($where . " and uid in(" . $sql . ")")->sum("w_price");

            // 三级提现总额
            $sql = userLevelSql($this->uid, $agent['rec_level'], 3);
            $withdraw_count3 = UserWithdrawalModel::where($where . " and uid in(" . $sql . ")")->sum("w_price");

            $withdraw_count = $withdraw_count1 + $withdraw_count2 + $withdraw_count3;
            $data['withdraw_count'] = $withdraw_count;
            $data['withdraw_count1'] = $withdraw_count1;
            $data['withdraw_count2'] = $withdraw_count2;
            $data['withdraw_count3'] = $withdraw_count3;
            $ApiRedis->set($redis_key, $data, 60);
        } else {
            $data['cache'] = 1;
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    /*
     * 提现列表
     */
    public function cashlist_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $level = $this->param['level'] ?? 4;
        $isday = $this->param['isday'] ?? 0;
        $date = $this->param['date'] ?? "";
        if (!in_array($level, [1, 2, 3, 4])) {
            ajax_return("参数错误", 0);
        }
        $where = "status=3";
        if ($isday == 1) {
            $start_time = strtotime(date("Y-m-d") . " 00:00:00");
            $end_time = strtotime(date("Y-m-d") . " 23:59:59");
            $where .= " and update_time > " . $start_time . " and update_time < " . $end_time;
        } else {
            $time = undate($date);
            if (!empty($time)) {
                $where .= " and update_time > " . $time[0] . " and update_time < " . $time[1];
            }
        }
        $agent = $this->uinfo;
        if ($level == 4) {
            $sql = userLevelSql($this->uid, $agent['rec_level'], $level, 1);
        } else {
            $sql = userLevelSql($this->uid, $agent['rec_level'], $level);
        }
        $list = UserWithdrawalModel::where($where . " and uid in(" . $sql . ")")->limit((($pagenow - 1) * $limit), $limit)->field("id,uid,w_price,w_service,w_remarks,add_time")->order("id desc")->select();
        foreach ($list as $key => &$val) {
            $val['username'] = UserModel::where(['id' => $val['uid']])->value('username');
            $val['add_time'] = Util::i18n_datetime2str($val['add_time']);
            $val['update_time'] = Util::i18n_datetime2str($val['update_time']);
        }
        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }

    /*
     * 统计充值
     */
    public function statrecharge_run()
    {
        $ApiRedis = new ApiRedis();
        $isday = $this->param['isday'] ?? 0;
        $date = $this->param['date'] ?? "";
        $redis_key = md5("recharge-" . $isday . "-" . $date . "-" . $this->uid);
        $data = $ApiRedis->get($redis_key);
        if (empty($data)) {
            $where = "status in (3,4)";
            if ($isday == 1) {
                $start_time = strtotime(date("Y-m-d") . " 00:00:00");
                $end_time = strtotime(date("Y-m-d") . " 23:59:59");
                $where .= " and update_time > " . $start_time . " and update_time < " . $end_time;
            } else {
                $time = undate($date);
                if (!empty($time)) {
                    $where .= " and update_time > " . $time[0] . " and update_time < " . $time[1];
                }
            }
            $agent = $this->uinfo;

            // 一级充值总额
            $sql = userLevelSql($this->uid, $agent['rec_level'], 1);
            $recharge_count1 = UserOfflineModel::where($where . " and uid in(" . $sql . ")")->sum("arrival_price");

            // 二级充值总额
            $sql = userLevelSql($this->uid, $agent['rec_level'], 2);
            $recharge_count2 = UserOfflineModel::where($where . " and uid in(" . $sql . ")")->sum("arrival_price");

            // 三级充值总额
            $sql = userLevelSql($this->uid, $agent['rec_level'], 3);
            $recharge_count3 = UserOfflineModel::where($where . " and uid in(" . $sql . ")")->sum("arrival_price");

            $recharge_count = $recharge_count1 + $recharge_count2 + $recharge_count3;
            $data['recharge_count'] = $recharge_count;
            $data['recharge_count1'] = $recharge_count1;
            $data['recharge_count2'] = $recharge_count2;
            $data['recharge_count3'] = $recharge_count3;
            $ApiRedis->set($redis_key, $data, 60);
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    /*
     * 充值列表
     */
    public function rechargelist_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $level = $this->param['level'] ?? 4;
        $isday = $this->param['isday'] ?? 0;
        $date = $this->param['date'] ?? "";
        if (!in_array($level, [1, 2, 3, 4])) {
            ajax_return("参数错误", 0);
        }
        $where = "status in(3,4)";
        if ($isday == 1) {
            $start_time = strtotime(date("Y-m-d") . " 00:00:00");
            $end_time = strtotime(date("Y-m-d") . " 23:59:59");
            $where .= " and update_time > " . $start_time . " and update_time < " . $end_time;
        } else {
            $time = undate($date);
            if (!empty($time)) {
                $where .= " and update_time > " . $time[0] . " and update_time < " . $time[1];
            }
        }
        $agent = $this->uinfo;
        if ($level == 4) {
            $sql = userLevelSql($this->uid, $agent['rec_level'], $level, 1);
        } else {
            $sql = userLevelSql($this->uid, $agent['rec_level'], $level);
        }

        $list = UserOfflineModel::where($where . " and uid in(" . $sql . ")")->limit((($pagenow - 1) * $limit), $limit)->field("id,uid,apply_price,arrival_price,order_sn,remarks,add_time")->order("id desc")->select();
        foreach ($list as $key => &$val) {
            $val['username'] = UserModel::where(['id' => $val['uid']])->value('username');
            $val['add_time'] = Util::i18n_datetime2str($val['add_time']);
            $val['update_time'] = Util::i18n_datetime2str($val['update_time']);
        }
        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }

    /*
     * 统计注册
     */
    public function statreg_run()
    {
        $ApiRedis = new ApiRedis();
        $isday = $this->param['isday'] ?? 0;
        $date = $this->param['date'] ?? "";
        $redis_key = md5("reg-" . $isday . "-" . $date . "-" . $this->uid);
        $data = $ApiRedis->get($redis_key);
        if (empty($data)) {
            $where = "1=1";
            if ($isday == 1) {
                $start_time = strtotime(date("Y-m-d") . " 00:00:00");
                $end_time = strtotime(date("Y-m-d") . " 23:59:59");
                $where .= " and add_time > " . $start_time . " and add_time < " . $end_time;
            } else {
                $time = undate($date);
                if (!empty($time)) {
                    $where .= " and add_time > " . $time[0] . " and add_time < " . $time[1];
                }
            }

            $agent = $this->uinfo;

            $sql = userLevelSql($this->uid, $agent['rec_level'], 1);
            $reg_count1 = UserModel::where($where . " and id in(" . $sql . ")")->count();
            $sql = userLevelSql($this->uid, $agent['rec_level'], 2);
            $reg_count2 = UserModel::where($where . " and id in(" . $sql . ")")->count();
            $sql = userLevelSql($this->uid, $agent['rec_level'], 3);
            $reg_count3 = UserModel::where($where . " and id in(" . $sql . ")")->count();

            $reg_count = $reg_count1 + $reg_count2 + $reg_count3;
            $data['reg_count'] = $reg_count;
            $data['reg_count1'] = $reg_count1;
            $data['reg_count2'] = $reg_count2;
            $data['reg_count3'] = $reg_count3;
            $ApiRedis->set($redis_key, $data, 60);
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }


    public function profit_run()
    {
        $date1 = date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") - date("w"), date("Y")));
        $date2 = date("Y-m-d", mktime(23, 59, 59, date("m"), date("d") - date("w") + 6, date("Y")));
        $date1 = strtotime($date1 . " 00:00:00");
        $date2 = strtotime($date2 . " 23:59:59");
        $uid = $this->uid;

        $ApiRedis = new ApiRedis();
        $weekarray = array("日", "一", "二", "三", "四", "五", "六"); //先定义一个数组

        $list = [];
        for ($i = $date1; $i <= $date2; $i += 86400) {
            $start_time = $i;
            $end_time = $i + 86400 - 1;
            $is_curr = 0;

            if (date("Y-m-d", $start_time) == date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") - 1, date("Y")))) {
                $where = "uid=" . $uid . " and create_time>=" . $start_time . " and create_time <=" . $end_time;
                $redis_key = md5($where);
                $money = $ApiRedis->get($redis_key);
                if (empty($money)) {
                    $money = $date = AgentRakeBackModel::where($where)->sum("money");
                    $ApiRedis->set($redis_key, $money, 86400);
                }
                $is_curr = 1;
            } else {
                $money = "0.00";
            }

            $weeks = lang("星期" . $weekarray[date("w", $start_time)]);
            $list[date("d", $start_time)] = ['money' => $money, "weeks" => $weeks, "day" => date("d", $start_time), "is_curr" => $is_curr, 'date' => Util::i18n_date2str($start_time)];
        }

        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function report_run()
    {
        $date_type = $this->param['date_type'] ?? 0;
        $date = $this->param['date'] ?? '';
        $where = "uid=" . $this->uid;

        if ($date_type == 1) {
            $start_date = date("Y-m-d", mktime(0, 0, 0, date("m"), 1, date("Y")));
            $end_date = date("Y-m-d", mktime(23, 59, 59, date("m"), date("t"), date("Y")));
        } else if ($date_type == 2) {
            $start_date = date("Y-m-d", mktime(0, 0, 0, date("m") - 1, 1, date("Y")));
            $end_date = date("Y-m-d", mktime(23, 59, 59, date("m"), 0, date("Y")));
        } else if ($date_type == 3) {
            $date = explode("|", $date);
            if (!isset($date[0]) || !isset($date[1])) {
                ajax_return("日期格式错误", 0);
            }
            $start_date = $date[0];
            $end_date = $date[1];
        } else {
            $date = AgentRakeBackModel::where($where)->field("FROM_UNIXTIME(`create_time`, '%Y-%m') AS create_date")->group('create_date')->order('create_date asc')->select();
            if (count($date) == 0) { // 没有返佣记录时，历史记录按本月日期显示
                $start_date = date("Y-m-d", mktime(0, 0, 0, date("m"), 1, date("Y")));
                $end_date = date("Y-m-d", mktime(23, 59, 59, date("m"), date("t"), date("Y")));
            } else {
                $start_date = $date[0]['create_date'];
                $end_date = $date[count($date) - 1]['create_date'];
            }
        }
        $lists = [];
        $dates = getEmptyArr($start_date, $end_date, 'month');
        foreach ($dates as $key => $val) {
            $time = strtotime($val . "-01 00:00:00");
            $start_time = mktime(0, 0, 0, date("m", $time), 1, date("Y", $time));
            $end_time = mktime(23, 59, 59, date("m", $time), date("t", $time), date("Y", $time));
            if ($date_type == 0) {
                $lists[$val] = $this->getReportData($start_time, $end_time, $where);
            } else {
                $lists = $this->getReportData($start_time, $end_time, $where);
            }
        }
        $data['lists'] = $lists;
//        $data['user_count'] = UserModel::where("status=1 and rec_id = ".$this->uid)->count();
        //修改为直属下级有效充值数量
        $data['user_count'] = UserFactory::getUserInvitePerson($this->uid);
        $data['extension'] = AgentSellLogModel::where("status=3 and uid=" . $this->uid)->sum('money');
        // 已结算代理返佣总计
        $data['agent_total_amount'] = Db::name('third_stats_fyfy')->where('sff_uid', $this->uid)->where('sff_payed', 2)->sum('sff_fy');
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    private function getReportData($start_time, $end_time, $where = '1=1')
    {
        $where .= " and create_time > " . $start_time . " and create_time < " . $end_time;
        $agent = $this->uinfo;
        $sql = userLevelSql($this->uid, $agent['rec_level'], 1);
        $data = [];
        /* 一级 */
        $profit = AgentRakeBackModel::where($where . " and bottom_uid in(" . $sql . ")")->field("SUM(IF(money>0,money,0)) as back_price,SUM(IF(status in (3,4),money,0)) as valid_back_price")->find();
        $people = UserModel::where("add_time < " . $end_time . " and id in (" . $sql . ")")->count();

        $data[1] = [
            'people' => $people,
            'effective' => empty($profit['back_price']) ? 0 : $profit['back_price'],
            'actual' => empty($profit['valid_back_price']) ? 0 : $profit['valid_back_price'],
        ];

        /* 二级 */
        $sql = userLevelSql($this->uid, $agent['rec_level'], 2);
        $profit = AgentRakeBackModel::where($where . " and bottom_uid in(" . $sql . ")")->field("SUM(IF(money>0,money,0)) as back_price,SUM(IF(status in (3,4),money,0)) as valid_back_price")->find();
        $people = UserModel::where("add_time < " . $end_time . " and id in (" . $sql . ")")->count();
        $data[2] = [
            'people' => $people,
            'effective' => empty($profit['back_price']) ? 0 : $profit['back_price'],
            'actual' => empty($profit['valid_back_price']) ? 0 : $profit['valid_back_price'],
        ];

        /* 三级 */
        $sql = userLevelSql($this->uid, $agent['rec_level'], 3);
        $profit = AgentRakeBackModel::where($where . " and bottom_uid in(" . $sql . ")")->field("SUM(IF(money>0,money,0)) as back_price,SUM(IF(status in (3,4),money,0)) as valid_back_price")->find();
        $people = UserModel::where("add_time < " . $end_time . " and id in (" . $sql . ")")->count();
        $data[3] = [
            'people' => $people,
            'effective' => empty($profit['back_price']) ? 0 : $profit['back_price'],
            'actual' => empty($profit['valid_back_price']) ? 0 : $profit['valid_back_price'],
        ];

        return $data;
    }

    //明细
    public function userdetail_run()
    {
        $uid = $this->param['uid'] ?? 0;
        $date = $this->param['date'] ?? "";

        $user = UserModel::where("id=" . $uid)->field("id,username,status,balance,head_img,add_time")->find();
        if (empty($user)) {
            ajax_return("数据不存在", 0);
        }
        $where = "uid=" . $user['id'];
        if (!empty($date)) {
            $time = undate($date);
            if (!empty($time)) {
                $where .= " and add_time > " . $time[0] . " and add_time < " . $time[1];
            }
        } else { // 默认本月数据
            $start_time = mktime(0, 0, 0, date("m"), 1, date("Y"));
            $end_time = mktime(23, 59, 59, date("m"), date("t"), date("Y"));
            $where .= " and add_time > " . $start_time . " and add_time < " . $end_time;
        }

        $user['add_time'] = Util::i18n_datetime2str($user['add_time']);
        $user['recharge'] = UserOfflineModel::where($where)
            ->where("status in (3,4)")
            ->field("count(*) as number,sum(arrival_price) as price")
            ->find();//充值

        $user['withdrawal'] = UserWithdrawalModel::where($where)
            ->where("status in (3,4)")
            ->field("count(*) as number,sum(w_price) as price")
            ->find();//提现


        //默认0
        $user['recharge']['price'] = $user['recharge']['price'] ? $user['recharge']['price'] : 0;
        $user['withdrawal']['price'] = $user['withdrawal']['price'] ? $user['withdrawal']['price'] : 0;
        $len = mb_strlen($user['username']);
        $str1 = mb_substr($user['username'], 0, 2);
        $str2 = mb_substr($user['username'], $len - 2, 2);
        $user['username'] = $str1 . '****' . $str2;
        $rsps['data'] = $user;
        $rsps['code'] = 1;
        return $rsps;
    }

    //代理申请
    public function apply_run()
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $UserAgentModel = new UserAgentModel();
        $data = UserAgentModel::where("uid", $this->uid)->find();
        if (!empty($data)) {
            if ($data['status'] == 2) {
                $ret = UserAgentModel::where(['uid' => $this->uid])->save(['status' => 1]);
            } else {
                ajax_return("请勿重复申请", 0);
            }
        } else {
            $user = $this->uinfo;

            $add['uid'] = $this->uid;
            $add['apply_ip'] = Util::get_ip();
            $add['apply_time'] = time();
            $add['tree'] = $user['tree'];
            $add['rec_id'] = $user['rec_id'];
            if ($sys['agent_apply_verify'] == 1) {
                $add['status'] = 3;
            }
            $agent_recharge_money = 0;
            if ($sys['agent_recharge'] > 0) {
                $agent_recharge_money = UserOfflineModel::where([['uid', '=', $this->uid], ['status', 'in', '3,4']])->sum('arrival_price');
            }
            $agent_water_money = 0;

            if ($agent_recharge_money < $sys['agent_recharge'] || $agent_water_money < $sys['agent_water']) {
                ajax_return("代理条件未达成，无法申请", 0);
            }
            $ret = UserAgentModel::insert($add);
        }
        if ($ret) {
            $rsps['code'] = 1;
        } else {
            $rsps['code'] = 0;
        }
        return $rsps;
    }

    //代理申请
    public function apply_status_run()
    {
        $data = UserAgentModel::where("uid", $this->uid)->find();
        if (!isset($data['id'])) {
            $data['status'] = 0;//未申请
        }

        $rsps['data']['status'] = $data['status'];
        $rsps['code'] = 1;
        return $rsps;
    }

    //发放记录
    public function log_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $date = $this->param['date'] ?? "";
        $is_stat = $this->param['is_stat'] ?? 0;
        $where = "1=1";
        $time = undate($date);
        if (!empty($time)) {
            $where .= " and create_time > " . $time[0] . " and create_time < " . $time[1];
        }

        $data['data'] = AgentRakeBackModel::where("uid", $this->uid)
            ->field("create_time,money,remark,status,(select username from dq_user where id=bottom_uid limit 1) as username")
            ->where($where)
            ->limit((($pagenow - 1) * $limit), $limit)
            ->order("create_time desc")
            ->select();

        if ($is_stat == 1) {
            $data['sum'] = AgentRakeBackModel::where("uid", $this->uid)->where($where)->sum('money');
            $data['settled'] = AgentRakeBackModel::where("uid", $this->uid)->where('status', 'in', '3,4')->where($where)->sum('money');
            $data['no_settled'] = AgentRakeBackModel::where("uid", $this->uid)->where(['status' => 1])->where($where)->sum('money');
            $data['count'] = AgentRakeBackModel::where("uid", $this->uid)->where($where)->count('money');
        }
        foreach ($data['data'] as &$value) {
            $value['create_time'] = Util::i18n_datetime2str($value['create_time']);
            $len = mb_strlen($value['username']);
            $str1 = mb_substr($value['username'], 0, 2);
            $str2 = mb_substr($value['username'], $len - 2, 2);
            $value['username'] = $str1 . '****' . $str2;
        }

        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //推广奖励
    public function popularize_run()
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $agent = $this->uinfo;

        $people = 0;
        if ($sys['tg_condition'] > 0) {
            $sql = UserModel::where([['rec_id', "=", $this->uid]])->field("id")->buildSql();
            $recU = UserModel::alias("a")->join("dq_user_offline b", "a.id=b.uid")->field('a.id,sum(arrival_price) as sum')->where("a.id in (" . $sql . ")")->where("b.status in(3,4)")->group('b.uid')->select();
            foreach ($recU as $v) {
                if ($v['sum'] >= $sys['tg_condition']) {
                    $people++;
                }
            }
        } else {
            $recU = UserModel::alias("a")->field('a.id')->where(["a.rec_id" => $this->uid])->select();
            foreach ($recU as $v) {
                $people++;
            }
        }

        $list = AgentSettingModel::select();
        $AgentSellLogModel = new AgentSellLogModel();
        $finish = [];
        $not_finish = [];
        $data['people'] = 0;
        $data['task_volume'] = 0;//任务量
        $data['percentage'] = 0;// 任务进度比例
        if (!empty($list)) {
            $data['people'] = $people;//已推广人数
            foreach ($list as $key => $value) {
                $list[$key]['is_standard'] = 2;//未达标
                if ($people >= $list[$key]['stratum_number']) {
                    $list[$key]['is_standard'] = 1;//已达标
                }
                $sell = $AgentSellLogModel->where("tg_set_id", $value['id'])->where("uid", $this->uid)->find();//查看是否完成阶段任务
                if (isset($sell['id'])) {
                    $finish[] = $value;
                    $data['task_volume'] = isset($list[$key + 1]) ? $list[$key + 1]['stratum_number'] : $data['people'];
                    $list[$key]['is_play'] = 2;//已领取
                } else {
                    $not_finish[] = $value;
                    $list[$key]['is_play'] = 1;//未领取
                }
            }
            if (empty($finish)) {
                $data['task_volume'] = $list[0]['stratum_number'];
            }
            if ($data['people'] > 0) {
                $data['percentage'] = $data['task_volume'] > 0 ? round($data['people'] / $data['task_volume'] * 100, 2) : 0;
                $data['percentage'] = $data['percentage'] > 100 ? 100 : $data['percentage'];
            }
        }
        $data['not_finish'] = $not_finish;
        $data['finish'] = $finish;
        $data['list'] = $list;

        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function getRechargeRebate()
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();

        $people = UserFactory::getUserInvitePerson($this->uid);

        //推荐总数
        $data['people_num'] = $people;
        //推广奖励
        $condition = [];
        $condition['uid'] = $this->uid;
        $condition['status'] = ['!=', 2];
        $data['invite_rebate'] = AgentSellLogModel::where($condition)->sum('money');
        //总收入(返佣+返水+推广)
        #1、返佣
        $condition = [];
        $condition['uid'] = $this->uid;
        $condition['status'] = ['!=', 2];#2为拒绝
        $inviteAmount = AgentRakeBackModel::where($condition)->sum('money');
        #2、返水
        $condition = [];
        $condition['sb_uid'] = $this->uid;
        $condition['sb_payed'] = ['!=', 0];#0为无效返水
        $backWaterAmount = Db::name('third_stats_back')->where($condition)->sum('sb_backmoney');
        $data['total_rebate_amount'] = round($inviteAmount + $backWaterAmount + $data['invite_rebate'], 4);
        //获取邀请链接
        $data['rec_code'] = $this->uinfo['rec_code'];
        $data['invite_url'] = MemberService::createShareUrl($this->uinfo['rec_code']);
        //获取邀请充值用户对应奖励列表
        $data['invite_recharge_rebate_list'] = AgentSettingModel::select();
        //上级邀请码
        $data['parent_rec_code'] = '';
        if ($this->uinfo['rec_id']) {
            $data['parent_rec_code'] = UserModel::where('id', $this->uinfo['rec_id'])->value('rec_code');
        }
        return ['data' => $data, 'code' => 1];
    }

    //获取团队报酬列表
    public function getRemuneration()
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        //增加代理条件
        $agentWhere = AgentSettingService::getAgentTypeSql($this->uid);
        //获取邀请充值用户对应奖励列表
        $rebateList = AgentSettingModel::where($agentWhere)->select()->toArray();
        $AgentSellLogModel = new AgentSellLogModel();
        $people = 0;
        foreach ($rebateList as $k => $v) {
            $people = UserFactory::getUserInvitePerson($this->uid, $v['start_time']);
//        $people = AgentService::getAgentSellUsers($this->uid);
            //判断是否达成条件
            $rebateList[$k]['is_finish'] = 0;
            $rebateList[$k]['is_receive'] = 0;
            //判断是否可以领取推广奖励
            if ($people >= $v['stratum_number']) {
                $rebateList[$k]['is_finish'] = 1;
            }
            //判断是否需要后台审核
            if ($v['is_check'] == 1) {
                //待审核 TODO 后面修改后台审核用户可以在前端申请，然后后台派发
//                $rebateList[$k]['is_receive'] = 2;
            }
            //判断是否有领取记录
            $_sell = $AgentSellLogModel->where("tg_set_id", $v['id'])->where("uid", $this->uid)->find();//查看是否完成阶段任务
            if ($_sell) {
                if ($v['reward_type'] == AgentSettingModel::REWARD_TYPE_TOTAL) {
                    if (in_array($_sell->status, [2, 3])) {
                        //完成后判断是否领取(拒绝和派发都算已领取,2为拒绝，3为派发)
                        $rebateList[$k]['is_receive'] = 1;
                    }
                } elseif ($v['reward_type'] == AgentSettingModel::REWARD_TYPE_ONE) {
                    //判断类型为单次奖励时验证是否领取完
                    if (!$this->getReceivePeopleNum($this->uid, $v, $people)) {
                        $rebateList[$k]['is_receive'] = 1;
                    }
                }

            }

        }
        //获取当前货币符号
        $currentSymbol = Util::i18n_currency_no_amount();
        return [
            'data' => [
                'total_num' => $people,
                'current_symbol' => $currentSymbol,
                'list' => $rebateList,
                'valid_recharge' => isset($sys['tg_condition']) ? $sys['tg_condition'] ?: 0 : 0,
                'valid_bet' => isset($sys['tg_condition_valid_bet']) ? $sys['tg_condition_valid_bet'] ?: 0 : 0,
            ],
            'code' => 1
        ];
    }

    //领取代理奖励
    public function receiveInviteRebate()
    {
        $id = $this->param['id'] ?? 0;
        if ($id <= 0) ajax_return("参数错误", 0);
        $CommomRedis = new CommomRedis();
        if (!$CommomRedis->lock('selllog_' . $id)) {
//            ajax_return("请勿频繁访问", 0);
        }
        // 判断是否禁止参与活动
        if (MemberService::tagBan($this->uid, 'is_reward')) {
            ajax_return("禁止参与活动，请联系客服", 0);
        }

        //获取领取的奖励数据
        //增加代理条件
        $agentWhere = AgentSettingService::getAgentTypeSql($this->uid);
        $agentSettingDetail = AgentSettingModel::where('id', '=', $id)->where($agentWhere)->find();
        if (!$agentSettingDetail) ajax_return('该活动已结束');

        //获取用户邀请人数
        $people = UserFactory::getUserInvitePerson($this->uid, $agentSettingDetail->start_time);
//        $people = AgentService::getAgentSellUsers($this->uid);


        //判断是否可以领取推广奖励
        if ($people < $agentSettingDetail['stratum_number']) {
            ajax_return("任务未完成", 0);
        }
        //判断代理线
        if (!empty($agentSettingDetail->agent_id)) {
            //获取用户数据
            $userDetail = UserModel::uinfo($this->uid);
            $tree = explode("/", $userDetail->tree);
            if (!isset($tree[1]) || $tree[1] != $agentSettingDetail->agent_id) {
                ajax_return('当前账号代理线条件不满足领取条件');
            }
        }
        //获取用户代理数据
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
        $condition[] = ['status', '=', 3];
        $userAgentDetail = UserAgentModel::where($condition)->find();
        //判断领取代理类型
        if ($agentSettingDetail->receive_agent_type == 1 && $userAgentDetail->type != 1) {
            //内部代理判断
            return ajax_return('该活动仅限内部代理参与');
        } elseif ($agentSettingDetail->receive_agent_type == 2 && $userAgentDetail->type != 0) {
            return ajax_return('该活动仅限外部代理参与');
        }

        $AgentModel = new AgentSellLogModel();
        //判断是否是重复领取的奖励
        $receiveNum = 1;
        if ($agentSettingDetail->reward_type == AgentSettingModel::REWARD_TYPE_ONE) {
            $receiveNum = $this->getReceivePeopleNum($this->uid, $agentSettingDetail, $people);
            //判断是否能领取
            if ($receiveNum <= 0) {
                ajax_return('对应的人数奖励已领取，请完成条件后再来');
            }
            $data = false;
        } else {
            $agent_where = [];
            $agent_where[] = ['tg_set_id', '=', $id];
            $agent_where[] = ['uid', '=', $this->uid];
            $data = $AgentModel->where($agent_where)->find();
        }
        if (!$data) {
            //没有记录生成一条记录
            $data = [];
            $data['uid'] = $this->uid;
            $data['tg_set_id'] = $id;
            $data['money'] = $receiveNum * $agentSettingDetail['stratum_bonus'];
            $data['remark'] = "推广奖励";
            $data['received_people_num'] = $receiveNum;
            $data['status'] = 1;
            $data['create_time'] = time();
            $_id = AgentSellLogModel::insertGetId($data);
            $data['id'] = $_id;
        }

        $amount = $data['money'];

        if ($data['status'] != 1) ajax_return("已领取，请勿重复领取", 0);


        //判断该奖励是否需要后台审核
        if ($agentSettingDetail->is_check == 1) {
            return ajax_return('已申请，请耐心等待后台审核');
        }

        //判断状态是否未处理
        Db::startTrans();
        try {
            //先修改状态
            $save = [];
//            if(
//                ($agentSettingDetail->reward_type == AgentSettingModel::REWARD_TYPE_ONE &&
//                isset($nextAgentSettingDetail) &&
//                $people == $nextAgentSettingDetail->stratum_number - 1) ||
//                $agentSettingDetail->reward_type == AgentSettingModel::REWARD_TYPE_TOTAL
//            ){
            //判断单次奖励时人数等于下阶段要求人数减1或总数奖励时才能更改为完成状态
            $save['status'] = 3;
//                AgentSellLogModel::where('id', '=', $data['id'])->update($save);
//            }
            AgentSellLogModel::where('id', '=', $data['id'])->update($save);

            //修改余额
            $UserModel = new UserModel();
            $UserModel->cz_balance($data['uid'], $amount, '+', true, '推广奖励', 7, $agentSettingDetail['savings'], 1, $data['id'], true, 101);
            // 提交事务
            Db::commit();
            return ['msg' => '领取成功', 'code' => 1];

        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            ajax_return($e->getMessage(), 0);
        }
    }

    //获取可领取的人数
    public function getReceivePeopleNum($uid, $agentSettingDetail, $people)
    {
        if (!$agentSettingDetail) {
            return 0;
        }
        //查找该用户上个记录领取的人数
        $condition = [];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['tg_set_id', '=', $agentSettingDetail['id']];
        $receiveNum = AgentSellLogModel::where($condition)->sum('received_people_num');
        //获取下阶段奖励的要求人数
        $condition = [];
        $condition[] = ['stratum_number', '>', $agentSettingDetail['stratum_number']];
        $nextAgentSettingDetail = AgentSettingModel::where($condition)->order('stratum_number asc')->find();

        if ($nextAgentSettingDetail) {
            //有下阶段的的奖励将用户完成人数和下阶段要求人数取小，那本人数要求为不包含，上面判断完成没有包含，所有这里需要没有包含
            $people = min($nextAgentSettingDetail['stratum_number'] - 1, $people);
        } else {
            //减去本阶段的
            $people = $people - $agentSettingDetail['stratum_number'] + 1;
        }
        if ($receiveNum) {
            $people = $people - $receiveNum;
        }
        return $people;
    }

    public function judgeOneRewardType($agentSettingDetail, $agentSellLog, $people)
    {
        //取本阶段用户要求或已领取的人数
        $receiveRewardPeopleNum = $people - ($agentSettingDetail['stratum_number'] - 1) - $agentSellLog['received_people_num'];
        if ($receiveRewardPeopleNum <= 0) {
            return false;
        }
        //获取下阶段奖励的要求人数
        $condition = [];
        $condition[] = ['stratum_number', '>', $agentSettingDetail['stratum_number']];
        $nextAgentSettingDetail = AgentSettingModel::where($condition)->order('stratum_number asc')->find();
        if ($nextAgentSettingDetail) {
            //有下阶段的的奖励将用户完成人数和下阶段要求人数取小，那本人数要求为不包含，上面判断完成没有包含，所有这里需要没有包含
            $receiveRewardPeopleNum = min($nextAgentSettingDetail->stratum_number - 1, $receiveRewardPeopleNum);
        }
        //可领取奖励的人数
        if ($receiveRewardPeopleNum < 1) {
            return false;
        }
        //计算人数对应的金额奖励
        $amount = round($receiveRewardPeopleNum * $agentSellLog['money'], 2);
        return ['amount' => $amount, 'receive_reward_people_num' => $receiveRewardPeopleNum, 'next_agent_setting_detail' => $nextAgentSettingDetail];
    }

    //获取代理奖励排行榜和记录
    public function getSellRankingRecord()
    {
        $params = $this->param;
        $page = isset($params['page']) ?? 1;
        $limit = isset($params['limit']) ?? 5;
        //获取前三名排行榜
        $condition = [];
        $condition[] = ['asl.status', '=', 3];
//        $condition[] = ['asl.uid', '=', 999];
        $field = 'u.username,u.head_img,sum(asl.money) as money';
        $rankingList = AgentSellLogModel::alias('asl')
            ->join('user u', 'u.id=asl.uid')
            ->where($condition)->group('asl.uid')
            ->field($field)->order('money desc')
            ->limit(3)->select();
        foreach ($rankingList as $v) {
            $v['username'] = Util::formatUsername($v['username']);
        }
        //获取记录
        $field = 'asl.money,u.username';
        $recordList = AgentSellLogModel::alias('asl')
            ->join('user u', 'u.id=asl.uid')
            ->where([['asl.status', '=', 3]])
            ->field($field)->order('asl.id desc')
            ->page($page, $limit)->select();
        foreach ($recordList as $v) {
            $v['username'] = Util::formatUsername($v['username']);
        }
        return ['data' => ['ranking_list' => $rankingList, 'record_list' => $recordList], 'code' => 1];
    }

    //推广码
    public function promotion_run()
    {
        $type = $this->param['type'] ?? 0;
        $sys = (new ApiRedis())->systemInfo();
        $rec_code = UserModel::where("id", $this->uid)->value('rec_code');//查看状态
        if ($type == 1) {
            $data['rec_code'] = $rec_code;
            $data['rec_url'] = MemberService::createShareUrl($this->uinfo['rec_code']);
            $data['qrcode_url'] = $sys['domain'] . "/team/qrcode?type=1&share_code=" . $data['rec_code'];
        } else {
            $data['rec_code'] = $rec_code;
            $data['rec_url'] = MemberService::createShareUrl($data['rec_code']);
            // . "&modal=LoginRegistration&data=tab-1"
            $data['qrcode_url'] = $sys['domain'] . "/team/qrcode?share_code=" . $data['rec_code'];
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //返回二维码不登录
    public function qrcode_run()
    {
        $share_code = $this->param['share_code'];
        $type = $this->param['type'] ?? 0;
        $sys = (new ApiRedis())->systemInfo();
        $object = new QRcode();

        //返回下载页二维码
        if ($type == 2) {
            $url = getAppUrl() . "/#/pages/ucenter/doanload";
            $errorCorrectionLevel = intval(3);//容错级别
            $matrixPointSize = intval(4);//生成图片大小
            return $object->png($url, false, $errorCorrectionLevel, $matrixPointSize, 2);
        }
        //返回下载页二维码结束

        $user = UserModel::where("rec_code", $share_code)->field("rec_code")->find();//查看状态
        if (empty($user)) {
            ajax_return("推荐码不存在", 0);
        }
        if ($type == 1) {
            $url = MemberService::createShareUrl($user['rec_code']);//网址或者是文本内容
        } else {
            // 注册
//            $url=getAppUrl()."/#/?share_code=".$user['rec_code'] . "&modal=LoginRegistration&data=tab-1";//网址或者是文本内容
            $url = MemberService::createShareUrl($user['rec_code']);//网址或者是文本内容
        }

        $errorCorrectionLevel = intval(3);//容错级别
        $matrixPointSize = intval(4);//生成图片大小
        return $object->png($url, false, $errorCorrectionLevel, $matrixPointSize, 2);
    }

    //推广二维码
    public function share_qrcode_run()
    {
        $sys = (new ApiRedis())->systemInfo();
        $user = UserModel::where("id", $this->uid)->field("rec_code")->find();//查看状态
        $object = new QRcode();
        $url = MemberService::createShareUrl($user['rec_code']);//网址或者是文本内容
        $errorCorrectionLevel = intval(3);//容错级别
        $matrixPointSize = intval(4);//生成图片大小
        return $object->png($url, false, $errorCorrectionLevel, $matrixPointSize, 2);
    }

    //团队介绍
    public function teamView()
    {
        $statistics = [];
        //获取邀请链接
        $statistics['rec_code'] = $this->uinfo['rec_code'];
        $statistics['invite_url'] = MemberService::createShareUrl($this->uinfo['rec_code']);
        #获取外部代理三级分佣
        $withoutRate = env('FYFY_BILLING_LEVEL_OUTSIDE_RATE', '');
        $withoutRateArr = explode(',', $withoutRate);
        $statistics['without_rate'] = $withoutRateArr;
        #获取推广佣金，查询设置第一个
        $agentSellLogDetail = AgentSettingModel::where('start_time', '<=', time())->order('id asc')->find();
        $statistics['invite_bonus'] = 0;
        if ($agentSellLogDetail) {
            $statistics['invite_bonus'] = Util::i18n_currency($agentSellLogDetail['stratum_bonus']);
        }
        $userDetail = UserModel::where('id', '=', $this->uid)->find();
        $sql = UserModel::where('tree', 'like', $userDetail->tree . '%')->field('id')->buildSql();
        $userSqlWhere = 'id in(' . $sql . ')';
        //获取团队人数
        $teamNumStatistics = [];
        $teamNumStatistics['total'] = UserModel::where($userSqlWhere)->count();#总数
        $teamNumStatistics['child'] = UserModel::where('rec_id', '=', $this->uid)->count();#直属
        $teamNumStatistics['other'] = $teamNumStatistics['total'] - $teamNumStatistics['child'];#其他
        $statistics['team_people'] = $teamNumStatistics;

        //团队统计
        $startTime = 0;
        $startDate = 0;
        if (isset($this->param['start_time']) && $this->param['start_time'] !== '') {
            $startTime = strtotime($this->param['start_time']);
            $startDate = str_replace('-', '', $this->param['start_time']);
        }
        $endTime = 4849920240;
        $endDate = 22220101;
        if (isset($this->param['end_time']) && $this->param['end_time'] !== '') {
            $endDate = str_replace('-', '', $this->param['end_time']);
            $endTime = strtotime($this->param['end_time'] . '  23:59:59');
        }
        $teamStatistics = [];
        #首次存款直接会员总数
        $condition = [];
        $condition[] = ['u.rec_id', '=', $this->uid];
        $condition[] = ['uo.first', '=', 1];
        $condition[] = ['uo.status', 'in', '3,4'];
        $condition[] = ['uo.add_time', 'between', [$startTime, $endTime]];
        $teamStatistics['first_deposit_child_user'] = UserModel::alias('u')
            ->join('dq_user_offline uo', 'u.id=uo.uid')
            ->group('uo.uid')
            ->where($condition)->count();
        #直接存款会员总数
        $condition = [];
        $condition[] = ['u.rec_id', '=', $this->uid];
        $condition[] = ['uo.status', 'in', '3,4'];
        $condition[] = ['uo.add_time', 'between', [$startTime, $endTime]];
        $teamStatistics['deposit_child_user'] = UserModel::alias('u')
            ->join('dq_user_offline uo', 'u.id=uo.uid')
            ->group('uo.uid')
            ->where($condition)->count();
        #直接会员投注总额
        $condition = [];
        $condition[] = ['u.rec_id', '=', $this->uid];
        $condition[] = ['tsg.su_dt', 'between', [$startDate, $endDate]];
        $table = \app\common\service\BetService::getThirdStatsTable();
        $teamStatistics['bet_child_user_amount'] = Util::i18n_currency(UserModel::alias('u')
            ->join($table['stats_global'] . ' tsg', 'tsg.su_uid=u.id')
            ->where($condition)->sum('tsg.su_bet') ?: 0);
        #团队投注总额
        $teamStatistics['bet_total_amount'] = Util::i18n_currency(Db::name($table['_stats_global'])->where("su_uid in($sql)")
            ->where('su_dt', 'between', [$startDate, $endDate])
            ->sum("su_bet") ?: 0);
        #团队存款总额
        $teamStatistics['recharge_total_amount'] = Util::i18n_currency(UserOfflineModel::where("uid in ({$sql})")
            ->where('add_time', 'between', [$startTime, $endTime])
            ->where('status', 'in', '3,4')->sum('arrival_price') ?: 0);
        #N1级存款总人数
        $_sql = UserModel::where('rec_id', '=', $userDetail->id)->field('id')->buildSql();
        $teamStatistics['child_recharge_total_people'] = UserOfflineModel::where("uid in ({$_sql})")
            ->where('add_time', 'between', [$startTime, $endTime])
            ->where('status', 'in', '3,4')->group('uid')->count();
        #N1级一下其他存款总人数
        //当前等级往下推两级的所有人
        $_sql = UserModel::where('tree', 'like', $userDetail->tree . '%')
            ->where('rec_level', '>', $userDetail->rec_level + 1)
            ->field('id')->buildSql();
        $teamStatistics['child_other_recharge_total_people'] = UserOfflineModel::where("uid in ({$_sql})")
            ->where('add_time', 'between', [$startTime, $endTime])
            ->where('status', 'in', '3,4')->group('uid')->count();

        $statistics['team_statistics'] = $teamStatistics;
        //佣金
        $commissionArr = [];
        #今日佣金
        $condition = [];
        $condition[] = ['sff_w', '=', Carbon::now()->format('Ymd')];
        $condition[] = ['sff_uid', '=', $this->uid];
        $condition[] = ['sff_payed', '=', 2];
        $todayCommission = Db::name('third_stats_fyfy')->where($condition)->sum('sff_fy') ?: 0;
        $commissionArr['today'] = Util::i18n_currency($todayCommission);
        #昨日佣金
        $condition = [];
        $condition[] = ['sff_w', '=', Carbon::yesterday()->format('Ymd')];
        $condition[] = ['sff_uid', '=', $this->uid];
        $condition[] = ['sff_payed', '=', 2];
        $yesterdayCommission = Db::name('third_stats_fyfy')->where($condition)->sum('sff_fy') ?: 0;
        $commissionArr['yesterday'] = Util::i18n_currency($yesterdayCommission);
        #所有佣金
        $condition = [];
        $condition[] = ['sff_uid', '=', $this->uid];
        $condition[] = ['sff_payed', '=', 2];
        $totalCommission = Db::name('third_stats_fyfy')->where($condition)->sum('sff_fy') ?: 0;
        $commissionArr['total'] = Util::i18n_currency($totalCommission);
        $statistics['commission'] = $commissionArr;
        //推荐奖励
        $referralRewardArr = [];
        #今日推荐奖励
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
        $condition[] = ['create_time', 'between', [Carbon::now()->startOfDay()->timestamp, Carbon::now()->endOfDay()->timestamp]];
        $todayRewards = AgentSellLogModel::where($condition)->sum('money') ?: 0;
        $referralRewardArr['today'] = Util::i18n_currency($todayRewards);
        #昨天推荐奖励
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
        $condition[] = ['create_time', 'between', [Carbon::yesterday()->startOfDay()->timestamp, Carbon::yesterday()->endOfDay()->timestamp]];
        $yesterdayRewards = AgentSellLogModel::where($condition)->sum('money') ?: 0;
        $referralRewardArr['yesterday'] = Util::i18n_currency($yesterdayRewards);
        #总推荐奖励
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
        $totalRewards = AgentSellLogModel::where($condition)->sum('money') ?: 0;
        $referralRewardArr['total'] = Util::i18n_currency($totalRewards);
        $statistics['referral_reward'] = $referralRewardArr;
        //总收益
        $totalEarningsArr = [];
        #今天所有收益
        $totalEarningsArr['today'] = Util::i18n_currency($todayRewards + $todayCommission);
        #昨天所有收益
        $totalEarningsArr['yesterday'] = Util::i18n_currency($yesterdayRewards + $yesterdayCommission);
        #总奖金
        $totalEarningsArr['total'] = Util::i18n_currency($totalRewards + $totalCommission);
        $statistics['total_earnings'] = $totalEarningsArr;
        $rsps['data'] = $statistics;
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 佣金列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function commissionList()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $startTime = time() - (3600 * 24 * 7);
        $endTime = time();
        if (isset($this->param['start_time']) && $this->param['start_time'] !== '') {
            $startTime = strtotime($this->param['start_time']);
        }
        if (isset($this->param['end_time']) && $this->param['end_time'] !== '') {
            $endTime = strtotime($this->param['end_time']);
        }
        //指定日期团队收益
        $condition = [];
        $condition[] = ['sff_statstime', 'between', [$startTime, $endTime]];
        $condition[] = ['sff_uid', '=', $this->uid];
        $condition[] = ['sff_payed', '=', 2];
        $team = Db::name('third_stats_fyfy')->where($condition)->field('sff_uid,sff_bet,sff_fy,sff_statstime,sff_utype')->limit((($page - 1) * $limit), $limit)->select();
        $team_arr = [];
        foreach ($team as $key => $value) {
            $value['time'] = date("Y-m-d H:i:s", $value['sff_statstime']);
            $team_arr[] = $value;
        }
        $rsps['data'] = $team_arr;
        $rsps['total'] = Db::name('third_stats_fyfy')->where($condition)->count();
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 反水列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function backList()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $order = $this->param['order'] ?? '';
        $sort = $this->param['sort'] ?? 'DESC';
        if (empty($order)) {
            $order = "sb_id DESC";
        } else {
            $order = $order . " " . $sort;
        }
        $startTime = date("Ymd", time() - (3600 * 24 * 7));
        $endTime = date("Ymd");
        if (isset($this->param['start_time']) && $this->param['start_time'] !== '') {
            $startTime = date('Ymd', strtotime($this->param['start_time']));
        }
        if (isset($this->param['end_time']) && $this->param['end_time'] !== '') {
            $endTime = date("Ymd", strtotime($this->param['end_time']));
        }
        //指定日期团队收益
        $condition = [];
        $condition[] = ['sb_dt', 'between', [$startTime, $endTime]];
        $condition[] = ['sb_uid', '=', $this->uid];
        $team = Db::name('third_stats_back')->where($condition)->field('sb_dt,sb_uid,sb_backbase,sb_backmoney')->order($order)->limit((($page - 1) * $limit), $limit)->select();
        $team_arr = [];
        foreach ($team as $key => $value) {
            $value['time'] = date("Y-m-d", strtotime($value['sb_dt']));
            $team_arr[] = $value;
        }
        $rsps['data'] = $team_arr;
        $rsps['total'] = Db::name('third_stats_back')->where($condition)->count();
        $rsps['code'] = 1;
        return $rsps;
    }

    //注单列表
    public function orderlist_run()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $isday = $this->param['isday'] ?? 0;
        $date = $this->param['date'] ?? '';
        $level = $this->param['level'] ?? 4;
        $settlement = $this->param['settlement'] ?? 0;
        $dateData = explode('|', $date);
        $condition = [];
        if (count($dateData) == 2 && $isday != 1 && $dateData[0] != '' && $dateData[1] != '') {
            $startTime = strtotime($dateData[0] . ' 00:00:00');
            $endTime = strtotime($dateData[1] . ' 23:59:59');
            $condition[] = ['a.gb_intime', 'between', [$startTime, $endTime]];
        }
        if (in_array($level, [1, 2, 3])) {
            $condition[] = ['b.rec_level', '=', $this->uinfo['rec_level'] + 1];
        }
        if ($isday == 1) {
            $condition[] = ['a.gb_intime', 'between', [strtotime('today'), strtotime('today 23:59:59')]];
        }
        if ($settlement == 1) {
            //已结算
            $condition[] = ['a.gb_winlose', 'in', '2,3,4'];
        } elseif ($settlement == 2) {
            //未结算
            $condition[] = ['a.gb_winlose', '=', '1'];
        }
        $condition[] = ['a.gb_uid', '=', $this->uid];
        $field = 'a.gb_uid as uid,a.gb_intime as add_time,a.gb_sn as zd_number,a.gb_price as tz_price,a.gb_otherwin as sy_res,a.gb_winlose as status,b.username,b.head_img as avatar';
        $data = Db::name('game_bets')->alias('a')
            ->join('user b', 'a.gb_uid=b.id')
            ->where($condition)
            ->field($field)
            ->page($page, $limit)
            ->select()->toArray();
        foreach ($data as &$v) {
            $v['add_time'] = date('Y-m-d H:i:s', $v['add_time']);
            $v['username'] = hideStr($v['username']);
        }

        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }


    //推送活动完成情况给用户
    public function pushMessageToFinishTaskUser($uid = 0)
    {
        $apiRedis = new ApiRedis();
        //获取用户数据
        $userDetail = UserModel::where("id", $uid)->field('tree,level_id')->find();
        if (!$userDetail) {
            return false;
        }
        //获取邀请了列表
        $remunerationList = $this->getRemuneration()['data']['list'];
        $userFactory = new UserFactory($uid);
        foreach ($remunerationList as $v) {
            //两小时内推送过则不再推送
            $pushUserKey = 'push_user_finish_invite_key_' . $uid . '_' . $v['id'];
            if ($apiRedis->has($pushUserKey)) {
                continue;
            }
            $status = 0;
            if ($v['is_finish'] == 1) {
                $status = 1;
            }
            $isReceive = 0;
            if ($v['is_receive'] == 1) {
                $isReceive = 1;
            }
            //判断是否是重复领取的奖励
            $receiveNum = 1;
            //获取用户邀请人数
            $people = $userFactory->getUserInvitePerson($this->uid, $v['start_time']);
            if ($v['reward_type'] == AgentSettingModel::REWARD_TYPE_ONE) {
                $receiveNum = (new AgentFactory($this->uid))->getReceivePeopleNum($this->uid, $v, $people);
                //判断是否能领取
                if ($receiveNum > 0) {
                    $status = 1;
                }
            }
            $amount = $receiveNum * $v['stratum_bonus'];
            if ($amount > 0 && !$isReceive && $status == 1) {
                //完成任务且没有领取给用户发送没有领取通知
                MessageService::sendTaskFinishMessageNotify($uid, 'invite');
                //设置缓存两小时内不再推送
                $apiRedis->set($pushUserKey, 1, 3600 * 2);
            }
        }
        return true;
    }
}