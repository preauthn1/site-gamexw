<?php
namespace app\promote\controller;
use app\common\model\UserModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\ActionApplyModel;
use app\common\model\LotteryRecordModel;
use app\common\lib\Respons;
use app\common\service\AgentService;
use app\common\service\BetService;
use app\common\service\MemberService;
use app\common\service\UserOfflineService;
use app\common\Utils;
use think\facade\Session;
use app\common\redis\CommomRedis;
use think\facade\View;
use think\facade\Request;
use think\facade\Db;

class Report extends Base
{	
    /*
     * 获取用户数量
     * @param $where 条件
     */
    public function getUserCount($where){
        $count = UserModel::where($where)->count();
        return $count;
    }
    
    public function queryData(){
        $param = Request::param();
        $CommomRedis=new CommomRedis();
        $key = isset($param['key'])?$param['key']:'';

        if(!empty($key)){
             /*当天*/
            if (isset($param['date']) && $param['date']!='') {
                $date=explode(' - ',$param['date']);
            }else{
                $date = [date('Y-m-d'), date('Y-m-d')];
            }
            $today_strat_time = Utils::getQueryDateTime($date[0]);
            $today_end_time = Utils::getQueryDateTime($date[1], true);
            /*本月*/
            $today_strat_date = date('Ymd', $today_strat_time);
            $today_end_date = date('Ymd', $today_end_time);
            $BeginDate = date('Y-m-01', strtotime(date("Y-m-d")));
            $month_strat_time = strtotime($BeginDate.'00:00:00');
            $month_end_time = strtotime(date('Y-m-d', strtotime("$BeginDate +1 month -1 day"))." 23:59:59");
            
            $where_month = $where_total = $where_day = $where_day1 = [];
            $where_day[0]=["a.add_time",">=", $today_strat_time];
            $where_day[1]=["a.add_time","<=", $today_end_time];
            $where_day[]=["a.lebal","=",1];//正式用户
            
            $where_day1[0]=["a.update_time",">=", $today_strat_time];
            $where_day1[1]=["a.update_time","<=", $today_end_time];
            $where_day1[]=["a.lebal","=",1];//正式用户

            $where_month[0]=["a.add_time",">=", $month_strat_time];
            $where_month[1]=["a.add_time","<=", $month_end_time];
            $where_month[]=["a.lebal","=",1];//正式用户
			
            $where_month1[0]=["a.update_time",">=", $month_strat_time];
            $where_month1[1]=["a.update_time","<=", $month_end_time];
            $where_month1[]=["a.lebal","=",1];//正式用户
            
            $where_total[]=["a.lebal","=",1];//正式用户
            
            $whereStr = '';
            $agent = $this->user;
            $sql = UserModel::where([['tree','like', $agent['tree'].'%']])->field('id')->buildSql();
            $whereStr = 'a.uid in ('.$sql .')';
            $total = 0;
            $cachekey = "promote_v5:".$agent['id'] . ":{$key}:" . md5(json_encode([$today_strat_time, $today_end_time]));
            if($key == "TotalUser"){
                if (!$CommomRedis->has($cachekey)) {
                    $total = $this->getUserCount([['lebal', '=', 1],['tree','like', $agent['tree'].'%']]); //获取正常用户总数
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            if($key == "TotalUser1"){
                if (!$CommomRedis->has($cachekey)) {
                    $total = $this->getUserCount([['add_time', ">=", $today_strat_time], ['add_time', "<=", $today_end_time], ['lebal', '=', 1],['tree','like', $agent['tree'].'%']]); //注册人数
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            if($key == "TotalUser2"){
                if (!$CommomRedis->has($cachekey)) {
                    $agent_wh = [];
                    $agent_wh[] = ['tree','like', $agent['tree'].'%'];
                    $sql = UserModel::where($agent_wh)->field('id')->buildSql();
                    $total = MemberService::getActiveUserNum([['login_time', ">=", $today_strat_time], ['login_time', "<=", $today_end_time], ['uid','exp',Db::raw('in'.$sql)]]); //活跃人数
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            if($key == "TotalRemainMoney"){
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserModel::where([['lebal', '=', 1],['tree','like', $agent['tree'].'%']])->sum('balance'); // 获取盘内总余额  不包含测试用户
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalRechargeUser"){ // 充值人数 所有
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserOfflineModel::getRechargeUserCount($where_total, $whereStr);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            if($key == "TotalRechargeUser1"){ // 充值人数 今天
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserOfflineModel::getRechargeUserCount($where_day1, $whereStr);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalRechargeUser2"){ // 首充人数 今天
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserOfflineModel::getRechargeUserCount($where_day1, $whereStr." and a.first=1");
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalRechargeUser3"){ // 复充人数 今天
                if (!$CommomRedis->has($cachekey)) {
                    $total = (new UserOfflineService())->getAgainRechargeAmount($where_day1, $cachekey, $whereStr);
//                    $total1 = UserOfflineModel::getRechargeUserCount($where_day1, $whereStr);
//                    $total2 = UserOfflineModel::getRechargeUserCount($where_day1, $whereStr." and a.first=1");
//                    $total = $total1 - $total2;
//                    $total = UserOfflineModel::getRechargeUserCount($where_day1, $whereStr." and a.first=2");
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }

            if($key == "TotalFirstRecharge"){ // 首充充值总额 今天
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserOfflineModel::getRechargeUserAmount($where_day1, $whereStr." and a.first=1");
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalRecharge1"){ // 线上充值 今天
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserOfflineModel::getRechargeUserAmount($where_day1, $whereStr, 2);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalRecharge2"){ // 线下转帐 今天
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserOfflineModel::getRechargeUserAmount($where_day1, $whereStr, 1);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalRecharge3"){ // USDT充值 今天
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserOfflineModel::getRechargeUserAmount($where_day1, $whereStr, 3);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalRecharge4"){ // 充值总额 今天
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserOfflineModel::getRechargeUserAmount($where_day1, $whereStr);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalRecharge5"){ // 充值总额 本月
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserOfflineModel::getRechargeUserAmount($where_month1, $whereStr);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "totalUserBalance"){ // 用户总余额
                if (!$CommomRedis->has($cachekey)) {
                    $total = MemberService::getAllBalance($this->user_id, true, $where_day);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }

            if($key == "TotalRecharge6"){ // 充值总额 总计
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserOfflineModel::getRechargeUserAmount([['a.lebal', '=', 1]], $whereStr);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalWithdraw1"){ // 提现总额 今天
                if (!$CommomRedis->has($cachekey)) {
                    $recharge = UserWithdrawalModel::getWithdrawUserAmount($where_day1, $whereStr);
                    $total = $recharge[2];
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalWithdraw2"){ // 提现总额 本月
                if (!$CommomRedis->has($cachekey)) {
                    $recharge = UserWithdrawalModel::getWithdrawUserAmount($where_month1, $whereStr);
                    $total = $recharge[2];
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalWithdraw3"){ // 提现总额 总计
                if (!$CommomRedis->has($cachekey)) {
                    $recharge = UserWithdrawalModel::getWithdrawUserAmount([['a.lebal', '=', 1]], $whereStr);
                    $total = $recharge[2];
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalWithdrawFee1"){ // 提现手续费 今天
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserWithdrawalModel::getWithdrawFeeAmount($where_day1, $whereStr);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalWithdrawFee2"){ // 提现手续费 本月
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserWithdrawalModel::getWithdrawFeeAmount($where_month1, $whereStr);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
            
            if($key == "TotalWithdrawFee3"){ // 提现手续费 总计
                if (!$CommomRedis->has($cachekey)) {
                    $total = UserWithdrawalModel::getWithdrawFeeAmount([['a.lebal', '=', 1]], $whereStr);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }

            $whereA = [
                // 分佣状态 0无效（不予分佣） 1未支付 2已支付
//                ["sff_payed", "IN", [1,2]],
                ["sff_uid", "=", $this->user['id']],
                ["sff_statstime",">=", $today_strat_time],
                ["sff_statstime","<=", $today_end_time]
            ];
            if($key == "TotalProfitAndLoss1"){ // 总客损 今天
                if (!$CommomRedis->has($cachekey)) {
                    $total = AgentService::getFySum("sff_ks", $whereA);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }

            
            if($key == "TotalGiveMoney"){ // 优惠
                if (!$CommomRedis->has($cachekey)) {
                    $total = AgentService::getFySum("youhui", $whereA);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }

            if($key == "BetTotal"){
                if (!$CommomRedis->has($cachekey)) {
                    $whereB = [
                        ["tree", "LIKE", "{$agent['tree']}%"],
                        ["su_dt",">=", $today_strat_date],
                        ["su_dt","<=", $today_end_date]
                    ];
                    $total = AgentService::getBetsSum("su_bet", $whereB);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }

            if($key == "BetUsersTotal"){
                if (!$CommomRedis->has($cachekey)) {
                    $whereB = [
                        ["tree", "LIKE", "{$agent['tree']}%"],
                        ["su_dt",">=", $today_strat_date],
                        ["su_dt","<=", $today_end_date]
                    ];
                    $total = AgentService::getBetsUsers($whereB);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }

            if($key == "TotalAgentCommission1"){ // 代理佣金 今天
                if (!$CommomRedis->has($cachekey)) {
                    $whereA[] = ["sff_payed", "IN", [1,2]];
                    $total = AgentService::getFySum("sff_fy", $whereA);
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }

            //贡献度
            if( $key == "ContributionRate" ){
                if (!$CommomRedis->has($cachekey)) {
                    //总站的投注数据
                    $totalCondition[] = ['su_dt', 'between', [$today_strat_date, $today_end_date]];
                    $table = BetService::getThirdStatsTable();
                    $totalValidBet = Db::name($table['_stats_global'])
                        ->where($totalCondition)
                        ->sum('su_valid_bet')?:0;
                    //团队有效投注
                    $_condition = [];
                    $_condition[] = ['su_dt', 'between', [$today_strat_date, $today_end_date]];
                    $teamValidBet = Db::name($table['_stats_global'])
                        ->where($_condition)
                        ->where('su_uid in ' . $sql)
                        ->sum('su_valid_bet');
                    //有效投注比例
                    $total = '0%';
                    if( $totalValidBet > 0 && $teamValidBet ){
                        $total = round($teamValidBet/$totalValidBet*100,2).'%';
                    }
                    $CommomRedis->set($cachekey, $total, 600);
                }else{
                    $total = $CommomRedis->get($cachekey);
                }
            }
                
            $rsps['data']=$total;
            $rsps['code']=1;
            return Respons::run($rsps);
        }
    }
    
    public function clear_cache(){
        $param = Request::param();
        $CommomRedis=new CommomRedis();
        $type = isset($param['type'])?$param['type']:0;
        if($type == 1){
            $CommomRedis->delete("operation_summary".$param['date']);
        }else{
            /*当天*/
            if (isset($param['date']) && $param['date']!='') {
                $date=explode(' - ',$param['date']);
                $today_strat_time = strtotime($date[0].'00:00:00');
                $today_end_time = strtotime($date[1].'23:59:59');
            }else{
                $today_strat_time = strtotime(date('Y-m-d').'00:00:00');
                $today_end_time = strtotime(date('Y-m-d').'23:59:59'); 
            }
            $agent = $this->user_agent;
            $cachekey = "agent".$agent['id'];
            $CommomRedis->delete($cachekey);
        }
        $rsps['code']=1;
        return Respons::run($rsps);
    }
    
}
