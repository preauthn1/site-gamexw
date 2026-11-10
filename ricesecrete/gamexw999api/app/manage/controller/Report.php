<?php

namespace app\manage\controller;

use app\common\FastGame\Socket\R;
use app\common\FastGame\Socket\SockPlugin;
use app\common\lib\Uploads;
use app\common\lib\ActiveReport;
use app\common\model\AdminModel;
use app\common\model\ExportReportsModel;
use app\common\model\GameBetsModel;
use app\common\model\ThirdStatsGloBalModel;
use app\common\model\UserAgentModel;
use app\common\model\UserModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\ActionApplyModel;
use app\common\model\LotteryRecordModel;
use app\common\lib\Respons;
use app\common\redis\redis;
use app\common\service\MemberService;
use app\common\service\ReportService;
use app\common\service\UserOfflineService;
use app\common\service\UserOnlineService;
use app\common\Utils;
use app\manage\factory\ReportFactory;
use Carbon\Carbon;
use think\facade\Session;
use app\common\redis\CommomRedis;
use think\facade\View;
use think\Request;
use think\facade\Db;

class Report extends Base
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    //列表
    public function index()
    {
        return View::fetch('report/index');
    }

    public function getData(Request $request)
    {
        $param = $request->param();
        $CommomRedis = new CommomRedis();
        if (isset($param['stat'])) {
            $data = [];

            /*当天*/
            $today_strat_time = strtotime(date('Y-m-d') . '00:00:00');
            $today_end_time = strtotime(date('Y-m-d') . '23:59:59');
            /*昨天*/
            $yesterday_strat_time = strtotime(date('Y-m-d') . '00:00:00') - 86400;
            $yesterday_end_time = strtotime(date('Y-m-d') . '23:59:59') - 86400;
            /*本月*/
            $BeginDate = date('Y-m-01', strtotime(date("Y-m-d")));
            $month_strat_time = strtotime($BeginDate . '00:00:00');
            $month_end_time = strtotime(date('Y-m-d', strtotime("$BeginDate +1 month -1 day")) . " 23:59:59");
            /*上月*/
            $lastmonth_strat_time = strtotime(date('Y-m-01', strtotime('-1 month')) . '00:00:00');
            $lastmonth_end_time = strtotime(date('Y-m-t', strtotime('-1 month')) . " 23:59:59");

            /*用户数据*/
            $data['today_new_user_count'] = $this->getUserCount([['add_time', ">=", $today_strat_time], ['add_time', "<=", $today_end_time], ['lebal', '=', 1]]); //获取当天新增正常用户
            $data['today_new_test_user_count'] = $this->getUserCount([['add_time', ">=", $today_strat_time], ['add_time', "<=", $today_end_time], ['lebal', '=', 2]]); //获取当天新增测试用户
            $data['month_user_count'] = $this->getUserCount([['add_time', ">=", $month_strat_time], ['add_time', "<=", $month_end_time], ['lebal', '=', 1]]);
            $data['month_test_user_count'] = $this->getUserCount([['add_time', ">=", $month_strat_time], ['add_time', "<=", $month_end_time], ['lebal', '=', 2]]);
            $data['lastmonth_user_count'] = $this->getUserCount([['add_time', ">=", $lastmonth_strat_time], ['add_time', "<=", $lastmonth_end_time], ['lebal', '=', 1]]);
            $data['lastmonth_test_user_count'] = $this->getUserCount([['add_time', ">=", $lastmonth_strat_time], ['add_time', "<=", $lastmonth_end_time], ['lebal', '=', 2]]);
            $data['user_count'] = $this->getUserCount([['lebal', '=', 1]]);
            $data['test_user_count'] = $this->getUserCount([['lebal', '=', 2]]);
            $data['normal_user_count'] = $this->getUserCount([['status', '=', 1]]);
            $data['seal_user_count'] = $this->getUserCount([['status', '=', 0]]);
            $data['user_count_money'] = UserModel::where([['lebal', '=', 1]])->sum('balance');
            $data['test_user_count_money'] = UserModel::where([['lebal', '=', 2]])->sum('balance');
            $rsps['data'] = $data;
            $rsps['code'] = 1;
            return Respons::run($rsps);
        }
        if (isset($param['search'])) {
            $info = isset($param['info']) ? $param['info'] : [];
            $start_time = time();
            $end_time = time() - 7 * 86400;
            if (isset($info['date']) && $info['date'] != '') {
                $date = explode(' - ', $info['date']);
                $end_time = Utils::getQueryDateTime($date[0]);
                $start_time = Utils::getQueryDateTime($date[1], true);
            }
            $defaultData = [];
            $fields = Db::name('operating')->getFields();
            foreach ($fields as $key => $val) {
                if (in_array($key, ['id', 'add_time', 'day_time'])) {
                    continue;
                }
                $defaultData[$key] = 0;
            }
            $defaultData['again_recharge_amount'] = 0;
            $defaultData['win_price'] = 0;
            $defaultData['betting_loss_rate'] = 0;
            $list = [];
            for ($i = $start_time; $i >= $end_time; $i -= 86400) {
                $date = date("Y-m-d", $i);
                $dateInfo = Db::name('operating')->where(['day_time' => $date])->find();
                if (empty($dateInfo)) {
                    $row = $defaultData;
                } else {
                    $dateInfo['betting_loss_rate'] = $dateInfo['bet_valid_amount'] > 0 ? round($dateInfo['betting_loss'] / $dateInfo['bet_valid_amount'] * 100, 2) . "%" : "0%";
//                    $dateInfo['win_price'] = round($dateInfo['bet_valid_amount'] - $dateInfo['betting_loss'], 2); //会员总赢
                    $dateInfo['again_recharge_amount'] = $dateInfo['recharge_number'] - $dateInfo['number_charge'];
                    $row = $dateInfo;
                };
                $row['time'] = $date;
                $row['loading'] = 0;
                $list[] = $row;
            }
            $total = $defaultData;
            $total['time'] = "#";
            foreach ($list as $v) {
                foreach ($defaultData as $key => $val) {
                    if (in_array($key, ['betting_loss_rate'])) {
                        continue;
                    }
                    $total[$key] = round($total[$key] + $v[$key], 2);
                }
            }
            $total['betting_loss_rate'] = $total['betting_loss'] > 0 && $total['bet_valid_amount'] > 0 ? round($total['betting_loss'] / $total['bet_valid_amount'] * 100, 2) . "%" : "0%";
            $total['time'] = "合计";
            $list[] = $total;

            if (isset($param['export']) && isset($param['export']) == 1) {
                $header = [
                    'time' => '时间日期',
                    'total_recharge' => '充值总额',
                    'first_recharge_amount' => '首充金额',
                    'total_withdrawal' => '提现总额',
                    'actual_payment' => '实际出款',
                    'access_balance' => '存取差额',
                    'new_registrants' => '新注册人数',
                    'recharge_number' => '充值人数',
                    'number_charge' => '首充人数',
                    'again_recharge_amount' => '复充人数',
                    'number_withdrawals' => '提现人数',
                    'number_people' => '投注人数',
                    'number_bets' => '投注笔数',
                    'total_bet' => '投注总额',
                    'bet_valid_amount' => '有效投注',
                    'betting_loss' => '游戏盈亏',
                    'win_price' => '派彩总额',
                    'betting_loss_rate' => '杀率',
                    'artificial_increase' => '人工增加',
                    'artificial_reduction' => '人工减少',
                    'promotional_rebates' => '推广奖励',
                    'activity_offer' => '活动优惠',
                    'give_bonuses' => '赠送彩金',
                    'lottery' => '免费转盘奖励',
                    'task_reward' => '任务中心奖励',
                    'level_reward' => 'VIP等级周月奖励',
                    'game_back' => '游戏返水',
                    'sign_reward' => '签到奖励',
                    'agent_fyfy' => '代理返佣',
                    'record_reward' => '领取码',
                ];
                $lists = $list;
                $file_name = genCsv($header, $lists, lang('运营总表') . date('YmdHis', time()), false);
                $s3_url = Uploads::uploadS3(public_path() . $file_name);
                Utils::addOptLogByHandle('运营总表导出');
                $result['data'] = $s3_url ?: toMedia($file_name, '/');
                $result['code'] = 1;
                return Respons::run($result);
            }

            $rsps['data'] = $list;
            $rsps['code'] = 1;
            return Respons::run($rsps);
        }
    }

    /*
     * 获取用户数量
     * @param $where 条件
     */
    public function getUserCount($where)
    {
        $count = UserModel::where($where)->count();
        return $count;
    }

    public function queryData(Request $request)
    {
        $key = $request->param('key/s');
        $date = $date2 = $request->param('date/s');
        if (!empty($key)) {
            $CommomRedis = new CommomRedis();
            if (isset($date) && $date != '') {
                $date = explode(' - ', $date);
            } else {
                $date = [date('Y-m-d'), date('Y-m-d')];
            }
            $today_strat_time = Utils::getQueryDateTime($date[0]);
            $today_end_time = Utils::getQueryDateTime($date[1], true);
            $redis_key = md5(json_encode([$key, $today_end_time, $today_strat_time])) . '_v1';
            $redis_tips_key = md5(json_encode([$key, $today_end_time, $today_strat_time])) . '_tips_v1';
            $where_game_time = [];
            $where_game_time[] = ['su_dt', '>=', date('Ymd', $today_strat_time)];
            $where_game_time[] = ['su_dt', '<=', date('Ymd', $today_end_time)];

            $where_day1 = [];
            $where_day1[] = ["a.update_time", ">=", $today_strat_time];
            $where_day1[] = ["a.update_time", "<=", $today_end_time];
            $where_day1[] = ["a.lebal", "=", 1];//正式用户
            $cache_time = 60;
            // 生命周期价值相关缓存1800秒
            if (in_array($key, ['RechargeUserAvgLifeTimeValue', 'LastMonthRechargeUserAvgLifeTime', 'ToMonthRechargeUserAvgLifeTime', 'RechargeUserAvgLifeTime'])) {
                $cache_time = 1800;
            }
            $total = 0;
            $tips = null;
            $result = [];
            if ($CommomRedis->has($redis_key)) {
                $total = $CommomRedis->get($redis_key);
                $result['data'] = $total;
                $result['tips'] = $CommomRedis->get($redis_tips_key) ?: $tips;
                $result['code'] = 1;
                return Respons::run($result);
            }
            //在线人数
            if ($key == 'onlineUser') {
                $total = UserOnlineService::getOnlineUserNum([['uo_stime', ">=", $today_strat_time], ['uo_stime', "<=", $today_end_time], ['uo_onstate', "=", 1]]);
//                $total = SockPlugin::getSocketMsgServerOnlineUserNum();
                $tips = '当前在线人数';
            }
            //注册人数
            if ($key == "TotalUser1") {
                $total = $this->getUserCount([['add_time', ">=", $today_strat_time], ['add_time', "<=", $today_end_time], ['lebal', '=', 1]]);
                $tips = '新注册用户数量';
            }
            // 活跃人数
            if ($key == "TotalUser2") {
                $total = MemberService::getActiveUserNum([['login_time', 'between', [$today_strat_time, $today_end_time]]]);
                $tips = '登录过的用户数量';
            }
            // 三方余额
            if ($key == "ThirdsBalance") {
                $total = round(MemberService::getUserThirdsBalance(0),2);
                $tips = '三方余额';
            }
            // 三方余额+盘口余额
            if ($key == "ThirdsTotalBalance") {
                $total = round(MemberService::getUserThirdsBalance(0) + UserModel::where([['lebal', '=', 1]])->sum('balance'),2);
                $tips = '三方余额+盘口余额';
            }
            // 盘口
            if ($key == "TotalBalance") {
                $total = UserModel::where([['lebal', '=', 1]])->sum('balance');
                $tips = '所有正式用户总余额';
            }
            // 充值人数 今天
            if ($key == "TotalRechargeUser1") {
                $total = UserOfflineModel::getRechargeUserCount($where_day1);
                $tips = '充值过的用户数量';
            }
            // 首充人数 今天
            if ($key == "TotalRechargeUser2") {
                $total = UserOfflineModel::getRechargeUserCount($where_day1, [["a.first", "=", 1]]);
                $tips = '首充用户数量（用户第一笔充值,例：用户1号充值，2号充值，则该用户只计算在1号首充中）';
            }
            // 复充人数1 今天
            if ($key == "TotalRechargeUser3") {
                $total = UserOfflineService::getAgainRechargeAmount($where_day1, "TotalRechargeUser3" . $redis_key);
                $tips = '首充外复充（充值人数-首充人数）';
            }
            // 复充人数2 今天
            if ($key == "TotalRechargeUser2_3") {
                $total = UserOfflineService::getAgainRechargeAmount2($where_day1, "TotalRechargeUser2_3" . $redis_key);
                $tips = '多笔复充（充值多笔即算复充）';
            }
            // 首充充值总额 今天
            if ($key == "TotalFirstRecharge") {
                $total = UserOfflineModel::getRechargeUserAmount($where_day1, [["a.first", "=", 1]]);
                $tips = '首充总额';
            }
            // 充值用户平均生命周期(天)
            if ($key == "RechargeUserAvgLifeTime") {
                $life_times = Db::name('user_offline')->whereIn('status', '3,4')
                    ->field(Db::raw("min(add_time) AS min_t,max(add_time) as max_t,uid"))
                    ->group('uid')
                    ->select();
                $users = count($life_times);
                $time = 0;
                foreach ($life_times as $life_time) {
                    $time += ($life_time['max_t'] - $life_time['min_t']);
                }
                $total = $users > 0 ? sprintf4f($time / $users / 86400) : 0;
                $tips = 'SUM(用户最后充值时间 - 第一次充值时间) ÷ 总充值用户数';
            }
            // 本月充值用户平均生命周期(天)
            if ($key == "ToMonthRechargeUserAvgLifeTime") {
                $life_times = Db::name('user_offline')->whereIn('status', '3,4')
                    ->field(Db::raw("min(add_time) AS min_t,max(add_time) as max_t,uid"))
                    ->whereTime('add_time', 'month')
                    ->group('uid')
                    ->select();
                $users = count($life_times);
                $time = 0;
                foreach ($life_times as $life_time) {
                    $time += ($life_time['max_t'] - $life_time['min_t']);
                }
                $total = $users > 0 ? sprintf4f($time / $users / 86400) : 0;
                $tips = 'SUM(用户最后充值时间 - 第一次充值时间) ÷ 总充值用户数';
            }
            // 上月充值用户平均生命周期(天)
            if ($key == "LastMonthRechargeUserAvgLifeTime") {
                $life_times = Db::name('user_offline')->whereIn('status', '3,4')
                    ->field(Db::raw("min(add_time) AS min_t,max(add_time) as max_t,uid"))
                    ->whereTime('add_time', 'last month')
                    ->group('uid')
                    ->select();
                $users = count($life_times);
                $time = 0;
                foreach ($life_times as $life_time) {
                    $time += ($life_time['max_t'] - $life_time['min_t']);
                }
                $total = $users > 0 ? sprintf4f($time / $users / 86400) : 0;
                $tips = 'SUM(用户最后充值时间 - 第一次充值时间) ÷ 总充值用户数';
            }
            // 充值用户平均生命周期价值
            if ($key == "RechargeUserAvgLifeTimeValue") {
                $life_times = Db::name('user_offline')->whereIn('status', '3,4')
                    ->field(Db::raw("sum(arrival_price) AS amount,uid"))
                    ->group('uid')
                    ->select();
                $users = count($life_times);
                $amount = 0;
                foreach ($life_times as $life_time) {
                    $amount += $life_time['amount'];
                }
                $total = $users > 0 ? sprintf4f($amount / $users) : 0;
                $tips = '用户充值总额÷总充值用户数';
            }
            // 线上充值 今天
            if ($key == "TotalRecharge1") {
                $total = UserOfflineModel::getRechargeUserAmount($where_day1, [], 2);
                $tips = '线上充值总额';
            }
            // 线下转帐 今天
            if ($key == "TotalRecharge2") {
                $total = UserOfflineModel::getRechargeUserAmount($where_day1, [], 1);
                $tips = '线下充值总额';
            }
            // USDT充值 今天
            if ($key == "TotalRecharge3") {
                $total = UserOfflineModel::getRechargeUserAmount($where_day1, [], 3);
                $tips = 'USDT充值总额';
            }
            // 充值总额 今天
            if ($key == "TotalRecharge4") {
                $total = UserOfflineModel::getRechargeUserAmount($where_day1);
                $tips = '充值总额';
            }
            // 充值笔数 今天
            if ($key == "RechargeCount") {
                $total = UserOfflineModel::getRechargeUserAmount($where_day1, [], 4);
                $tips = '充值笔数';
            }
            // 提现总额 今天
            if ($key == "TotalWithdraw1") {
                $recharge = UserWithdrawalModel::getWithdrawUserAmount($where_day1);
                $total = $recharge[2];
                $tips = '提现总额';
            }
            // 提现人数 今天
            if ($key == "TotalWithdraw2") {
                $total = UserWithdrawalModel::getWithdrawUserCount($where_day1);
                $tips = '提现总人数';
            }
            // 提现笔数 今天
            if ($key == "TotalWithdraw3") {
                $total = UserWithdrawalModel::getWithdrawCount($where_day1);
                $tips = '提现笔数';
            }
            // 提现手续费 今天
            if ($key == "TotalWithdrawFee1") {
                $total = UserWithdrawalModel::getWithdrawFeeAmount($where_day1);
                $tips = '提现手续费';
            }
            // 赠送彩金
            if ($key == "TotalGiveMoney") {
                $bonus = ReportService::getBonuses($today_strat_time, $today_end_time);
                $total = $bonus['amount'];
                $tips = $bonus['tips'];
            }
            // 人工减少
            if ($key == "TotalArtificialReduction") {
                $start_date = date("Y-m-d", $today_strat_time);
                $end_date = date("Y-m-d", $today_end_time);
                $total = Db::name('operating')->where("day_time>='" . $start_date . "' and day_time <= '" . $end_date . "'")->sum("artificial_reduction");
                $tips = '人工减少金额';
            }
            // 人工增加
            if ($key == "TotalArtificialIncrease") {
                $start_date = date("Y-m-d", $today_strat_time);
                $end_date = date("Y-m-d", $today_end_time);
                $total = Db::name('operating')->where("day_time>='" . $start_date . "' and day_time <= '" . $end_date . "'")->sum("artificial_increase");
                $tips = '人工增加金额';
            }
            // 流水总额
            if ($key == "TotalWaterNum") {
                $start_date = date("Y-m-d", $today_strat_time);
                $end_date = date("Y-m-d", $today_end_time);
                $total = Db::name('operating')->where("day_time>='" . $start_date . "' and day_time <= '" . $end_date . "'")->sum("artificial_increase");
                $tips = '流水总额';
            }
            $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
            // 投注人数
            if ($key == "BetPeople") {
                $total = Db::name($table['_stats_global'])->where($where_game_time)->count("distinct su_uid");
                $tips = '投注人数';
            }
            // 投注笔数
            if ($key == "BetNumber") {
                $total = Db::name($table['_stats_global'])->where($where_game_time)->sum("su_betnum");
                $tips = '投注笔数';
            }
            // 投注总额
            if ($key == "BetTotal") {
                $total = Db::name($table['_stats_global'])->where($where_game_time)->sum("su_bet");
                $tips = '投注总额';
            }
            // 存提差
            if ($key == "RechargeWithdra") {
                $Offline = UserOfflineModel::getRechargeUserAmount($where_day1); //总充值
                $Withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where_day1)[2]; //总提现
                $total = floatval($Offline - $Withdrawal);
                $tips = '充值总额 - 提现总额';
            }
            // 付费率
            if ($key == "Rate") {
                $RechargeUser = UserOfflineModel::getRechargeUserCount($where_day1, [["a.first", "=", 1]]); //首存人数
                $UserCount = $this->getUserCount([['add_time', ">=", $today_strat_time], ['add_time', "<=", $today_end_time], ['lebal', '=', 1]]); //注册人数
                $total = $RechargeUser && $UserCount > 0 ? $RechargeUser / $UserCount : 0;
                $tips = '首充人数/注册人数';
            }
            // 平均首充金额
            if ($key == "AverageRecharge") {
                if (empty($date2)) {
                    $where_day1 = [];
                }
                $Offline = UserOfflineModel::getRechargeUserAmount($where_day1, [["a.first", "=", 1]]); //首存金额
                $OfflineUser = UserOfflineModel::getRechargeUserCount($where_day1, [["a.first", "=", 1]]); //首充人数
                $total = $Offline && $OfflineUser > 0 ? round($Offline / $OfflineUser, 2) : 0;
                $tips = '首充总金额/首充人数';
            }
            // 损益
            if ($key == "Profitloss") {
                $bet = Db::name($table['_stats_global'])->where($where_game_time)->sum("su_bet"); //投注总额
                $win = Db::name($table['_stats_global'])->where($where_game_time)->sum("su_win"); //派彩总额
                $total = round($bet - $win, 2);
                $tips = '投注总额-派彩总额';
            }
            // 客单价
            if ($key == "Customerprice") {
                $Offline = UserOfflineModel::getRechargeUserAmount($where_day1, [["a.first", "=", 1]]); //首存金额
                $OfflineUser = UserOfflineModel::getRechargeUserCount($where_day1, [["a.first", "=", 1]]); //首充人数
                $total = $Offline && $OfflineUser > 0 ? round($Offline / $OfflineUser, 2) : 0;
                $tips = '当天首充总额/首充人数';
            }
            // 杀率
            if ($key == "Killrate") {
                $bet = Db::name($table['_stats_global'])->where($where_game_time)->sum("su_bet"); //投注总额
                $win = Db::name($table['_stats_global'])->where($where_game_time)->sum("su_win"); //派彩总额
                $toa = $bet - $win;
                $total = $toa != 0 && $bet != 0 ? round($toa / $bet, 2) : 0;
                $tips = '损益/投注总额';
            }
            if ($key != 'onlineUser') {
                $CommomRedis->tag('manage:dashboard')->set($redis_key, $total, $cache_time);
                $CommomRedis->tag('manage:dashboard')->set($redis_tips_key, $tips, $cache_time);
            }
            $result['data'] = $total;
            $result['tips'] = $tips;
            $result['code'] = 1;
            return Respons::run($result);
        }
    }

    public function clear_cache(Request $request)
    {
        $param = $request->param();
        $CommomRedis = new CommomRedis();
        $type = isset($param['type']) ? $param['type'] : 0;
        if ($type == 1) {
            $CommomRedis->delete("operation_summary" . $param['date']);
            ReportService::updateReport($param['date']);
        } else {
            $CommomRedis->clearTag('manage:dashboard');
        }
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //用户
    public function user(\think\Request $request)
    {
        $rsps = (new ReportFactory($request->token->uid))->user();
        return $rsps;
    }

    public function get_line(Request $request)
    {
        $where = [
            ['status', 'IN', '3,4']
        ];
        $start_time = Carbon::today()->subDay(7)->timestamp;
        $end_time = Carbon::yesterday()->timestamp;
        $list = UserOfflineModel::where($where)
            ->whereBetweenTime('add_time', $start_time, $end_time)
            ->fieldRaw("FROM_UNIXTIME(add_time,'%Y%m-%d') AS days, SUM(arrival_price) AS amount ")
            ->group('days')
            ->select();
        $result = [];
        for ($i = $start_time; $i < $end_time; $i++) {
            $result[date("m-d", $i)] = 0;
        }

        foreach ($list as $row) {
            $day = substr($row['days'], 4);
            if (isset($result[$day])) {
                $result[$day] = $row['amount'];
            }
        }

        return $this->success([
            'data' => $result,
            'xAxisData' => array_keys($result),
            'seriesData' => array_values($result),
        ]);
    }

    public function get_bar(Request $request)
    {
        $start_time = Carbon::today()->subDay(7);
        $end_time = Carbon::today();
        $result = [];
        for ($i = $start_time->timestamp; $i < $end_time->timestamp; $i++) {
            $result[date("Y-m-d", $i)] = [
                'remain_rate1' => 0,
                'remain_rate2' => 0,
                'remain_rate3' => 0,
                'remain_rate4' => 0,
                'remain_rate5' => 0,
                'remain_rate7' => 0,
                'remain_rate15' => 0,
                'remain_rate30' => 0,
                'remain_rate60' => 0,
            ];
        }
        $list = Db::name('remain_report')->where('type', 2)
            ->where('remain_stat', 'IN', array_keys($result))
            ->select();
        foreach ($list as $row) {
            $day = $row['remain_stat'];//substr($row['remain_stat'], 5);
            if (isset($result[$day])) {
                foreach ([1, 2, 3, 4, 5, 7, 15, 30, 60] as $d) {
                    $key = "remain_rate{$d}";
                    if (isset($result[$day][$key])) {
                        $result[$day][$key] = floatval($row[$key]);
                    }
                }
            }
        }
        foreach ($list as $row) {
            $day = substr($row['remain_stat'], 5);
            if (isset($result[$day])) {
                foreach ([1, 2, 3, 4, 5, 7, 15, 30, 60] as $d) {
                    $key = "remain_rate{$d}";
                    if (isset($result[$day][$key])) {
                        $result[$day][$key] = floatval($row[$key]);
                    }
                }
            }
        }

        $seriesData = [];
        $legend = [];
        foreach ([1, 2, 3, 4, 5, 7, 15, 30, 60] as $d) {
            $key = "remain_rate{$d}";
            $seriesData[$key] = [
                'name' => lang("{:day}天充值留存率", ['day' => $d]),
                'type' => 'bar',
                'data' => array_column($result, $key),
            ];
            $legend[] = lang("{:day}天充值留存率", ['day' => $d]);
        }

        $xAxisData = array_keys($result);
        return $this->success([
            'data' => $result,
            'legend' => $legend,
            'xAxisData' => $xAxisData,
            'seriesData' => array_values($seriesData),
        ]);
    }

    public function iprank()
    {
        $date_str = $this->request->param('date');
        $ip_type = $this->request->param('ip_type', 1);
        $page = $this->request->param('page/d', 1);
        $size = $this->request->param('limit/d', 10);
        $offset = ($page - 1) * $size;
        $field = $ip_type == 1 ? 'reg_ip' : 'end_login_ip';
        $where = [];
        if ($date_str) {
            $date_arr = explode(' - ', $date_str);
            if (count($date_arr) == 2) {
                $where[] = ['add_time', '>=', Utils::getQueryDateTime($date_arr[0])];
                $where[] = ['add_time', '<=', Utils::getQueryDateTime($date_arr[1], true)];
            }
        }

        $list = UserModel::where($where)->field("{$field}, COUNT(id) AS total")
            ->whereNotIn($field, ['', '0.0.0.0'])
            ->group($field)->order('total DESC')->limit($offset, $size)->select();
        $total = UserModel::where($where)->whereNotIn($field, ['', '0.0.0.0'])->count("distinct {$field}");
        $result = [];
        foreach ($list as $key => $value) {
            $row = [
                'ip_address' => $value[$field],
                'ip_local' => ip2location($value[$field]),
                'total' => $value['total']
            ];
            $result[] = $row;
        }

        return $this->success([
            'data' => $result,
            'total' => $total
        ]);
    }

    public function balance()
    {
        $date_str = $this->request->param('date');
        $page = $this->request->param('page/d', 1);
        $size = $this->request->param('limit/d', 10);
        $offset = ($page - 1) * $size;
        $where = [];
        if ($date_str) {
            $date_arr = explode(' - ', $date_str);
            if (count($date_arr) == 2) {
                $where[] = ['create_time', '>=', Utils::getQueryDateTime($date_arr[0])];
                $where[] = ['create_time', '<=', Utils::getQueryDateTime($date_arr[1], true)];
            }
        }

        $list = Db::name('balance_statistics')->where($where)
            ->order('id DESC')->limit($offset, $size)->select();
        $list = $list->toArray();
        $total = Db::name('balance_statistics')->where($where)->count();
        foreach ($list as $k => $v) {
            $list[$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
        }
        return $this->success([
            'data' => $list,
            'total' => $total
        ]);
    }

    //获取报表下载列表
    public function getExportReportsList()
    {
        $page = $this->request->param('page/d', 1);
        $size = $this->request->param('limit/d', 20);
        $name = $this->request->param('name', null);
        if ($name) {
            $uid = AdminModel::where('id', $name)->whereOr('username', 'like', $name)->value('id');
            if (!$uid) {
                return $this->error('不存在的用户');
            }
        } else {
            $uid = Session::get("adminInfo.id");
        }
        return $this->success(ExportReportsModel::getlist($page, $size, $uid));
    }

    public function getExportReportsClear()
    {
        $time = redis::get('generateReports');
        if (!$time) {
            return $this->success();
        }
        if (time() - $time < 60 * 5) {
            //大于五分钟才允许清除
            return $this->error('任务执行中，请耐心等待');
        }
        redis::del('generateReports');
        return $this->success();
    }

    //获取活跃报表时间范围
    public function getActiveReportRange()
    {
        return $this->success(["data" => ActiveReport::getActiveDataRange()]);
    }

    //获取活跃报表数据
    public function getActiveReportData(Request $request)
    {
        $param = $request->param();
        $type = $param['type'];
        $start = $param['s'];
        $end = isset($param['e']) ? $param['e'] : null;
        $proxyid = isset($param['proxyid']) ? $param['proxyid'] : null;
        if (!empty($proxyid)) {
            $proxyid = UserModel::where('username', $proxyid)->whereOr('rec_code', $proxyid)->whereOr('mail', $proxyid)->whereOr('phone', $proxyid)->value('id');
        } else {
            $proxyid = null;
        }
        if ($type == "d") {
            if ($start) {
                $date = explode(' - ', $start);
                $start = str_replace("-", "", $date[0]);
                $end = str_replace("-", "", $date[1]);
            } else {
                $start = date("Ymd", strtotime("-1 day"));
                $end = date("Ymd");
            }
        }
        $field = $param['field'];
        $fieldsum = $param['fieldsum'];
        $data = ActiveReport::getActiveDatas($type, $start, $end, $field, $fieldsum, $proxyid);
        return $this->success(['data' => $data]);
    }

    //获取分布式报表指标信息
    public function getDistributionReportsClass(Request $request)
    {
        return $this->success(['data' => ActiveReport::getDistributionReportsClass()]);
    }

    //获取分布式报表数据
    public function getDistributionReportsData(Request $request)
    {
        $param = $request->param();
        $cycle = isset($param['cycle']) ? $param['cycle'] : false;
        $type = $param['typestr'];
        if ($cycle) {
            $start = $param['s'];
            $date = explode(' - ', $start);
            $start = str_replace("-", "", $date[0]);
            $end = str_replace("-", "", $date[1]);
            return $this->success(['data' => ActiveReport::getDistributionReportDatas($type, $start, $end)]);
        } else {
            $start = $param['s'];
            $end = $param['e'] ?? null;
            $data = [];
            for ($i = 0; $i < count($start); $i++) {
                array_push($data, ActiveReport::getDistributionReportDatas($type, $start[$i]));
            }
            return $this->success(['data' => $data]);
        }
    }
}
