<?php

namespace app\common\service;

use app\api\redis\ApiRedis;
use app\common\model\ActionApplyModel;
use app\common\model\AdsChannelModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\GameBetFeeModel;
use app\common\model\GameBetsModel;
use app\common\model\PddWithdrawalRecordModel;
use app\common\model\ReceiveCodeRecordModel;
use app\common\model\SignRecordModel;
use app\common\model\TaskModel;
use app\common\model\ThirdStatsGloBalModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\WuFuWithdrawalModel;
use app\common\Utils;
use think\facade\Db;

class ReportService
{
    /**
     * 更新运营总表
     * @param $date
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function updateReport($date){
        $arr = $where = $where1 = $where2 = $where3 = $where4 = $where5 = [];
        $where[]=["a.lebal","=",1];//正式用户
        $where3[]=["a.lebal","=",1];//正式用户
        $startTime = Utils::getQueryDateTime($date);
        $endTime = Utils::getQueryDateTime($date, true);
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
        $bets = Db::name($table['_stats_global'])->field($field)->where($where_game_time)->find();
        $arr['bet_draw'] = $bets['bet_draw'] ?? 0;
        $arr['bet_valid_amount'] = $bets['bet_valid_amount'] ?? 0;
        $arr['number_people'] = $bets['tz_number'] ?? 0;
        $arr['total_bet'] = $bets['tz_price'] ?? 0;
        $arr['number_bets'] = $bets['tz_num'] ?? 0;
        $arr['win_price'] = $bets['tz_win'] ?? 0;
        $arr['betting_loss'] = $bets['betting_loss'] ?? 0; // 平台盈亏
//        $arr['betting_loss'] = $arr['total_bet'] - $arr['win_price']; // 平台盈亏
        $arr['member_total_lose'] = $arr['betting_loss'] * -1; //平台盈亏 x -1 = 会员客损
        $recharge = UserOfflineModel::getRechargeUserAmount($where3);
        $arr['total_recharge'] = $recharge; // 总充值

        $recharge = UserOfflineModel::getRechargeUserAmount($where3, [["a.first", "=", 1]]);
        $arr['first_recharge_amount'] = $recharge; //首充金额

        $withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where3);
        $arr['total_withdrawal'] = $withdrawal[2]; // 提现总额

        $withdrawal = UserWithdrawalModel::getWithdrawArrivalUserAmount($where3);
        $arr['actual_payment'] = $withdrawal[2]; // 实际出款
        $arr['access_balance'] = $arr['total_recharge'] - $arr['actual_payment']; // 存取差额
        $arr['recharge_number'] = UserOfflineModel::getRechargeUserCount($where3);
        $arr['number_charge'] = UserOfflineModel::getRechargeUserCount($where3, [["a.first", "=", 1]]);
        $arr['number_withdrawals'] = UserWithdrawalModel::getWithdrawUserCount($where3);
        $arr['new_registrants'] = UserModel::where([['add_time', ">=",  $startTime], ['add_time', "<=",  $endTime], ['lebal', '=', 1]])->count(); // 获取当天新增正常用户
        $arr['artificial_increase'] = ArtificialBalanceModel::alias("a")->where("a_type",1)->where($where)->sum("a.price");//人工增加
        $arr['artificial_reduction'] = ArtificialBalanceModel::alias("a")->where("a_type",2)->where($where)->sum("a.price");//人工减少
        // 反波胆返佣
        $arr['agency_commission'] = AgentRakeBackModel::alias("a")->where("a.status in (3,4)")->where($where5)->sum("a.money");//代理返佣金额
        // 推广奖励
        $arr['promotional_rebates'] = AgentSellLogModel::alias("a")->where("a.status",3)->where($where2)->sum("a.money");//推广奖励金额
        // 人工赠送彩金 + 会员等级彩金
        $arr['give_bonuses'] = ArtificialBalanceModel::alias("a")->where([["a_type", 'in', '3,4']])->where($where)->sum("a.price");//赠送彩金
        // 优惠活动奖金
        $arr['activity_offer'] = ActionApplyModel::alias("a")->join("dq_user b","a.uid=b.id")->where([['a.ps_deta', ">=",  $startTime], ['a.ps_deta', "<=",  $endTime], ['a.ps_status', '=', 1], ['b.lebal', '=', 1]])->sum(Db::raw('a.`gd_amount`+a.`ck_hl_amount`'));//活动优惠
        // 免费转盘奖励
        $arr['lottery'] = Db::name('lottery_record')->where('prize_type', 1)->whereBetweenTime("time", $startTime, $endTime)->sum(Db::raw("IF(is_free=1, prize_price, prize_price-price)"));
        //任务奖励
        $arr['task_reward'] = Db::name('task_record')->whereBetweenTime("create_time", $startTime, $endTime)->where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)->sum('reward_amount_or_integral');
        //VIP等级周月奖励
        $arr['level_reward'] = Db::name('user_level_reward')->whereBetweenTime("create_time", $startTime, $endTime)->sum('reward_amount');
        $arr['game_back'] = Db::name('third_stats_back')
//            ->where('sb_paytime', 'between',[$startTime, $endTime])
            ->where('sb_dt', 'between',[$startDate, $endDate])
            ->where([['sb_payed','=',2]])
            ->sum('sb_backmoney'); //游戏返水
        $arr['sign_reward'] = SignRecordModel::where('is_receive_reward', '=', 1)
            ->where('create_time', 'between', [$startTime, $endTime])
            ->sum('reward_amount'); //签到奖励
        $arr['agent_fyfy'] =  Db::name('third_stats_fyfy')->where('sff_payed', '=', 2)
            ->where('sff_w', 'between', [$startDate, $endDate])
            ->sum('sff_fy'); //代理返佣
        //领取码
        $arr['record_reward'] = (new ReceiveCodeRecordModel)->whereBetweenTime("create_time", $startTime, $endTime)->sum('reward_amount');
        //中奖手续费
        $arr['winprice_fee'] = GameBetFeeModel::where('create_time', 'between', [$startTime, $endTime])->sum('fee');

        //今日注册且首充用户数
        $_condition = [];
        $_condition[] = ['b.add_time', 'between', [$startTime, $endTime]];
        $_condition[] = ['a.lebal', '=', 1];
        $_condition[] = ['a.first', '=', 1];
        $arr['day_register_first_recharge'] = UserOfflineModel::getRechargeUserCount($_condition);

        //未结算注单
//        $arr['no_settle_order'] = (new GameBetsModel())
//            ->where('gb_winlose', '=', 1)
//            ->whereBetweenTime("gb_bettime", $startTime, $endTime)
//            ->sum('gb_price');


        $arr['add_time'] = time();
        $arr['turntable_times'] = 0;
        $arr['active_number'] = Db::name('user_login')->where([['login_time', ">=",  $startTime], ['login_time', "<=",  $endTime]])->count("distinct uid");
        $report = Db::name('operating')->where(['day_time' => $date])->find();
        if(empty($report)){
            Db::name('operating')->insertGetId($arr);
        }else{
            Db::name('operating')->where(['day_time' => $date])->update($arr);
        }
    }

    /**
     * 统计所有优惠活动+赠送彩金
     * @param $startTime
     * @param $endTime
     * @param $uid
     * @param $type  1默认全部  2获取 全部、已领取、未领取
     * @return array
     */
    public static function getBonuses($startTime = 0, $endTime = 99999999999, $uid = null, $type = 1){
        $fyWhere = $where = [];
        if (is_numeric($uid)) {
            $where['uid'] = $uid;
            $fyWhere['sff_uid'] = $uid;
        } elseif (is_array($uid) || $uid instanceof \Closure) {
            $where[] = ['uid', "IN", $uid];
            $fyWhere[] = ['sff_uid', "IN", $uid];
        }
        $all_amount = $not_amount = 0;
        $result = $actionResult = [];
        // 人工赠送彩金 + 会员等级彩金
        $result['bouns'] = ArtificialBalanceModel::where([["a_type", '=', 3]])
            ->where($where)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("add_time", $startTime, $endTime);
            })
