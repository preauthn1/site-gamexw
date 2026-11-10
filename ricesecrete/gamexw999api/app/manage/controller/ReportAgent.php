<?php
namespace app\manage\controller;
use app\common\lib\ReportExport;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
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
    protected $middleware = ['ManageCheckLogin',"Language"];

    public function getData(Request $request){
        $info = $request->param();
        $page = $info['page'] ?? 1;
        $limit = $info['limit'] ?? 10;
        $condition = [];
        if( isset($info['username']) && $info['username'] !== '' ){
            if(is_numeric($info['username'])){
                $condition[]=["a.id|a.rec_code|a.phone", "=", $info['username']];
            }else{
                $condition[]=["a.username|a.mail", "like", '%'.$info['username'].'%'];
            }
        }
        if( isset($info['agent_type']) && $info['agent_type'] !== '' ){
            if( $info['agent_type'] == -1 ){
                //无代理为没有上级，筛选没有上级用户(包含内部代理)
                $condition[] = ['a.rec_id', '=', 0];
            }elseif($info['agent_type'] == -2){
                //无代理为没有上级，筛选没有上级用户(不包含内部代理)
                $condition[] = ['a.rec_id', '=', 0];
                $condition[] = ['b.type', '<>', 1];
            }else{
                $condition[] = ['b.type', '=', $info['agent_type']];
            }
        }
        $end_time = time();
        $start_time = time() - 7 * 86400;
        if (isset($info['date']) && $info['date']!='') {
            $date=explode(' - ',$info['date']);
            $start_time = Utils::getQueryDateTime($date[0]);
            $end_time = Utils::getQueryDateTime($date[1], true);
        }
        if (isset($info['export']) && $info['export']== 1) {
            return $this->exportList($condition, $start_time, $end_time);
        }
//        if(($end_time - $start_time) / 86400 > 31){
//            return Respons::run(0, [], '查询时间不得大于三十一天');
//        }
        $userList = UserModel::alias('a')
            ->join('user_agent b', 'b.uid=a.id')
            ->where($condition)->field('a.id,a.tree,a.mail,a.phone')
            ->page($page, $limit)
            ->order('a.id desc')->paginate($limit,false,['query' => $info]);
        foreach ($userList as &$userDetail){
            $userDetail['uid'] = $userDetail['id'];
            $_data = self::getAgentReport($start_time, $end_time, $userDetail);
            foreach ($_data as $k => $v){
                $userDetail[$k] = $v;
            }
        }
        $userList = $userList->toArray();
        if( $userList&&$userList['data'] ){
            $total = [];
            foreach($userList['data'] as $v){
                foreach ($v AS $key => $val) {
                    if (in_array($key, ['betting_loss_rate', 'mail', 'phone', 'tree', 'uid'])) {
                        $total[$key] = $val;
                        continue;
                    }
                    if( isset($total[$key]) ){
                        $total[$key] = round($total[$key] + $val, 2);
                    }else{
                        $total[$key] = round($val, 2);
                    }
                }
            }
            $total['betting_loss_rate'] = $total['betting_loss'] > 0 && $total['bet_valid_amount'] > 0 ? round($total['betting_loss']/$total['bet_valid_amount']*100, 2) . "%" : "0%";
            $total['uid'] = "合计";
            $userList['data'][] = $total;
        }
        /*if (isset($info['export']) && $info['export']== 1) {
            dd(123);
            return $this->exportList($where,$where1,$whereStr1);

            $header = [
//                'time' => '时间日期',
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
//                'again_recharge_amount' => '复充人数',
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
            dd($userList);
            $file_name = genCsv($header, $userList,lang('运营总表').date('YmdHis',time()), false);
            $s3_url = Uploads::uploadS3(public_path() . $file_name);
            Utils::addOptLogByHandle('运营总表导出');
            $result['data'] = $s3_url ?: toMedia($file_name, '/');
            $result['code']=1;
            return Respons::run($result);
        }*/

        $rsps['data']=$userList;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    private function exportList($where, $startTime, $endTime){
        $params = \think\facade\Request::param();
        $params['where'] = $where;
        $params['positionField'] = 'a.id';
        $params['positionOrder'] = 'desc';
        $params['muid']=$this->request->token->uid;

        $rlt = ReportExport::export(new rptParams('代理运营报表',
            [
                new rptCol(rptCol::BuildRowData,'','app\manage\controller\ReportAgent::getRowData',['start_time' => $startTime, 'end_time' => $endTime]),
                'uid:代理id',
                'mail:代理邮箱',
                'phone:代理号码',
                'total_recharge:充值总额',
                'first_recharge_amount:首充金额',
                'total_withdrawal:提现总额',
                'actual_payment:实际出款',
                'access_balance:存取差额',
                'new_registrants:新注册人数',
                'recharge_number:充值人数',
                'number_charge:首充人数',
                'again_recharge_amount:复充人数',
                'number_withdrawals:提现人数',
                'number_people:投注人数',
                'number_bets:投注笔数',
                'total_bet:投注总额',
                'bet_valid_amount:有效投注',
                'betting_loss:游戏盈亏',
                'win_price:派彩总额',
                'betting_loss_rate:杀率',
                'artificial_increase:人工增加',
                'artificial_reduction:人工减少',
                'promotional_rebates:推广奖励',
                'activity_offer:活动优惠',
                'give_bonuses:赠送彩金',
                'lottery:免费转盘奖励',
                'task_reward:任务中心奖励',
                'level_reward:VIP等级周月奖励',
                'game_back:游戏返水',
                'sign_reward:签到奖励',
                'agent_fyfy:代理返佣',
                'record_reward:领取码',
            ],
            __CLASS__, 'exportListList',
            $params,
            ['mail', 'phone', 'uid'],//导出表名称使用的字段信息
            'a.id', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    //获取单条数据
    public static function getRowData($value, &$datarow, $transparams){
        $userDetail = UserModel::where('id', '=', $datarow['uid'])->find();
        $_data = self::getAgentReport($transparams['start_time'], $transparams['end_time'], $userDetail);
        return array_merge($datarow, $_data);
    }

    public static function exportListList($params){
        return UserModel::alias('a')
            ->join('user_agent b', 'b.uid=a.id')
            ->where($params['where'])->field('a.id,a.tree,a.mail,a.phone,b.uid');
    }

    /**
     * 获取代理运营总表数据
     * @param $initStartTime
     * @param $initEndTime
     * @param $userDetail
     * @param $return_type
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getAgentReport($initStartTime, $initEndTime, $userDetail,$return_type = 1){
        $dataArr = [];
        for ($i = $initStartTime; $i <= $initEndTime; $i += 86400) {
            $date = date('Y-m-d', $i);
            $condition = [];
            $condition[] = ['day_time', '=', $date];
            $condition[] = ['user_id', '=', $userDetail->id];
            $report = Db::name('operating_agent')->where($condition)->find();
            if($report){
                $arr = $report;
            }else{
                $arr = $where = $where1 = $where2 = $where3 = $where4 = $where5 = [];
                $where[]=["a.lebal","=",1];//正式用户
                $where3[]=["a.lebal","=",1];//正式用户

                $startTime = Utils::getQueryDateTime($date);
                $endTime = Utils::getQueryDateTime($date, true);
                $userSql = UserModel::where('tree', 'like', $userDetail['tree'].'%')->field('id')->buildSql();
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
                $arr['user_id'] = $userDetail->id;
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
                $arr['again_recharge_amount'] = $arr['recharge_number'] - $arr['number_charge'];

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
//        $arr['user_id'] = $userDetail['id'];
                $arr['active_number'] = Db::name('user_login')->where($uidWhereSql)->where([['login_time', ">=",  $startTime], ['login_time', "<=",  $endTime]])->count("distinct uid");
                //杀率
                $arr['betting_loss_rate'] = $arr['betting_loss'] > 0 && $arr['bet_valid_amount'] > 0 ? round($arr['betting_loss']/$arr['bet_valid_amount']*100, 2) : 0;

            }

            //总统计返回
            foreach ($arr as $__k => $__v){
                //如果是时间则跳过
                if( strpos($__k, 'time') !== false || $__k == 'user_id' || $__k == 'id'){
                    continue;
                }
                if( isset($dataArr[$__k]) ){
                    $dataArr[$__k] += floatval($__v);
                }else{
                    $dataArr[$__k] = floatval($__v);
                }
            }

            if( $date >= date('Y-m-d') ){
                continue;
            }
            unset($arr['id']);
            //今天数据不添加和修改
//            if( $date != date('Y-m-d') ){
//                if(empty($report)){
//                    Db::name('operating_agent')->insertGetId($arr);
//                } else{
//                    Db::name('operating_agent')->where($condition)->update($arr);
//                }
//            }

        }
        if( isset($dataArr['betting_loss_rate']) ){
            $dataArr['betting_loss_rate'] .= "%";
        }
        if($return_type == 1){
            return $dataArr;
        }else{
            return $arr;
        }
    }

}
