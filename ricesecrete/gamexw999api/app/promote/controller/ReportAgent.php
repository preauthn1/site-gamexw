<?php
namespace app\promote\controller;
use app\common\lib\Uploads;
use app\common\model\ReceiveCodeRecordModel;
use app\common\model\SignRecordModel;
use app\common\model\TaskModel;
use app\common\model\ThirdStatsGloBalModel;
use app\common\model\UserModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\ActionApplyModel;
use app\common\lib\Respons;
use app\common\Utils;
use think\Request;
use think\facade\Db;

class ReportAgent extends Base
{

    public function getData(Request $request){
        $param = $request->param('info/a');
        $userDetail = $this->user;
        $userSql = UserModel::where('tree', 'like', $userDetail->tree.'%')->field('id')->buildSql();
        if (isset($request->param()['stat'])) {
            $data = [];
        
            /*当天*/
            $today_strat_time = strtotime(date('Y-m-d').'00:00:00');
            $today_end_time = strtotime(date('Y-m-d').'23:59:59');
            /*昨天*/
            $yesterday_strat_time = strtotime(date('Y-m-d').'00:00:00') - 86400;
            $yesterday_end_time = strtotime(date('Y-m-d').'23:59:59') - 86400;
            /*本月*/
            $BeginDate = date('Y-m-01', strtotime(date("Y-m-d")));
            $month_strat_time = strtotime($BeginDate.'00:00:00');
            $month_end_time = strtotime(date('Y-m-d', strtotime("$BeginDate +1 month -1 day"))." 23:59:59");
            /*上月*/
            $lastmonth_strat_time = strtotime(date('Y-m-01', strtotime('-1 month')).'00:00:00');
            $lastmonth_end_time = strtotime(date('Y-m-t', strtotime('-1 month'))." 23:59:59");

            $childUserWhereStr = 'a.id in '.$userSql;
            /*用户数据*/
            $data['today_new_user_count'] = $this->getUserCount($childUserWhereStr, [['a.add_time', ">=", $today_strat_time], ['a.add_time', "<=", $today_end_time], ['a.lebal', '=', 1]]); //获取当天新增正常用户
            $data['today_new_test_user_count'] = $this->getUserCount($childUserWhereStr, [['a.add_time', ">=", $today_strat_time], ['a.add_time', "<=", $today_end_time], ['a.lebal', '=', 2]]); //获取当天新增测试用户
            $data['month_user_count'] = $this->getUserCount($childUserWhereStr, [['a.add_time', ">=", $month_strat_time], ['a.add_time', "<=", $month_end_time], ['a.lebal', '=', 1]]);
            $data['month_test_user_count'] = $this->getUserCount($childUserWhereStr, [['a.add_time', ">=", $month_strat_time], ['a.add_time', "<=", $month_end_time], ['a.lebal', '=', 2]]);
            $data['lastmonth_user_count'] = $this->getUserCount($childUserWhereStr, [['a.add_time', ">=", $lastmonth_strat_time], ['a.add_time', "<=", $lastmonth_end_time], ['a.lebal', '=', 1]]);
            $data['lastmonth_test_user_count'] = $this->getUserCount($childUserWhereStr, [['a.add_time', ">=", $lastmonth_strat_time], ['a.add_time', "<=", $lastmonth_end_time], ['a.lebal', '=', 2]]);
            $data['user_count'] = $this->getUserCount($childUserWhereStr, [['a.lebal', '=', 1]]);
            $data['test_user_count'] = $this->getUserCount($childUserWhereStr, [['a.lebal', '=', 2]]);
            $data['normal_user_count'] = $this->getUserCount($childUserWhereStr, [['a.status', '=', 1]]);
            $data['seal_user_count'] = $this->getUserCount($childUserWhereStr, [['a.status', '=', 0]]);
            $data['user_count_money'] = UserModel::alias('a')->where([['a.lebal', '=', 1]])->where($childUserWhereStr)->sum('balance');
            $data['test_user_count_money'] = UserModel::alias('a')->where([['a.lebal', '=', 2]])->where($childUserWhereStr)->sum('balance');
            $rsps['data']=$data;
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        if (isset($param['search'])) {
            $info = $request->param()['info'] ?? [];
            $start_time = time();
            $end_time = time() - 7 * 86400;
            if (isset($info['date']) && $info['date']!='') {
                $date=explode(' - ',$info['date']);
                $end_time = Utils::getQueryDateTime($date[0]);
                $start_time = Utils::getQueryDateTime($date[1], true);
            }
            $defaultData = [];
            $fields = Db::name('operating')->getFields();
            foreach ($fields AS $key => $val) {
                if (in_array($key, ['id', 'add_time', 'day_time'])) {
                    continue;
                }
                $defaultData[$key] = 0;
            }
            $defaultData['again_recharge_amount'] = 0;
            $defaultData['win_price'] = 0;
            $defaultData['betting_loss_rate'] = 0;
            $list = [];
            for($i = $start_time;$i >= $end_time;$i-=86400){
                $date = date("Y-m-d", $i);
                $dateInfo = Db::name('operating_agent')->where(['day_time' => $date, 'user_id' => $userDetail->id])->find();
                if(empty($dateInfo)) {
                    $row = $defaultData;
                } else {
                    $dateInfo['betting_loss_rate'] = $dateInfo['bet_valid_amount'] > 0 ? round($dateInfo['betting_loss']/$dateInfo['bet_valid_amount']*100, 2) . "%" : "0%";
//                    $dateInfo['win_price'] = round($dateInfo['bet_valid_amount'] - $dateInfo['betting_loss'], 2); //会员总赢
                    $dateInfo['again_recharge_amount'] = $dateInfo['recharge_number'] - $dateInfo['number_charge'];
                    $row = $dateInfo;
                };
                $row['time'] = $date;
                $row['loading'] = 0;
                $row['uid'] = $userDetail->id;
                $row['mail'] = $userDetail->mail;
                $row['phone'] = $userDetail->phone;
                $list[] = $row;
            }

            if (isset($param['export'])&&isset($param['export'])== 1) {
                $header = [
                    'time' => '时间日期',
                    'uid' => '代理id',
                    'mail' => '代理邮箱',
                    'phone' => '代理号码',
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
                $file_name = genCsv($header, $lists,lang('运营总表').date('YmdHis',time()), false);
                $s3_url = Uploads::uploadS3(public_path() . $file_name);
                Utils::addOptLogByHandle('运营总表导出');
                $result['data'] = $s3_url ?: toMedia($file_name, '/');
                $result['code']=1;
                return Respons::run($result);
            }
            $total = $defaultData;
            $total['time'] = "#";
            foreach($list as $v){
                foreach ($defaultData AS $key => $val) {
                    if (in_array($key, ['betting_loss_rate'])) {
                        continue;
                    }
                    $total[$key] = round($total[$key] + $v[$key], 2);
                }
            }
            $total['betting_loss_rate'] = $total['betting_loss'] > 0 && $total['bet_valid_amount'] > 0 ? round($total['betting_loss']/$total['bet_valid_amount']*100, 2) . "%" : "0%";
            $total['time'] = "合计";
            $list[] = $total;

            $rsps['data']=$list;
            $rsps['code']=1;
            return Respons::run($rsps);
        }
    }
    /*
     * 获取用户数量
     * @param $where 条件
     */
    public function getUserCount($where = [], $where1 = []){
        $count = UserModel::alias('a')->where($where)->where($where1)->count();
        return $count;
    }

    public function clear_cache(Request $request){
        $param = $request->param();
        $userDetail = $this->user;
        $endTime = time();
        $startTime = $endTime - 7 * 86400;
        if( $param['date'] ) {
            $timeArr = explode(' - ', $param['date']);
            $startTime = strtotime($timeArr[0]);
            $endTime = strtotime($timeArr[1]);
        }
        for($i = $startTime;$i <= $endTime;$i += 86400) {
            $date = date("Y-m-d", $i);
            self::updateReport($date, $userDetail);
        }
        return Respons::run(1, [], '更新成功');
    }

    /**
     * 更新运营总表
     * @param $date
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private static function updateReport($date, $userDetail){
        $arr = $where = $where1 = $where2 = $where3 = $where4 = $where5 = [];
        $where[]=["a.lebal","=",1];//正式用户
        $where3[]=["a.lebal","=",1];//正式用户
        $startTime = Utils::getQueryDateTime($date[0]);
        $endTime = Utils::getQueryDateTime($date[1], true);
        $userSql = UserModel::where('tree', 'like', $userDetail->tree.'%')->field('id')->buildSql();
        $uidWhereSql = 'uid in'.$userSql;
        $uidaWhereSql = 'a.uid in'.$userSql;
        $where[]=["a.add_time",">=", $startTime];
        $where[]=["a.add_time","<=", $endTime];
        $where2[]=["a.create_time",">=", $startTime];
        $where2[]=["a.create_time","<=", $endTime];
        $where3[]=["a.update_time",">=", $startTime];
        $where3[]=["a.update_time","<=", $endTime];
        $where5[]=["a.update_time",">=", $startTime];
        $where5[]=["a.update_time","<=", $endTime];
        $startDate =  date("Ymd", $startTime);
        $endDate = date("Ymd", $endTime);
        $where_game_time = [];
        $where_game_time[]=["su_dt",">=", $startDate];
        $where_game_time[]=["su_dt","<=", $endDate];

        $arr['day_time'] = $date;
        $field = "count(distinct su_uid) as tz_number,".
            "SUM(su_betnum) as tz_num, sum(su_bet) as tz_price,".
            "SUM(su_win) as tz_win, ".
            "SUM(su_draw) as bet_draw, ".
            "SUM(su_syswin) as betting_loss, ".
            "SUM(su_valid_bet) as bet_valid_amount";
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        $bets = Db::name($table['_stats_global'])->field($field)->where($where_game_time)->where('su_uid in'.$userSql)->find();
        $arr['bet_draw'] = $bets['bet_draw'] ?? 0;
        $arr['bet_valid_amount'] = $bets['bet_valid_amount'] ?? 0;
        $arr['number_people'] = $bets['tz_number'] ?? 0;
        $arr['total_bet'] = $bets['tz_price'] ?? 0;
        $arr['number_bets'] = $bets['tz_num'] ?? 0;
        $arr['win_price'] = $bets['tz_win'] ?? 0;
        $arr['betting_loss'] = $bets['betting_loss'] ?? 0; // 平台盈亏
//        $arr['betting_loss'] = $arr['total_bet'] - $arr['win_price']; // 平台盈亏
        $arr['member_total_lose'] = $arr['betting_loss'] * -1; //平台盈亏 x -1 = 会员客损
        $recharge = UserOfflineModel::getRechargeUserAmount($where3, $uidaWhereSql);
        $arr['total_recharge'] = $recharge; // 总充值

        $_where = $where3;
        $_where[] = [["a.first", "=", 1]];
        $recharge = UserOfflineModel::getRechargeUserAmount($_where, $uidaWhereSql);
        $arr['first_recharge_amount'] = $recharge; //首充金额

        $withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where3, $uidaWhereSql);
        $arr['total_withdrawal'] = $withdrawal[2]; // 提现总额

        $withdrawal = UserWithdrawalModel::getWithdrawArrivalUserAmount($where3, $uidaWhereSql);
        $arr['actual_payment'] = $withdrawal[2]; // 实际出款
        $arr['access_balance'] = $arr['total_recharge'] - $arr['actual_payment']; // 存取差额
        $arr['recharge_number'] = UserOfflineModel::getRechargeUserCount($where3, $uidaWhereSql);
        $arr['number_charge'] = UserOfflineModel::getRechargeUserCount($where3, [["a.first", "=", 1]], $uidaWhereSql);
        $arr['number_withdrawals'] = UserWithdrawalModel::getWithdrawUserCount($where3, $uidaWhereSql);
        $arr['new_registrants'] = UserModel::where([['add_time', ">=",  $startTime], ['add_time', "<=",  $endTime], ['lebal', '=', 1]])->where('id in'.$userSql)->count(); // 获取当天新增正常用户
        $arr['artificial_increase'] = ArtificialBalanceModel::alias("a")->where("a_type",1)->where($where)->where($uidaWhereSql)->sum("a.price");//人工增加
        $arr['artificial_reduction'] = ArtificialBalanceModel::alias("a")->where("a_type",2)->where($where)->where($uidaWhereSql)->sum("a.price");//人工减少
        // 反波胆返佣
        $arr['agency_commission'] = AgentRakeBackModel::alias("a")->where("a.status in (3,4)")->where($where5)->where($uidaWhereSql)->sum("a.money");//代理返佣金额
        // 推广奖励
        $arr['promotional_rebates'] = AgentSellLogModel::alias("a")->where("a.status",3)->where($where2)->where($uidaWhereSql)->sum("a.money");//推广奖励金额
        // 人工赠送彩金 + 会员等级彩金
        $arr['give_bonuses'] = ArtificialBalanceModel::alias("a")->where([["a_type", 'in', '3,4']])->where($where)->where($uidaWhereSql)->sum("a.price");//赠送彩金
        // 优惠活动奖金
        $arr['activity_offer'] = ActionApplyModel::alias("a")->join("dq_user b","a.uid=b.id")->where($uidaWhereSql)->where([['a.ps_deta', ">=",  $startTime], ['a.ps_deta', "<=",  $endTime], ['a.ps_status', '=', 1], ['b.lebal', '=', 1]])->sum(Db::raw('a.`gd_amount`+a.`ck_hl_amount`'));//活动优惠
        // 免费转盘奖励
        $arr['lottery'] = Db::name('lottery_record')->where('prize_type', 1)->where($uidWhereSql)->whereBetweenTime("time", $startTime, $endTime)->sum(Db::raw("IF(is_free=1, prize_price, prize_price-price)"));
        //任务奖励
        $arr['task_reward'] = Db::name('task_record')->where($uidWhereSql)->whereBetweenTime("create_time", $startTime, $endTime)->where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)->sum('reward_amount_or_integral');
        //VIP等级周月奖励
        $arr['level_reward'] = Db::name('user_level_reward')->where($uidWhereSql)->whereBetweenTime("create_time", $startTime, $endTime)->sum('reward_amount');
        $arr['game_back'] = Db::name('third_stats_back')
//            ->where('sb_paytime', 'between',[$startTime, $endTime])
            ->where('sb_dt', 'between',[$startDate, $endDate])
            ->where([['sb_payed','=',2]])->where('sb_uid in'.$userSql)
            ->sum('sb_backmoney'); //游戏返水
        $arr['sign_reward'] = SignRecordModel::where('is_receive_reward', '=', 1)
            ->where('create_time', 'between', [$startTime, $endTime])
            ->where($uidWhereSql)
            ->sum('reward_amount'); //签到奖励
        $arr['agent_fyfy'] =  Db::name('third_stats_fyfy')->where('sff_payed', '=', 2)
            ->where('sff_w', 'between', [$startDate, $endDate])
            ->where('sff_uid in'.$userSql)
            ->sum('sff_fy'); //代理返佣
        //领取码
        $arr['record_reward'] = (new ReceiveCodeRecordModel)->where($uidWhereSql)->whereBetweenTime("create_time", $startTime, $endTime)->sum('reward_amount');

        //未结算注单
//        $arr['no_settle_order'] = (new GameBetsModel())
//            ->where('gb_winlose', '=', 1)
//            ->whereBetweenTime("gb_bettime", $startTime, $endTime)
//            ->sum('gb_price');


        $arr['add_time'] = time();
        $arr['turntable_times'] = 0;
        $arr['user_id'] = $userDetail->id;
        $arr['active_number'] = Db::name('user_login')->where($uidWhereSql)->where([['login_time', ">=",  $startTime], ['login_time', "<=",  $endTime]])->count("distinct uid");
        $report = Db::name('operating_agent')->where(['day_time' => $date])->find();
        if(empty($report)){
            Db::name('operating_agent')->insertGetId($arr);
        }else{
            Db::name('operating_agent')->where(['day_time' => $date])->update($arr);
        }
    }

}