//            ->whereBetweenTime("add_time", $startTime, $endTime)
            ->sum("price");//赠送彩金
        if($type == 2){
            $all_amount += $result['bouns'];
        }

        $result['bouns_vip'] = ArtificialBalanceModel::where([["a_type", '=', 4]])
            ->where($where)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("add_time", $startTime, $endTime);
            })
//            ->whereBetweenTime("add_time", $startTime, $endTime)
            ->sum("price");//赠送彩金
        if($type == 2){
            $all_amount += $result['bouns_vip'];
        }

        // 免费转盘奖励
        $result['lottery'] = $actionResult['lottery'] = Db::name('lottery_record')
            ->where($where)
            ->where('is_free', 1)
            ->where('prize_type', 1)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("time", $startTime, $endTime);
            })
//            ->whereBetweenTime("time", $startTime, $endTime)
            ->sum("price");
        if($type == 2){
            $all_amount += $result['lottery'];
        }


        // 优惠活动奖金
        $result['action_apply'] = $actionResult['action_apply'] = Db::name('action_apply')
                    ->where($where)
                    ->where('ps_status', 1)
                    ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                        $query->whereBetweenTime("add_time", $startTime, $endTime);
                    })
//                    ->whereBetweenTime("add_time", $startTime, $endTime)
                    ->sum(Db::raw('`gd_amount`+`ck_hl_amount`'));
        if($type == 2){
            //获取未领取
            $not_amount += Db::name('action_apply')
                ->where($where)
                ->where('ps_status', 0)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("add_time", $startTime, $endTime);
                })
