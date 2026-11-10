<?php

namespace app\manage\factory;

use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\model\ArtificialBalanceModel;
use app\common\model\PddWithdrawalRecordModel;
use app\common\model\ReceiveCodeRecordModel;
use app\common\model\SignRecordModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\WuFuWithdrawalModel;
use app\common\service\MemberService;
use app\common\Utils;
use think\facade\Request;
use think\facade\Db;

class ChartCaiwuFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function list()
    {
        $param=Request::param();
        $channel = Util::getOpenChannel(); //充值的
        $w_channel = Util::getWOpenChannel(); //提现的
        $info=$param['info'] ?? [];
        if(empty($info['date']) || $info['date'] == ''){
            $info['date'] = date("Y-m-d")." - ".date("Y-m-d");
        }

        $date=explode(' - ',$info['date']);
        $data = [];
        $where = $_condition = $add_where = $agentfyfyWhere = $up_where = $user_where  = $active_wh = $art_where = $activity_where = $lottery_where= $action_where =[];
        if(!empty($info['lebal'])){
            $info['lebal'] = $info['lebal'] == 1 ? 1 : 2;
            $where[] = ['lebal','=',$info['lebal']];
            $up_where[] = ['lebal','=',$info['lebal']];
            $add_where[] = ['lebal','=',$info['lebal']];
            $user_where[] = ['lebal','=',$info['lebal']];
            $art_where[] = ['a.lebal','=',$info['lebal']];
            $lebal_sql = UserModel::where([['lebal','=',$info['lebal']]])->field('id')->buildSql();
            $activity_where[] = ['uid','exp',Db::raw('in'.$lebal_sql)];
            $lottery_where[] = ['uid','exp',Db::raw('in'.$lebal_sql)];
            $thirdBack_where[] = ['sb_uid','exp',Db::raw('in'.$lebal_sql)];
            $action_where[] = ['uid','exp',Db::raw('in'.$lebal_sql)];
            $_condition[] = ['su_uid','exp',Db::raw('in'.$lebal_sql)];
            $agentfyfyWhere[] = ['sff_uid','exp',Db::raw('in'.$lebal_sql)];
        }
        //增加代理线搜索条件
        if(!empty($info['username'])){
            $agent_id = UserModel::where("username",$info['username'])->value("id") ?? 0;
            $agent_wh[] = ['tree','like', '%/'.$agent_id.'/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $where[] = ['uid','exp',Db::raw('in'.$sql)];
            $up_where[] = ['uid','exp',Db::raw('in'.$sql)];
            $add_where[] = ['uid','exp',Db::raw('in'.$sql)];
            $user_where[] = ['id','exp',Db::raw('in'.$sql)];
            $active_wh[] = ['uid','exp',Db::raw('in'.$sql)];
            $art_where[] = ['a.uid','exp',Db::raw('in'.$sql)];
            $activity_where[] = ['uid','exp',Db::raw('in'.$sql)];
            $lottery_where[] = ['uid','exp',Db::raw('in'.$sql)];
            $thirdBack_where[] = ['sb_uid','exp',Db::raw('in'.$sql)];
            $action_where[] = ['uid','exp',Db::raw('in'.$sql)];
            $_condition[] = ['su_uid','exp',Db::raw('in'.$sql)];
            $agentfyfyWhere[] = ['sff_uid','exp',Db::raw('in'.$sql)];
        }

        $start_time = Utils::getQueryDateTime($date[0]);
        $end_time = Utils::getQueryDateTime($date[1], true);
        $where[] = [['add_time','>=',$start_time],['add_time','<=',$end_time]];
        $up_where[] = [['a.update_time','>=',$start_time],['a.update_time','<=',$end_time]];
        $add_where[] = [['a.add_time','>=',$start_time],['a.add_time','<=',$end_time]];
        $user_where[] = [['add_time','>=',$start_time],['add_time','<=',$end_time]];
        $active_wh[] = [['login_time','>=',$start_time],['login_time','<=',$end_time]];
        $art_where[] = [['a.add_time','>=',$start_time],['a.add_time','<=',$end_time]];
        $activity_where[] = [['create_time','>=',$start_time],['create_time','<=',$end_time]];
        $lottery_where[] = [['time','>=',$start_time],['time','<=',$end_time]];
//        $thirdBack_where[] = [['sb_paytime','>=',$start_time],['sb_paytime','<=',$end_time]];
        $thirdBack_where[] = ['sb_dt','between', [date("Ymd", $start_time), date("Ymd", $end_time)]];
        $action_where[] = [['a.ps_deta','>=',$start_time],['a.ps_deta','<=',$end_time]];
        $_condition[] = ['su_dt','between', [date("Ymd", $start_time), date("Ymd", $end_time)]];
        $agentfyfyWhere[] = ['sff_w','between', [date("Ymd", $start_time), date("Ymd", $end_time)]];
        $all_wh['status'] = [3,4];
        /*充值类型*/
        if(isset($channel[0])){
            $wh = [];
            $wh[] = ['channel', '=', 0];//线下银行卡
            $data['cz']['off_bank_num'] =  UserOfflineModel::get_count($up_where,$wh,$all_wh);
            $data['cz']['off_bank_money'] = UserOfflineModel::get_sum($up_where,$wh,$all_wh,'arrival_price');
        }

        if(isset($channel[1])) {
            $wh = [];
            $wh[] = ['channel', '=', 1];//线上银行卡
            $data['cz']['on_bank_num'] = UserOfflineModel::get_count($up_where,$wh,$all_wh);
            $data['cz']['on_bank_money'] = UserOfflineModel::get_sum($up_where,$wh,$all_wh,'arrival_price');
        }

        if(isset($channel[2])) {
            $wh = [];
            $wh[] = ['channel', '=', 2];//线上USDT
            $data['cz']['on_usdt_num'] = UserOfflineModel::get_count($up_where,$wh,$all_wh);
            $data['cz']['on_usdt_money'] = UserOfflineModel::get_sum($up_where,$wh,$all_wh,'arrival_price');
        }

        if(isset($channel[3])) {
            $wh = [];
            $wh[] = ['channel', '=', 3];//线下USDT
            $data['cz']['off_usdt_num'] = UserOfflineModel::get_count($up_where,$wh,$all_wh);
            $data['cz']['off_usdt_money'] = UserOfflineModel::get_sum($up_where,$wh,$all_wh,'arrival_price');
        }

        if(isset($channel[4])) {
            $wh = [];
            $wh[] = ['channel', '=', 4];//电子钱包
            $data['cz']['off_wallet_num'] = UserOfflineModel::get_count($up_where,$wh,$all_wh);
            $data['cz']['off_wallet_money'] = UserOfflineModel::get_sum($up_where,$wh,$all_wh,'arrival_price');
        }

        if(isset($channel[5])) {
            $wh = [];
            $wh[] = ['channel', '=', 5];//线上电子钱包
            $data['cz']['on_wallet_num'] = UserOfflineModel::get_count($up_where,$wh,$all_wh);
            $data['cz']['on_wallet_money'] = UserOfflineModel::get_sum($up_where,$wh,$all_wh,'arrival_price');
        }

        if(isset($channel[6]) || isset($channel[7])) {
            $wh = [];
            $wh[] = ['channel', 'in', '6,7'];//PIX钱包
            $data['cz']['off_pix_num'] = UserOfflineModel::get_count($up_where,$wh,$all_wh);
            $data['cz']['off_pix_money'] = UserOfflineModel::get_sum($up_where,$wh,$all_wh,'arrival_price');
        }

        if(isset($channel[20])) {
            $wh = [];
            $wh[] = ['channel', '=', 20];//银商
            $data['cz']['off_agent_num'] = UserOfflineModel::get_count($up_where,$wh,$all_wh);
            $data['cz']['off_agent_money'] = UserOfflineModel::get_sum($up_where,$wh,$all_wh,'arrival_price');
        }

        /*提现类型*/
        if(isset($w_channel[0])){
            $wh = [];
            $wh[] = ['channel', '=', 0];
            $data['tx']['bank_num'] = UserWithdrawalModel::get_count($up_where,$wh,$all_wh); //提现银行卡（笔数）
            $data['tx']['bank_money'] = UserWithdrawalModel::getWithdrawUserAmount($up_where,$wh)[2]; //提现银行卡（金额）
            $data['tx']['bank_poundage'] = UserWithdrawalModel::getWithdrawFeeAmount($up_where,$wh); //提现银行卡（手续费）
        }

        if(isset($w_channel[1])) {
            $wh = [];
            $wh[] = ['channel', '=', 1];
            $data['tx']['usdt_num'] = UserWithdrawalModel::get_count($up_where,$wh,$all_wh); //提现usdt（笔数）
            $data['tx']['usdt_money'] = UserWithdrawalModel::getWithdrawUserAmount($up_where,$wh)[2];//提现usdt（金额）
            $data['tx']['usdt_poundage'] = UserWithdrawalModel::getWithdrawFeeAmount($up_where,$wh); //提现usdt（手续费）

        }

        if(isset($w_channel[2])) {
            $wh = [];
            $wh[] = ['channel', '=', 2];
            $data['tx']['wallet_num'] = UserWithdrawalModel::get_count($up_where,$wh,$all_wh);//提现电子钱包（笔数）
            $data['tx']['wallet_money'] = UserWithdrawalModel::getWithdrawUserAmount($up_where,$wh)[2];//提现电子钱包（金额）
            $data['tx']['wallet_poundage'] = UserWithdrawalModel::getWithdrawFeeAmount($up_where,$wh); //提现电子钱包（手续费）

        }

        if(isset($w_channel[3])) {
            $wh = [];
            $wh[] = ['channel', '=', 3];
            $data['tx']['pix_num'] = UserWithdrawalModel::get_count($up_where,$wh,$all_wh);//提现pix钱包（笔数）
            $data['tx']['pix_money'] = UserWithdrawalModel::getWithdrawUserAmount($up_where,$wh)[2];//提现pix钱包（金额）
            $data['tx']['pix_poundage'] = UserWithdrawalModel::getWithdrawFeeAmount($up_where,$wh); //提现pix钱包（手续费）

        }

        /*充提统计*/
//        $c_wh[] = ['channel','in',array_keys($channel)];
        $c_wh= [];
        $data['c_to_t']['cz_num'] = UserOfflineModel::get_count($add_where,$c_wh); //充值总笔数
        $data['c_to_t']['cz_price'] = UserOfflineModel::getRechargeUserAmount($add_where,$c_wh); //充值总金额
        $data['c_to_t']['cz_people'] = UserOfflineModel::getRechargeUserCount($add_where,$c_wh);; //充值总人数(成功)
        $data['c_to_t']['cz_succ'] = UserOfflineModel::get_count($add_where,$c_wh,$all_wh); //充值成功笔数

//        $tx_wh[] = ['channel','in',array_keys($w_channel)];
        $tx_wh = [];
        $data['c_to_t']['tx_num'] = UserWithdrawalModel::get_count($add_where,$tx_wh); //提现总笔数
        $data['c_to_t']['tx_price'] = UserWithdrawalModel::getWithdrawUserAmount($add_where, $tx_wh)[2]; //提现总额
        $data['c_to_t']['tx_people'] = UserWithdrawalModel::get_count($add_where,$all_wh,$tx_wh, 'a.uid'); //提现总人数(成功)
        $data['c_to_t']['tx_succ'] = UserWithdrawalModel::get_count($add_where,$all_wh,$tx_wh); //提现成功笔数

        /*新增统计*/
        $data['add']['cz_num'] = UserOfflineModel::getRechargeUserCount($up_where);//充值人数
        $data['add']['cz_money'] = UserOfflineModel::getRechargeUserAmount($up_where);//充值金额

        $data['add']['sc_money'] = UserOfflineModel::getRechargeUserAmount($up_where, [["a.first", "=", 1]]);  //首充金额1
        $data['add']['fc_money'] = round($data['add']['cz_money'] - $data['add']['sc_money'], 2);  //复充金额


        $data['add']['sc_num'] = UserOfflineModel::getRechargeUserCount($up_where, [["a.first", "=", 1]]); //首充人数
        $data['add']['fc_num'] = $data['add']['cz_num'] - $data['add']['sc_num']; //复充人数
        $data['add']['zc_num'] = UserModel::where($user_where)->count(); //注册人数
        $data['add']['active_num'] = MemberService::getActiveUserNum($active_wh); //活跃人数

        $data['add']['agent_reward'] = Db::name('third_stats_fyfy')->where($agentfyfyWhere)->where('sff_payed', '=', 2)->sum('sff_fy'); //代理返佣

//            /*人工操作*/
        $data['artificial']['add'] = ArtificialBalanceModel::get_count($art_where,['a_type'=>1],''); //人工增加
        $data['artificial']['del'] = ArtificialBalanceModel::get_count($art_where,['a_type'=>2],'price'); //人工减少
        $data['artificial']['giving'] = ArtificialBalanceModel::get_count($art_where,[['a_type', "=", 3]],'price'); //赠送彩金


        //活动统计
        $data['activity']['TaskRecor_price'] = Db::name('task_record')->where($activity_where)->where([['reward_type','=',0]])->sum('reward_amount_or_integral'); //任务领取金额
        $data['activity']['TaskRecor_integral'] = Db::name('task_record')->where($activity_where)->where([['reward_type','=',1]])->sum('reward_amount_or_integral'); //任务领取积分
        $data['activity']['TaskStageRecor_price'] = Db::name('task_stage_record')->where($activity_where)->sum('reward_amount'); //阶段任务领取金额
        $data['activity']['TaskStageRecor_integral'] = Db::name('task_stage_record')->where($activity_where)->sum('reward_integral'); //阶段任务领取积分
        $data['activity']['userLevel_week_price'] = Db::name('user_level_reward')->where($activity_where)->where([['reward_type','=',1]])->sum('reward_amount'); //用户等级周奖励
        $data['activity']['userLevel_the_price'] = Db::name('user_level_reward')->where($activity_where)->where([['reward_type','=',2]])->sum('reward_amount'); //用户等级月奖励
        $data['activity']['agentSell_price'] = Db::name('agent_sell_log')->where($activity_where)->where([['status','=',3]])->sum('money'); //推广奖励
        $data['activity']['lottery_price'] = Db::name('lottery_record')->where($lottery_where)->where([['prize_type','=',1],['is_free','=',1],['is_fake','=',0]])->sum('prize_price'); //免费转盘金额
        $data['activity']['thirdBack_price'] = Db::name('third_stats_back')->where($thirdBack_where)->where([['sb_payed','=',2]])->sum('sb_backmoney'); //游戏返水
        $data['activity']['action_register_price'] = Db::name('action_apply')->alias('a')->join('action b','a.action_id=b.id and a.ps_status=1')->where($action_where)->where([['b.base_type','=',1]])->sum(Db::raw('a.ck_hl_amount+a.gd_amount')); //注册首存
        $data['activity']['action_register_num'] = Db::name('action_apply')->alias('a')->join('action b','a.action_id=b.id and a.ps_status=1')->where($action_where)->where([['b.base_type','=',1]])->count(); //注册首存人数
        $data['activity']['action_restorage_price'] = Db::name('action_apply')->alias('a')->join('action b','a.action_id=b.id and a.ps_status=1')->where($action_where)->where([['b.base_type','=',2]])->sum(Db::raw('a.ck_hl_amount+a.gd_amount')); //再存优惠
        $data['activity']['action_restorage_num'] = Db::name('action_apply')->alias('a')->join('action b','a.action_id=b.id and a.ps_status=1')->where($action_where)->where([['b.base_type','=',2]])->count(); //再存优惠人数
        $data['activity']['action_same_price'] = Db::name('action_apply')->alias('a')->join('action b','a.action_id=b.id and a.ps_status=1')->where($action_where)->where([['b.base_type','=',3]])->sum(Db::raw('a.ck_hl_amount+a.gd_amount')); //一般优惠
        $data['activity']['action_same_num'] = Db::name('action_apply')->alias('a')->join('action b','a.action_id=b.id and a.ps_status=1')->where($action_where)->where([['b.base_type','=',3]])->count(); //一般优惠人数
        $data['activity']['action_game_price'] = Db::name('action_apply')->alias('a')->join('action b','a.action_id=b.id and a.ps_status=1')->where($action_where)->where([['b.base_type','=',6]])->sum(Db::raw('a.ck_hl_amount+a.gd_amount')); //游戏救援金
        $data['activity']['action_game_num'] = Db::name('action_apply')->alias('a')->join('action b','a.action_id=b.id and a.ps_status=1')->where($action_where)->where([['b.base_type','=',6]])->count(); //游戏救援金人数
        $data['activity']['action_invite_reward'] = Db::name('action_apply')->alias('a')->join('action b','a.action_id=b.id and a.ps_status=1')->where($action_where)->where([['b.base_type','=',7]])->sum(Db::raw('a.ck_hl_amount+a.gd_amount')); //推荐奖励
        $data['activity']['action_invite_num'] = Db::name('action_apply')->alias('a')->join('action b','a.action_id=b.id and a.ps_status=1')->where($action_where)->where([['b.base_type','=',7]])->count(); //推荐奖励人数
        $data['activity']['vip_price'] = ArtificialBalanceModel::get_count($art_where,[['a_type', "=", 4]],'price'); //VIP彩金
        $data['activity']['sign_price'] = SignRecordModel::where($activity_where)->where('is_receive_reward', '=', 1)->sum('reward_amount'); //签到奖励总额
        $data['activity']['pdd_price'] = PddWithdrawalRecordModel::where($activity_where)->sum('amount'); //PDD奖励总额
        $data['activity']['receive_code_price'] = ReceiveCodeRecordModel::where($activity_where)->sum('reward_amount'); //领取码奖励总额
        $data['activity']['wufu_price'] = WuFuWithdrawalModel::where($activity_where)->sum('amount'); //五福奖励总额

        /*总计*/
        $tx_all[] = ['channel','in',array_keys($w_channel)];
        $data['count']['poundage'] = UserWithdrawalModel::alias('a')->where($up_where)->where($all_wh)->where($tx_all)->sum('w_service'); //手续费
        $data['count']['tx_all'] = UserWithdrawalModel::alias('a')->where($up_where)->where($all_wh)->where($tx_all)->sum('w_price'); //提现总额
        $data['count']['paragraph'] = round($data['count']['tx_all'] - $data['count']['poundage'],2); //实际出款
        $data['count']['toale_TaskRecor_price'] = round($data['activity']['userLevel_week_price']+$data['activity']['userLevel_the_price'],2); //用户等级奖励总计
        $data['count']['toale_action_price'] = round($data['activity']['action_register_price']+$data['activity']['action_restorage_price']+$data['activity']['action_same_price']+$data['activity']['action_game_price'],2); //优惠活动总计

        //总投注
        $bet = [];
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        $bet['total_bet'] = Db::name($table['_stats_global'])
            ->where($_condition)
            ->sum('su_bet');
        $bet['total_valid_bet'] = Db::name($table['_stats_global'])
            ->where($_condition)
            ->sum('su_valid_bet');
        //投注笔数
        $bet['total_count'] = Db::name($table['_stats_global'])
            ->where($_condition)
            ->sum('su_betnum');
        //投注人数
        $bet['total_person_count'] = Db::name($table['_stats_global'])
            ->where($_condition)->group('su_uid')
            ->count();
        //派彩金额
        $bet['total_bet_amount'] = Db::name($table['_stats_global'])
            ->where($_condition)
            ->sum(Db::raw('su_win'));
        //客损
        $bet['ks_amount'] = Db::name($table['_stats_global'])
            ->where($_condition)
            ->sum("su_syswin") * -1;
        $data['bet'] = $bet;

        $rsps['data'] = $data;
        $rsps['where'] = [
            $where
        ];
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }
}