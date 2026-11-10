<?php

namespace app\manage\controller;

use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\ActionApplyModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\PaySetModel;
use app\common\model\SignRecordModel;
use app\common\model\TaskModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\redis\CommomRedis;
use app\common\service\GameService;
use app\common\service\MemberService;
use app\common\service\UserOfflineService;
use app\common\Utils;
use app\manage\factory\AgentReportFactory;
use think\facade\Db;
use think\facade\Request;
use think\facade\View;

class AgentReport
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    public function index(\think\Request $request)
    {
        $rsps = (new AgentReportFactory($request->token->uid))->lists();
        return $rsps;
    }

    public function user(\think\Request $request)
    {
        $param = Request::param();
        $username = $param['username'] ?? '';
        $redis_key = '';

        $info = $param['info'] ?? $param;
        $where[] = ["a.lebal", "=", 1];//正式用户
        $where1[] = ["a.lebal", "=", 1];//正式用户
        $ag_where = [];
        if (isset($info['date']) && $info['date'] != '') {
            $date = explode(' - ', $info['date']);
            $startTime = strtotime($date[0]);
            $endTime = strtotime($date[1]);
        } else {
            $date = [date("Y-m-d"), date("Y-m-d")];
            $startTime = Utils::getQueryDateTime($date[0]);
            $endTime = Utils::getQueryDateTime($date[1], true);
        }
        $redis_key = md5(json_encode([$date, $param]));
        $where[] = ["a.add_time", ">=", $startTime];
        $where[] = ["a.add_time", "<=", $endTime];
        //时间类型
        if (isset($param['time_type']) && $param['time_type'] == 1) {
            //审核时间
            $where1[] = ["a.update_time", ">=", $startTime];
            $where1[] = ["a.update_time", "<=", $endTime];
        } else {
            //用户注册时间
            $where1[] = ["b.add_time", ">=", $startTime];
            $where1[] = ["b.add_time", "<=", $endTime];
        }

        $whereCreate = [];
        $whereCreate[] = ["create_time", ">=", $startTime];
        $whereCreate[] = ["create_time", "<=", $endTime];
        $whereAdd = [];
        $whereAdd[] = ["add_time", ">=", $startTime];
        $whereAdd[] = ["add_time", "<=", $endTime];
        $startDate = date("Ymd", $startTime);
        $endDate = date("Ymd", $endTime);
        $dateArr = [$startDate, $endDate];

        if (isset($param['search']) && $param['search'] == 1) {
            if (isset($info['username']) && $info['username'] != '') {
                if (is_numeric($info['username'])) {
                    $ag_where[] = ['a.id|a.rec_code|a.phone', "=", $info['username']];
                } else {
                    $ag_where[] = ['a.id|a.username|a.mail|a.phone', "=", $info['username']];
                }
            }
            if (isset($info['rec_code']) && $info['rec_code'] != '') {
                $ag_where[] = ['a.rec_code', "=", $info['rec_code']];
            }
            if (!isset($ag_where[0])) {
                $data['off_cz_pnumber'] = 0; // 充值人数
                $data['off_cz_price'] = 0; // 充值总额
                $data['sc_price'] = 0; // 充值总额
                $data['off_tpnumber'] = 0; // 提现人数
                $data['off_tprice'] = 0; // 提现总额
                $data['sc_pnumber'] = 0; //首充总人数
                $data['sc_bfb'] = 0;//首充比例            
                $data['fc_pnumber'] = 0;  //复充总人数
                $data['fc_bfb'] = 0;//复充比例

                $data['reg_number'] = 0;
                $data['count_pnumber'] = 0;//总人数
                $data['rec_click'] = 0;//推广点击量
                $data['count_balance'] = 0;//总余额
                $rsps['data'] = $data;
                $rsps['code'] = 1;
                return Respons::run($rsps);
            }
            $CommomRedis = new CommomRedis();
            if (!empty($CommomRedis->get($redis_key)) && 1 == 2) {
                $data = json_decode($CommomRedis->get($redis_key), true);
            } else {
                $rec = UserModel::alias("a")
                    ->join("dq_user_agent b", "a.id=b.uid")
                    ->where($ag_where)
                    ->where("b.status", 3)
                    ->field("a.id,a.rec_level,a.rec_click,a.tree")
                    ->find();//要是代理且是通过
                if (!isset($rec['id'])) {
                    return Respons::run(0, [], lang('代理不存在'));
                }

                $str_uid = unLevelSql($rec['id'], $rec['rec_level'], $info['rec_level'], $rec['tree']);
                $data['off_cz_pnumber'] = UserOfflineModel::getRechargeUserCount($where1, "a.uid in(" . $str_uid . ")"); // 充值人数
                $data['off_cz_price'] = UserOfflineModel::getRechargeUserAmount($where1, "uid in(" . $str_uid . ")");
//                $data['off_cz_price'] = MemberService::getRechargeByUid($str_uid);; // 充值总额
                $data['off_tpnumber'] = UserWithdrawalModel::getWithdrawUserCount($where1, "uid in(" . $str_uid . ")"); // 提现人数
                $withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where1, "uid in(" . $str_uid . ")");
                $data['off_tprice'] = $withdrawal[2]; // 提现总额
                $data['sc_pnumber'] = UserOfflineModel::getRechargeUserCount($where1, "uid in(" . $str_uid . ") and a.first=1"); //首充总人数
                $recharge = UserOfflineModel::getRechargeUserAmount($where1, "uid in(" . $str_uid . ") and a.first=1");
                $data['sc_price'] = $recharge; // 首充总额
                $data['sc_bfb'] = round((($data['sc_pnumber'] != 0 ? $data['sc_pnumber'] / $data['off_cz_pnumber'] : 0) * 100), 2);//首充比例

                //首充外复充
                $data['fc_pnumber'] = $data['off_cz_pnumber'] - $data['sc_pnumber'];
                $data['fc_bfb'] = round((($data['fc_pnumber'] != 0 ? $data['fc_pnumber'] / $data['off_cz_pnumber'] : 0) * 100), 2);//复充比例
                //多笔复充
                $data['fc_pnumber2'] = UserOfflineService::getAgainRechargeAmount2($where1, "TotalRechargeUser2_3" . $redis_key, "a.uid in(" . $str_uid . ")");
                $data['fc_bfb2'] = round((($data['fc_pnumber2'] != 0 ? $data['fc_pnumber2'] / $data['off_cz_pnumber'] : 0) * 100), 2);//复充比例

                $data['reg_number'] = UserModel::alias('a')->where("id in(" . $str_uid . ")")->where($where)->count();
                $data['count_pnumber'] = UserModel::alias("a")->where("id in(" . $str_uid . ") and a.lebal=1")->count();//总人数
                $data['rec_click'] = $rec['rec_click'];//推广点击量
                $data['count_balance'] = UserModel::alias("a")->where("id in(" . $str_uid . ") and a.lebal=1")->sum("a.balance");//总余额
                $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
                $data['bet_num'] = Db::name($table['_stats_global'])->where("su_uid in(" . $str_uid . ")")->where('su_dt', 'between', $dateArr)->sum('su_betnum');//投注数量
                $data['bet_price'] = Db::name($table['_stats_global'])->where("su_uid in(" . $str_uid . ")")->where('su_dt', 'between', $dateArr)->sum('su_bet');//投注总额
                $data['bet_winprice'] = Db::name($table['_stats_global'])->where("su_uid in(" . $str_uid . ")")->where('su_dt', 'between', $dateArr)->sum('su_win');//派彩总额
                $data['bet_winLess'] = Db::name($table['_stats_global'])->where("su_uid in(" . $str_uid . ")")->where('su_dt', 'between', $dateArr)->sum(Db::raw('su_syswin*-1'));//游戏盈亏
                $data['artificial_inc'] = ArtificialBalanceModel::where("uid in(" . $str_uid . ")")->where("a_type", 1)->where($whereAdd)->sum("price");//人工增加
                $data['artificial_dec'] = ArtificialBalanceModel::where("uid in(" . $str_uid . ")")->where("a_type", 2)->where($whereAdd)->sum("price");//人工减少
                $data['promotional_rebates'] = AgentSellLogModel::where("uid in(" . $str_uid . ")")->where("status", 3)->where($whereCreate)->sum("money");//推广奖励
                $data['activity_offer'] = ActionApplyModel::alias("a")->join("dq_user b", "a.uid=b.id")->where("b.id in(" . $str_uid . ")")->where([['a.ps_deta', ">=", $startTime], ['a.ps_deta', "<=", $endTime], ['a.ps_status', '=', 1], ['b.lebal', '=', 1]])->sum(Db::raw('a.`gd_amount`+a.`ck_hl_amount`'));//活动优惠
                $data['give_bonuses'] = ArtificialBalanceModel::where("uid in(" . $str_uid . ")")->where([["a_type", 'in', '3,4']])->where($whereAdd)->sum("price");//赠送彩金
                $data['lottery'] = Db::name('lottery_record')->where("uid in(" . $str_uid . ")")->where('prize_type', 1)->whereBetweenTime("time", $startTime, $endTime)->sum("price");//转盘奖励
                $data['task_reward'] = Db::name('task_record')->where("uid in(" . $str_uid . ")")->whereBetweenTime("create_time", $startTime, $endTime)->where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)->sum('reward_amount_or_integral');//任务奖励
                $data['level_reward'] = Db::name('user_level_reward')->where("uid in(" . $str_uid . ")")->whereBetweenTime("create_time", $startTime, $endTime)->sum('reward_amount');//VIP周月奖励
                $data['game_back'] = Db::name('third_stats_back')
                    ->where("sb_uid in(" . $str_uid . ")")
                    ->where('sb_dt', 'between', [$startDate, $endDate])
                    ->where([['sb_payed', '=', 2]])
                    ->sum('sb_backmoney');//游戏反水
                $data['sign_reward'] = SignRecordModel::where('is_receive_reward', '=', 1)
                    ->where("uid in(" . $str_uid . ")")
                    ->where('create_time', 'between', [$startTime, $endTime])
                    ->sum('reward_amount');//签到奖励
                $data['agent_fyfy'] = Db::name('third_stats_fyfy')->where('sff_payed', '=', 2)
                    ->where("sff_uid in(" . $str_uid . ")")
                    ->where('sff_w', 'between', [$startDate, $endDate])
                    ->sum('sff_fy');//代理返佣
                $CommomRedis->set($redis_key, json_encode($data), 60);
            }

            $rsps['data'] = $data;
            $rsps['code'] = 1;
            return Respons::run($rsps);
        }
        if (isset($info['export']) && $info['export'] == 1) {
            $info = $param;
            if (!isset($info['rec_level'])) {
                $info['rec_level'] = 0;
            }
            $ag_where = [];
            if (isset($info['username']) && $info['username'] != '') {
                if (is_numeric($info['username'])) {
                    $ag_where[] = ['a.id|a.rec_code|a.phone', "=", $info['username']];
                } else {
                    $ag_where[] = ['a.id|a.username|a.mail|a.phone', "=", $info['username']];
                }
            }
            $user = UserModel::alias("a")
                ->join("dq_user_agent b", "a.id=b.uid")
                ->where("b.status", 3)
                ->where($ag_where)
                ->field("a.id,a.rec_level,a.tree")
                ->find();
            if (!empty($info['rec_level'])) {
                $rec_level = $user['rec_level'] + intval($info['rec_level']);
                $where_str = "a.rec_level=" . $rec_level . " and a.tree like '" . $user['tree'] . "%'";
            } else {
                $where_str = "a.tree like '" . $user['tree'] . "%'";
            }
            $where2 = [];
            $where2[] = ["a.add_time", ">=", Utils::getQueryDateTime($date[0])];
            $where2[] = ["a.add_time", "<=", Utils::getQueryDateTime($date[1], true)];
            return (new AgentReportFactory($request->token->uid))->exportListUser($where2, $where_str, $dateArr);
        }
        if (isset($param['level']) && $param['level'] == 1) {
            $info = $param;
            $page = $info['page'];
            if (!isset($info['rec_level'])) {
                $info['rec_level'] = 0;
            }
            $limit = $info['limit'];
            $ag_where = [];
            if (isset($info['username']) && $info['username'] != '') {
                if (is_numeric($info['username'])) {
                    $ag_where[] = ['a.id|a.rec_code|a.phone', "=", $info['username']];
                } else {
                    $ag_where[] = ['a.id|a.username|a.mail|a.phone', "=", $info['username']];
                }
            }
            $user = UserModel::alias("a")
                ->join("dq_user_agent b", "a.id=b.uid")
                ->where("b.status", 3)
                ->where($ag_where)
                ->field("a.id,a.rec_level,a.tree")
                ->find();
            if (!isset($user['id'])) {
                return Respons::run(0, [], lang('账号信息不存在'));
            }

            $CommomRedis = new CommomRedis();
            if (false && !empty($CommomRedis->get($redis_key))) {
                $rsps['data'] = json_decode($CommomRedis->get($redis_key), true);
            } else {
                if (!empty($info['rec_level'])) {
                    $rec_level = $user['rec_level'] + intval($info['rec_level']);
                    $where_str = "a.rec_level=" . $rec_level . " and a.tree like '" . $user['tree'] . "%'";
                } else {
                    $where_str = "a.tree like '" . $user['tree'] . "%'";
                }
                $where2 = [];
                $where2[] = ["a.add_time", ">=", Utils::getQueryDateTime($date[0])];
                $where2[] = ["a.add_time", "<=", Utils::getQueryDateTime($date[1], true)];
                $data = UserModel::alias("a")->where($where2)->where($where_str)->order("id desc")->paginate($limit, false, ['query' => $info]);
                $rsps['data'] = $data;
                $rec_name = [];
                foreach ($rsps['data'] as $key => $value) {
                    $rec_name[$value['rec_id']] = UserModel::where("id", $value['rec_id'])->field("username,mail,phone")->find();
                    if (isset($rec_name[$value['rec_id']])) {
                        $rsps['data'][$key]['rec_name'] = $rec_name[$value['rec_id']]['mail'] ?? ($rec_name[$value['rec_id']]['phone'] ?? $rec_name[$value['rec_id']]['username']);
                    }
                    $rsps['data'][$key]['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
//                    $rsps['data'][$key]['recharge'] = UserOfflineModel::getRechargeUserAmount($where1, [["a.uid","=",$value['id']]]);// 充值总额
                    $rsps['data'][$key]['recharge'] = MemberService::getRechargeByUid($value['id']);// 充值总额
                    $withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where1, [["a.uid", "=", $value['id']]]);
                    $rsps['data'][$key]['withdrawal'] = $withdrawal[2]; // 提现总额
                    $rsps['data'][$key]['rec_level'] = $value['rec_level'] - $user['rec_level'];
                    $rsps['data'][$key]['balance'] = $value['balance'] * 1;
                    $_where = [];
                    $_where[] = ['uid', '=', $value['id']];
                    $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
                    $rsps['data'][$key]['bet_num'] = Db::name($table['_stats_global'])->where('su_uid', '=', $value['id'])->where('su_dt', 'between', $dateArr)->sum('su_betnum');//投注数量
                    $rsps['data'][$key]['bet_price'] = Db::name($table['_stats_global'])->where('su_uid', '=', $value['id'])->where('su_dt', 'between', $dateArr)->sum('su_bet');//投注总额
                    $rsps['data'][$key]['bet_winprice'] = Db::name($table['_stats_global'])->where('su_uid', '=', $value['id'])->where('su_dt', 'between', $dateArr)->sum('su_win');//派彩总额
                    $rsps['data'][$key]['bet_winLess'] = Db::name($table['_stats_global'])->where('su_uid', '=', $value['id'])->where('su_dt', 'between', $dateArr)->sum(Db::raw('su_syswin*-1'));//游戏盈亏
                    $rsps['data'][$key]['artificial_inc'] = ArtificialBalanceModel::where($_where)->where("a_type", 1)->where($whereAdd)->sum("price");//人工增加
                    $rsps['data'][$key]['artificial_dec'] = ArtificialBalanceModel::where($_where)->where("a_type", 2)->where($whereAdd)->sum("price");//人工减少
                    $rsps['data'][$key]['promotional_rebates'] = AgentSellLogModel::where($_where)->where("status", 3)->where($whereCreate)->sum("money");//推广奖励
                    $rsps['data'][$key]['activity_offer'] = ActionApplyModel::alias("a")->join("dq_user b", "a.uid=b.id")->where('b.id', '=', $value['id'])->where([['a.ps_deta', ">=", $startTime], ['a.ps_deta', "<=", $endTime], ['a.ps_status', '=', 1], ['b.lebal', '=', 1]])->sum(Db::raw('a.`gd_amount`+a.`ck_hl_amount`'));//活动优惠
                    $rsps['data'][$key]['give_bonuses'] = ArtificialBalanceModel::where($_where)->where([["a_type", 'in', '3,4']])->where($whereAdd)->sum("price");//赠送彩金
                    $rsps['data'][$key]['lottery'] = Db::name('lottery_record')->where($_where)->where('prize_type', 1)->whereBetweenTime("time", $startTime, $endTime)->sum("price");//转盘奖励
                    $rsps['data'][$key]['task_reward'] = Db::name('task_record')->where($_where)->whereBetweenTime("create_time", $startTime, $endTime)->where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)->sum('reward_amount_or_integral');//任务奖励
                    $rsps['data'][$key]['level_reward'] = Db::name('user_level_reward')->where($_where)->whereBetweenTime("create_time", $startTime, $endTime)->sum('reward_amount');//VIP周月奖励
                    $rsps['data'][$key]['game_back'] = Db::name('third_stats_back')
                        ->where('sb_uid', '=', $value['id'])
                        ->where('sb_dt', 'between', [$startDate, $endDate])
                        ->where([['sb_payed', '=', 2]])
                        ->sum('sb_backmoney');//游戏反水
                    $rsps['data'][$key]['sign_reward'] = SignRecordModel::where('is_receive_reward', '=', 1)
                        ->where($_where)
                        ->where('create_time', 'between', [$startTime, $endTime])
                        ->sum('reward_amount');//签到奖励
                    $rsps['data'][$key]['agent_fyfy'] = Db::name('third_stats_fyfy')->where('sff_payed', '=', 2)
                        ->where('sff_uid', '=', $value['id'])
                        ->where('sff_w', 'between', [$startDate, $endDate])
                        ->sum('sff_fy');//代理返佣
                }
                $CommomRedis->set($redis_key, json_encode($data), 60);
            }
            $rsps['level'] = UserModel::where('lebal = 1')->whereLike("tree", "{$user['tree']}%")->order("rec_level desc")->value("rec_level");
            $rsps['where_str1'] = $where_str;
            $rsps['user'] = $user;
            $rsps['code'] = 1;
            return Respons::run($rsps);
        }

    }

    public function userzhudan()
    {
        $param = Request::param();
        $username = isset($param['username']) ? $param['username'] : '';
        if (isset($param['search'])) {
            $info = $param['info'];
            if (isset($info['date']) && $info['date'] != '') {
                $date = explode(' - ', $info['date']);
            } else {
                $date = [date("Y-m-d"), date("Y-m-d")];
            }
            $redis_key_date = $date[0] . $date[1];
            $start_date = Utils::getQueryDate($date[0], "Ymd");
            $end_date = Utils::getQueryDate($date[1], "Ymd");
            //无效代码
//            $where[]=["a.gb_bettime",">=", Utils::getQueryDateTime($date[0])];
//            $where[]=["a.gb_bettime","<=", Utils::getQueryDateTime($date[1], true)];
//            $where1[]=["a.gb_bettime",">=", Utils::getQueryDateTime($date[0])];
//            $where1[]=["a.gb_bettime","<=", Utils::getQueryDateTime($date[1], true)];

            if (isset($info['username']) && $info['username'] != '') {
                $ag_where[] = ['a.username', "=", $info['username']];
            }
            if (isset($info['rec_code']) && $info['rec_code'] != '') {
                $ag_where[] = ['a.rec_code', "=", $info['rec_code']];
            }
            if (!isset($ag_where[0])) {
                $data['tz_price'] = 0;//投注金额
                $data['tz_number'] = 0;//投注人数
                $data['tz_count'] = 0;//投注笔数
                $data['order_win'] = 0;
                $data['order_lose'] = 0;
                $data['order_loss'] = 0;
                $rsps['data'] = $data;
                $rsps['code'] = 1;
                return Respons::run($rsps);
            }

            $user = UserModel::alias("a")
                ->join("dq_user_agent b", "a.id=b.uid")
                ->where($ag_where)
                ->where("b.status", 3)
                ->field("a.id,a.rec_level,a.tree")
                ->find();//要是代理且是通过
            if (!isset($user['id'])) {
                return Respons::run(0, [], lang('代理不存在'));
            }

            $CommomRedis = new CommomRedis();
            $redis_key = $info['username'] . '-' . $redis_key_date . '-' . (isset($info['rec_level']) ? $info['rec_level'] : '');
            if (!empty($CommomRedis->get(md5($redis_key))) && 1 == 2) {
                $data = json_decode($CommomRedis->get(md5($redis_key)), true);
            } else {
                $str_uid = unLevelSql($user['id'], $user['rec_level'], $info['rec_level'], $user['tree']);
                $stat = GameService::statGameBetByGlobal($str_uid, $start_date, $end_date);
                $data['zd_price'] = $stat['tz_price'];//投注金额
                $data['zd_number'] = $stat['tz_num'];//投注笔数
                $data['tz_count'] = $stat['tz_num'];//投注笔数
                $data['order_loss'] = $stat['total_win_lost'];// 输赢

                $CommomRedis->set(md5($redis_key), json_encode($data), 60);
            }

            $rsps['data'] = $data;
            $rsps['code'] = 1;
            return Respons::run($rsps);
        }

        if (isset($param['level'])) {
            $info = $param;
            $page = $param['page'];
            if (!isset($param['rec_level'])) {
                $param['rec_level'] = 0;
            }
            $limit = $param['limit'];
            if (isset($info['date']) && $info['date'] != '') {
                $date = explode(' - ', $info['date']);
            } else {
                $date = [date("Y-m-d"), date("Y-m-d")];
            }
            $redis_key_date = $date[0] . $date[1];
            $start_date = Utils::getQueryDate($date[0], "Ymd");
            $end_date = Utils::getQueryDate($date[1], "Ymd");

            if (!(isset($param['username']) && $param['username'] != '')) {
                return Respons::run(0, [], lang('请输入账号'));
            }
            $user = UserModel::alias("a")
                ->join("dq_user_agent b", "a.id=b.uid")
                ->where("b.status", 3)
                ->where("a.username", $param['username'])
                ->field("a.id,a.rec_level,a.tree")
                ->find();

            if (!isset($user['id'])) {
                return Respons::run(0, [], lang('账号信息不存在'));
            }

            $CommomRedis = new CommomRedis();
            $redis_key = $username . '-' . $redis_key_date . '-' . (isset($info['rec_level']) ? $info['rec_level'] : '');
            if (!empty($CommomRedis->get(md5('listzhudan' . $redis_key . $page)))) {
                $data = json_decode($CommomRedis->get(md5('listzhudan' . $redis_key . $page)), true);
                $rsps['data'] = $data;
            } else {
                if (!empty($param['rec_level'])) {
                    $rec_level = $user['rec_level'] + intval($param['rec_level']);
                    $where_str = "a.rec_level=" . $rec_level . " and a.tree like '" . $user['tree'] . "%'";
                } else {
                    $where_str = "a.tree like '" . $user['tree'] . "%'";
                }

                $data = UserModel::alias("a")->where($where_str)->order("id desc")->paginate($limit, false, ['query' => $param]);
                $rsps['data'] = $data;

                foreach ($rsps['data'] as $key => $value) {
                    $rsps['data'][$key]['rec_name'] = $username;
                    $stat = GameService::statGameBetByGlobal($value['id'], $start_date, $end_date);
                    $rsps['data'][$key]['zd_price'] = $stat['tz_price'];//投注金额
                    $rsps['data'][$key]['zd_number'] = $stat['tz_num'];//投注笔数
                    $rsps['data'][$key]['tz_count'] = $stat['tz_num'];//投注笔数
                    $rsps['data'][$key]['win_price'] = $stat['tz_win'];
                    $rsps['data'][$key]['lose_price'] = 0;
                    $rsps['data'][$key]['rec_level'] = $value['rec_level'] - $user['rec_level'];
                    $rsps['data'][$key]['username'] = $value['mail'] ? $value['mail'] : ($value['phone'] ? $value['phone'] : $value['username']);
                }
                $CommomRedis->set(md5('listzhudan' . $redis_key . $page), json_encode($data), 60);
            }
            $rsps['code'] = 1;
            return Respons::run($rsps);
        }

        $level = UserModel::where('lebal = 1')->order("rec_level desc")->value("rec_level");
        View::assign('level', $level);
        View::assign('username', $username);
        return View::fetch('agent_report/userzhudan');
    }

    public function usergiving()
    {
        $param = Request::param();
        $username = isset($param['username']) ? $param['username'] : '';

        if (isset($param['search'])) {
            $info = $param['info'];
            if (isset($info['date']) && $info['date'] != '') {
                $date = explode(' - ', $info['date']);
            } else {
                $date = [date("Y-m-d"), date("Y-m-d")];
            }
            $redis_key_date = $date[0] . '-' . $date[1];

            if (isset($info['username']) && $info['username'] != '') {
                $ag_where[] = ['a.username', "=", $info['username']];
            }
            if (isset($info['rec_code']) && $info['rec_code'] != '') {
                $ag_where[] = ['a.rec_code', "=", $info['rec_code']];
            }
            $where1[] = ["a.lebal", "=", 1];//正式用户
            $where1[] = ["a.add_time", ">=", Utils::getQueryDateTime($date[0])];
            $where1[] = ["a.add_time", "<=", Utils::getQueryDateTime($date[1], true)];
            $where2[] = ["a.create_time", ">=", Utils::getQueryDateTime($date[0])];
            $where2[] = ["a.create_time", "<=", Utils::getQueryDateTime($date[1], true)];

            if (!isset($ag_where[0])) {
                $data['a_add'] = 0;
                $data['a_reduce'] = 0;
                $data['agent_price'] = 0;
                $data['tg_price'] = 0;
                $data['action_price'] = 0;
                $data['give_price'] = 0;
                $rsps['data'] = $data;
                $rsps['code'] = 1;
                return Respons::run($rsps);
            }

            $user = UserModel::alias("a")
                ->join("dq_user_agent b", "a.id=b.uid")
                ->where($ag_where)
                ->where("b.status", 3)
                ->field("a.id,a.rec_level,a.tree")
                ->find();//要是代理且是通过
            if (!isset($user['id'])) {
                return Respons::run(0, [], lang('代理不存在'));
            }
            $CommomRedis = new CommomRedis();
            $redis_key = 'usergiving_total' . $info['username'] . '-' . $redis_key_date . '-' . (isset($info['rec_level']) ? $info['rec_level'] : '');
            if (!empty($CommomRedis->get(md5($redis_key)))) {
                $data = json_decode($CommomRedis->get(md5($redis_key)), true);
            } else {
                $str_uid = unLevelSql($user['id'], $user['rec_level'], $info['rec_level'], $user['tree']);
                $data['a_add'] = ArtificialBalanceModel::alias("a")->where("a.uid in(" . $str_uid . ")")->where("a.a_type", 1)->where($where1)->sum("a.price");//人工增加
                $data['a_reduce'] = ArtificialBalanceModel::alias("a")->where("a.a_type", 2)->where($where1)->where("a.uid in(" . $str_uid . ")")->sum("a.price");//人工减少
                $data['agent_price'] = AgentRakeBackModel::alias("a")->join("dq_user c", "a.uid=c.id")->where("a.status in (3,4)")->where($where2)->where("a.uid in(" . $str_uid . ")")->sum("a.money");//代理返佣金额
                $data['tg_price'] = AgentSellLogModel::alias("a")->join("dq_user c", "a.uid=c.id")->where("a.status", 3)->where($where2)->where("a.uid in(" . $str_uid . ")")->sum("a.money");//推广奖励金额
                $data['action_price'] = ActionApplyModel::alias("a")->join("dq_user c", "a.uid=c.id")->where([['a.ps_deta', ">=", Utils::getQueryDateTime($date[0])], ['a.ps_deta', "<=", Utils::getQueryDateTime($date[1], true)], ['a.ps_status', '=', 1]])->where("a.uid in(" . $str_uid . ")")->sum("a.gd_amount");//活动优惠
                $data['give_price'] = ArtificialBalanceModel::alias("a")->where([["a.a_type", 'in', '3,4']])->where($where1)->where("a.uid in(" . $str_uid . ")")->sum("a.price");//赠送彩金
                $CommomRedis->set(md5($redis_key), json_encode($data), 60);
            }

            $rsps['data'] = $data;
            $rsps['code'] = 1;
            return Respons::run($rsps);
        }

        if (isset($param['level'])) {
            $info = $param;
            $page = $param['page'];
            if (!isset($param['rec_level'])) {
                $param['rec_level'] = 0;
            }
            $limit = $param['limit'];
            if (isset($info['date']) && $info['date'] != '') {
                $date = explode(' - ', $info['date']);
            } else {
                $date = [date("Y-m-d"), date("Y-m-d")];
            }
            $redis_key_date = $date[0] . '-' . $date[1];
            if (!(isset($param['username']) && $param['username'] != '')) {
                return Respons::run(0, [], lang('请输入账号'));
            }
            $where1[] = ["a.lebal", "=", 1];//正式用户
            $where[] = ["a.add_time", ">=", Utils::getQueryDateTime($date[0])];
            $where[] = ["a.add_time", "<=", Utils::getQueryDateTime($date[1], true)];
            $where1[] = ["a.add_time", ">=", Utils::getQueryDateTime($date[0])];
            $where1[] = ["a.add_time", "<=", Utils::getQueryDateTime($date[1], true)];
            $where2[] = ["a.create_time", ">=", Utils::getQueryDateTime($date[0])];
            $where2[] = ["a.create_time", "<=", Utils::getQueryDateTime($date[1], true)];
            $user = UserModel::alias("a")
                ->join("dq_user_agent b", "a.id=b.uid")
                ->where("b.status", 3)
                ->where("a.username", $param['username'])
                ->field("a.id,a.rec_level,a.tree")
                ->find();

            if (!isset($user['id'])) {
                return Respons::run(0, [], lang('账号信息不存在'));
            }

            $CommomRedis = new CommomRedis();
            $redis_key = 'usergiving' . $username . '-' . $redis_key_date . '-' . (isset($info['rec_level']) ? $info['rec_level'] : '') . $page;
            if (!empty($CommomRedis->get(md5($redis_key)))) {
                $data = json_decode($CommomRedis->get(md5($redis_key)), true);
            } else {
                if (!empty($param['rec_level'])) {
                    $rec_level = $user['rec_level'] + intval($param['rec_level']);
                    $where_str = "a.rec_level=" . $rec_level . " and a.tree like '" . $user['tree'] . "%'";
                } else {
                    $where_str = "a.tree like '" . $user['tree'] . "%'";
                }

                $data = UserModel::alias("a")->where($where_str)->field('id,username,rec_level')->order("id desc")->paginate($limit, false, ['query' => $param]);
                foreach ($data as $key => $value) {
                    $data[$key]['rec_name'] = $username;
                    $data[$key]['a_add'] = ArtificialBalanceModel::alias("a")->where('a.uid', $value['id'])->where("a.a_type", 1)->where($where1)->sum("a.price");//人工增加
                    $data[$key]['a_reduce'] = ArtificialBalanceModel::alias("a")->where("a.a_type", 2)->where($where1)->where('a.uid', $value['id'])->sum("a.price");//人工减少
                    $data[$key]['agent_price'] = AgentRakeBackModel::alias("a")->join("dq_user c", "a.uid=c.id")->where("a.status in (3,4)")->where($where2)->where('c.id', $value['id'])->sum("a.money");//代理返佣金额
                    $data[$key]['tg_price'] = AgentSellLogModel::alias("a")->join("dq_user c", "a.uid=c.id")->where("a.status", 3)->where($where2)->where('c.id', $value['id'])->sum("a.money");//推广奖励金额
                    $data[$key]['action_price'] = ActionApplyModel::alias("a")->join("dq_user c", "a.uid=c.id")->where([['a.ps_deta', ">=", Utils::getQueryDateTime($date[0])], ['a.ps_deta', "<=", Utils::getQueryDateTime($date[1], true)], ['a.ps_status', '=', 1]])->where('c.id', $value['id'])->sum("a.gd_amount");//活动优惠
                    $data[$key]['give_price'] = ArtificialBalanceModel::alias("a")->where([["a.a_type", 'in', '3,4']])->where($where1)->where('a.uid', $value['id'])->sum("a.price");//赠送彩金
                }
                $CommomRedis->set(md5($redis_key), json_encode($data), 60);
            }
            $rsps['code'] = 1;
            $rsps['data'] = $data;
            return Respons::run($rsps);
        }

        $level = UserModel::where('lebal = 1')->order("rec_level desc")->value("rec_level");
        View::assign('level', $level);
        View::assign('username', $username);
        return View::fetch('agent_report/usergiving');
    }

    public function user_data_view()
    {
        $param = Request::instance()->param();
        View::assign('param', $param);
        return View::fetch('agent_report/user_data_view');
    }

    public function user_data()
    {
        $param = Request::instance()->param();
        $page = $param['page'];
        $limit = $param['limit'];
        $channel = Util::getOpenChannel();
//        $where = $this->getDate($param);
        if (isset($param['date']) && $param['date'] != '') {
            $date = explode(' - ', $param['date']);
        } else {
            $date = [date("Y-m-d"), date("Y-m-d")];
        }
        $where[] = ["a.add_time", ">=", Utils::getQueryDateTime($date[0])];
        $where[] = ["a.add_time", "<=", Utils::getQueryDateTime($date[1], true)];
        $where1[] = ["a.update_time", ">=", Utils::getQueryDateTime($date[0])];
        $where1[] = ["a.update_time", "<=", Utils::getQueryDateTime($date[1], true)];
        if (!(isset($param['username']) && $param['username'] != '')) {
            return Respons::run(0, [], lang('请输入账号'));
        }
        $where[] = ['lebal', '=', 1];
        $user = UserModel::alias("a")
            ->join("dq_user_agent b", "a.id=b.uid")
            ->where("b.status", 3)
            ->where("a.username|a.mail|a.phone|a.id", $param['username'])
            ->field("a.id,a.rec_level")
            ->find();
        if (!isset($user['id'])) {
            return Respons::run(0, [], lang('账号信息不存在'));
        }

        $sql = unLevelSql($user['id'], $user['rec_level'], $param['rec_level']);

        $wh = [];
        $is_up = 0;
        if ($param['type'] == 1) {
            $model = new UserModel;
            $field = 'a.id';
            $w_id = 'a.id';
        } else if ($param['type'] == 3 || $param['type'] == 4) {
            if ($param['type'] == 3) {
                $wh[] = ['a.first', '=', 1];
            }
            $wh[] = ['a.status', "in", "3,4"];
            $model = new UserOfflineModel;
            $field = 'a.uid';
            $w_id = 'a.uid';
            $is_up = 1;
        } else if ($param['type'] == 5) {
            $model = new UserWithdrawalModel;
            $field = 'a.uid';
            $w_id = 'a.uid';
            $wh['a.status'] = [3, 4];
            $is_up = 1;
        } else if ($param['type'] == 6) {
            $where[] = ['a.status', "in", "3,4"];
            $model = new UserOfflineModel;
            $field = 'a.uid';
            $w_id = 'a.uid';
            $is_up = 1;
        }
        if ($is_up == 1) { // 如果是充提的话 时间戳字段要用update_time
            $nsql = $model::alias("a")
                ->where($wh)
                ->where($where1)
                ->where($w_id . ' in (' . $sql . ')')
                ->field($field)
                ->buildSql();
        } else {
            $nsql = $model::alias("a")
                ->where($wh)
                ->where($where)
                ->where($w_id . ' in (' . $sql . ')')
                ->field($field)
                ->buildSql();
        }
        if ($param['type'] == 6) { // 充值列表的时候
            $data = UserOfflineModel::alias("a")
                ->join("dq_user b", "a.uid = b.id")
                ->where('a.uid in (' . $nsql . ')')
                ->where('a.status in (3,4)')
                ->where($where1)
                ->order("id desc")
                ->field("a.*,b.username")
                ->paginate($limit, false, ['query' => $param]);
            $rsps['data'] = $data;
            foreach ($rsps['data'] as $key => &$val) {
                $val['channel_name'] = isset($channel[$val['channel']]) ? lang($channel[$val['channel']]) : "";
                $val['add_time'] = date("Y-m-d H:i:s", $val['add_time']);
                if ($val['update_time']) {
                    $val['update_time'] = date("Y-m-d H:i:s", $val['update_time']);
                } else {
                    $val['update_time'] = '';
                }
                if ($val['pay_id'] > 0) {
                    $val['pay_name'] = PaySetModel::where(['id' => $val['pay_id']])->value('pay_name');
                }
            }
        } else {
            $data = UserModel::alias("a")
                ->where('a.id in (' . $nsql . ')')
                ->order("id desc")
                ->paginate($limit, false, ['query' => $param]);
            $rsps['data'] = $data;
            foreach ($rsps['data'] as $key => $value) {
                $rsps['data'][$key]['rec_name'] = UserModel::where("id", $value['rec_id'])->value("username");
                $rsps['data'][$key]['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
                if ($param['type'] == 6) {
                    $rsps['data'][$key]['recharge'] = $value['arrival_price']; // 充值总额
                } else {
                    $recharge = UserOfflineModel::getRechargeUserAmount($where1, [["a.uid", "=", $value['id']]]);
                    $rsps['data'][$key]['recharge'] = $recharge; // 充值总额
                }

                $withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where1, [["a.uid", "=", $value['id']]]);
                $rsps['data'][$key]['withdrawal'] = $withdrawal[2]; // 提现总额

                $rsps['data'][$key]['zd_price'] = 0;//投注金额
                $rsps['data'][$key]['zd_number'] = 0;//投注笔数

                $rsps['data'][$key]['win_price'] = 0;
                $rsps['data'][$key]['lose_price'] = 0;
                $rsps['data'][$key]['rec_level'] = $value['rec_level'] - $user['rec_level'];
            }
        }

        $rsps['code'] = 1;
        return Respons::run($rsps);
    }


}