//                ->whereBetweenTime("add_time", $startTime, $endTime)
                ->sum(Db::raw('`gd_amount`+`ck_hl_amount`'));
            //获取全部
            $all_amount += Db::name('action_apply')
                ->where($where)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("add_time", $startTime, $endTime);
                })
//                ->whereBetweenTime("add_time", $startTime, $endTime)
                ->sum(Db::raw('`gd_amount`+`ck_hl_amount`'));

        }
        // 推广奖励
        $result['promotional_rebates'] = AgentSellLogModel::where("status",3)
            ->where($where)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("create_time", $startTime, $endTime);
            })
//            ->whereBetweenTime("create_time", $startTime, $endTime)
            ->sum("money");
//        $promotional_rebates = Db::name('operating')->where("day_time>='".$start_date."' and day_time <= '".$end_date."'")->sum("promotional_rebates") * 1;
        if($type == 2){
            //获取未领取
            $not_amount +=  AgentSellLogModel::where("status",1)
                ->where($where)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("create_time", $startTime, $endTime);
                })
//                ->whereBetweenTime("create_time", $startTime, $endTime)
                ->sum("money");
            //获取全部
            $all_amount +=  AgentSellLogModel::where($where)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("create_time", $startTime, $endTime);
                })
//                ->whereBetweenTime("create_time", $startTime, $endTime)
                ->sum("money");
        }


        //任务奖励
        $result['taskReward'] = $actionResult['taskReward'] = Db::name('task_record')
            ->where($where)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("create_time", $startTime, $endTime);
            })
//            ->whereBetweenTime("create_time", $startTime, $endTime)
            ->where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)
            ->where('receive_status', '=', 1)
            ->sum('reward_amount_or_integral');

        if($type == 2){
            //获取未领取
            $not_amount +=  Db::name('task_record')
                ->where($where)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("create_time", $startTime, $endTime);
                })
//                ->whereBetweenTime("create_time", $startTime, $endTime)
                ->where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)
                ->where('receive_status', '=', 0)
                ->sum('reward_amount_or_integral');
            //获取全部
            $all_amount +=  Db::name('task_record')
                ->where($where)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("create_time", $startTime, $endTime);
                })
//                ->whereBetweenTime("create_time", $startTime, $endTime)
                ->where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)
                ->sum('reward_amount_or_integral');;
        }

        //VIP等级周月奖励
        $result['levelReward'] = Db::name('user_level_reward')
            ->where($where)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("create_time", $startTime, $endTime);
            })
//            ->whereBetweenTime("create_time", $startTime, $endTime)
            ->sum('reward_amount');
        if($type == 2){
            $all_amount += $result['levelReward'];
        }


        //内部代理返佣
        $result['interior_rebates'] = Db::name('third_stats_fyfy')
            ->where($fyWhere)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("sff_paytime", $startTime, $endTime);
            })
//            ->whereBetweenTime("sff_paytime", $startTime, $endTime)
            ->where('sff_payed', '=', 2)
            ->sum('sff_back');
        if($type == 2){
            //获取未领取
            $not_amount +=  Db::name('third_stats_fyfy')
                ->where($fyWhere)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("sff_paytime", $startTime, $endTime);
                })
//                ->whereBetweenTime("sff_paytime", $startTime, $endTime)
                ->where('sff_payed', '=', 1)
                ->sum('sff_back');
            //获取全部
            $all_amount +=  Db::name('third_stats_fyfy')
                ->where($fyWhere)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("sff_paytime", $startTime, $endTime);
                })
//                ->whereBetweenTime("sff_paytime", $startTime, $endTime)
                ->where('sff_payed', '>', 0)
                ->sum('sff_back');
        }


        //领取码
        $result['receive_code'] = $actionResult['receive_code'] = ReceiveCodeRecordModel::where($where)
//            ->whereBetweenTime("create_time", $startTime, $endTime)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("create_time", $startTime, $endTime);
            })
            ->sum('reward_amount');

        if($type == 2){
            $all_amount += $result['receive_code'];
        }


        //签到
        $result['sign_amount'] = $actionResult['sign_amount'] = SignRecordModel::where($where)
//            ->whereBetweenTime("create_time", $startTime, $endTime)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("create_time", $startTime, $endTime);
            })
            ->where('status', '<>', 0)
            ->sum('reward_amount');

        if($type == 2){
            //获取已领取
            $result['sign_amount'] = $actionResult['sign_amount'] = SignRecordModel::where($where)
//                ->whereBetweenTime("create_time", $startTime, $endTime)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("create_time", $startTime, $endTime);
                })
                ->where('is_receive_reward', '=', 1)
                ->sum('reward_amount');

            //获取未领取
            $not_amount +=  SignRecordModel::where($where)
//                ->whereBetweenTime("create_time", $startTime, $endTime)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("create_time", $startTime, $endTime);
                })
                ->where('is_receive_reward', '=', 0)
                ->sum('reward_amount');
            //获取全部
            $all_amount +=  SignRecordModel::where($where)
//                ->whereBetweenTime("create_time", $startTime, $endTime)
                ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                    $query->whereBetweenTime("create_time", $startTime, $endTime);
                })
                ->where('status', '<>', 0)
                ->sum('reward_amount');
        }
        //PDD转盘
        $result['pdd_amount'] = $actionResult['pdd_amount'] = PddWithdrawalRecordModel::where($where)
//            ->whereBetweenTime("create_time", $startTime, $endTime)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("create_time", $startTime, $endTime);
            })
            ->sum('amount');
        if($type == 2){
            $all_amount += $result['pdd_amount'];
        }


        //五福
        $result['wufu'] = $actionResult['wufu'] = WuFuWithdrawalModel::where($where)
//            ->whereBetweenTime("create_time", $startTime, $endTime)
            ->when($startTime != 0 && $endTime != 99999999999, function($query) use ($startTime, $endTime){
                $query->whereBetweenTime("create_time", $startTime, $endTime);
            })
            ->sum('amount');
        if($type == 2){
            $all_amount += $result['wufu'];
        }

        $totalAmount = sprintf4f(array_sum(array_values($result)), 2, true, true);
        $tips = "包含赠送彩金({$result['bouns']}),会员等级彩金({$result['bouns_vip']}),推广奖金({$result['promotional_rebates']}),活动优惠({$result['action_apply']}),免费转盘({$result['lottery']}),".
        "任务奖励({$result['taskReward']}),周月礼金({$result['levelReward']}),内部代理({$result['interior_rebates']}),领取码({$result['receive_code']}),签到({$result['sign_amount']}),PDD转盘({$result['pdd_amount']}),五福({$result['wufu']})";

        //活动彩金
        $actionAmount = sprintf4f(array_sum(array_values($actionResult)), 2, true, true);
        return ['amount' => $totalAmount, 'tips' => $tips, 'action_amount' => $actionAmount, 'all_amount' => $all_amount, 'not_amount' => $not_amount];
    }


    /**
     * 更新渠道日报表
     * @param $date
     * @param $acid
     */
    public static function updateAdReport($date, $acid){
        $redis = new ApiRedis();
        $key = "updateAdReport:{$date}:{$acid}";
        if ($redis->has($key)) {
            return;
        }
        $st =  Utils::getQueryDateTime($date);
        $sd =  Utils::getQueryDateTime($date, true);
        $dt = date("Ymd", $st);
        $redis->set($key, 1, $sd > time() ? 300 : 3600);
        $channel = AdsChannelModel::where('ac_id', $acid)->find();
        if (empty($channel)) {
            return;
        }
        $channel_user = UserModel::where('rec_code', $channel['ac_share_code'])->find();
        if (empty($channel_user)) {
            return;
        }
        // 子查询
        $ac_uid_sql = function($query) use($acid){
            $query->name('user')->where('ac_id', $acid)->field('id');
        };

        $data = [];
        $data['sr_dt'] = $dt;
        $data['sr_acid'] = $acid;
        $data['sr_uid'] = $channel_user['id'];
        $data['sr_share_code'] = $channel['ac_share_code'];
        // 注册用户数
        $data['sr_reg'] = UserModel::where('ac_id', $acid)->whereBetweenTime('add_time', $st, $sd)->count();
        // 累计用户数
        $data['sr_user'] = UserModel::where('ac_id', $acid)->count();

        // 通用查询条件
        $where = [
            ["uid", "IN", $ac_uid_sql],
            ["status", "IN", [3,4]],
            ["add_time", "Between Time", [$st, $sd]],
        ];
        // 首充人数
        $data['sr_first'] = UserOfflineModel::where($where)->where('first', 1)->count('distinct uid');
        // 首充金额
        $data['sr_first_amount'] = UserOfflineModel::where($where)->where('first', 1)->sum('arrival_price');
        // 充值人数
        $data['sr_recharge_user'] = UserOfflineModel::where($where)->count('distinct uid');
        // 充值金额
        $data['sr_recharge_amount'] = UserOfflineModel::where($where)->sum('arrival_price');
        // 提现人数
        $data['sr_withdraw_user'] = UserWithdrawalModel::where($where)->count('distinct uid');
        //提现金额
        $data['sr_withdraw_amount'] = UserWithdrawalModel::where($where)->sum('arrival_prices');
        // 单笔最大提现金额
        $data['sr_w_max_amount'] = UserWithdrawalModel::where($where)->max('arrival_prices');

        // 查询投注信息
        $where_bet = [
            ["gb_uid", "IN", $ac_uid_sql],
            ["gb_winlose", ">=", 1],
//            ["gb_bettime", "Between Time", [$st, $sd]],
        ];
        $whereSql = BetService::getGbidByTime($st, $sd, 'gb_intime')['sql'];
        $field = "SUM(gb_otherwin) as user_lost, SUM(gb_validwater) as bet_valid_amount";
        $bets = GameBetsModel::field($field)->where($where_bet)->where($whereSql)->find();
        // 有效投注
        $data['sr_validbet'] = floatval($bets['bet_valid_amount']);
        // 派彩金额-有效投注=用户输赢 负数为用户输，正数为用户赢
        $data['sr_user_lost'] = floatval($bets['user_lost']);

        $data['sr_bonus'] = self::getBonuses($st, $sd, $ac_uid_sql)['amount'];

        $report = Db::name('ad_report')->where(['sr_dt' => $dt, 'sr_acid' => $acid])->find();
        if(empty($report)){
            Db::name('ad_report')->insertGetId($data);
        }else{
            Db::name('ad_report')->where(['sr_dt' => $dt, 'sr_acid' => $acid])->update($data);
        }
    }

}