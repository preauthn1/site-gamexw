<?php

namespace app\manage\factory;

use app\api\factory\UserFactory;
use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\Uploads;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\ActionApplyModel;
use app\common\model\AgentSellLogModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\SignRecordModel;
use app\common\model\TaskModel;
use app\common\model\ThirdStatsGloBalModel;
use app\common\model\UserAgentModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\service\AgentService;
use app\common\service\MemberService;
use app\common\service\UserOfflineService;
use app\common\Utils;
use think\facade\Db;
use think\facade\Request;

class AgentReportFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function lists()
    {
        $param = Request::param();
        $page=$param['page'] ?? 1;
        $limit=$param['limit'] ?? 10;
        $where=[];
        $UserModel =new UserModel();
        if (isset($param['username']) && $param['username']!='') {
            $param['username'] = trim($param['username']);
            if (is_numeric($param['username'])) {
                $where[]=["a.id|a.phone","=", $param['username']];
            } else {
                $where[]=["a.username|a.mail|a.phone","=", $param['username']];
            }
        }

        if (isset($param['rec_code']) && $param['rec_code']!='') {
            $where[]=["a.rec_code","=",$param['rec_code']];
        }

        if( isset($param['agent_type']) && $param['agent_type'] !== '' ){
            $where[] = ['b.type', '=', $param['agent_type']];
        }

        $whereStr1 = '';
        if( isset($param['min_recharge']) && $param['min_recharge'] !== '' && isset($param['max_recharge']) && $param['max_recharge'] !== '' ){
            //有最大金额和最小金额
            $sql = UserOfflineModel::where('status', 'in', '3,4')
                ->having( 'sum(arrival_price) between '.$param['min_recharge'].' and '.$param['max_recharge'])
                ->group('uid')->field('uid')->buildSql();
            $whereStr1 = 'a.id in('.$sql.')';
        }elseif(isset($param['min_recharge']) && $param['min_recharge'] !== ''){
            //只有最小金额
            $sql = UserOfflineModel::where('status', 'in', '3,4')
                ->having('sum(arrival_price) >= ' . $param['min_recharge'])
                ->group('uid')->field('uid')->buildSql();
            $whereStr1 = 'a.id in('.$sql.')';
        }elseif(isset($param['max_recharge']) && $param['max_recharge'] !== ''){
            //只有最大金额
            $sql = UserOfflineModel::where('status', 'in', '3,4')
                ->having('sum(arrival_price) <= ' . $param['max_recharge'])
                ->group('uid')->field('uid')->buildSql();
            $whereStr1 = 'a.id in('.$sql.')';
        }
        $where_str = '';
        if (isset($param['id']) && !empty($param['id'])) {
            $rec_user = UserModel::where("id",$param['id'])->find();//查看用户是否存在
            if (!isset($rec_user['id'])) {
                return Respons::run(0,[],lang('账号不存在'));
            }
            $agent_sql = unLevelSql($rec_user['id'], $rec_user['rec_level'], 1);
            $where_str = "a.id in(".$agent_sql.")";
        }else{
//            $where[] = ["a.rec_id","=",0];//获取顶级代理
//            $where[]=["b.status","=",3];//通过的代理
        }
        $start_time = $end_time = '';
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
        }else{
            $date = [date("Y-m-d"), date("Y-m-d")];
        }
        $start_time = Utils::getQueryDateTime($date[0]);
        $end_time = Utils::getQueryDateTime($date[1], true);

        $where_s[] = ["a.lebal","=",1];//正式用户
        $where_s1[] = ["a.lebal","=",1];//正式用户
        $where_dt = [];
        if(!empty($end_time) && !empty($start_time)){
            $where_s[0]=["a.add_time",">=", $start_time];
            $where_s[1]=["a.add_time","<=", $end_time];
            $where_s1[0]=["a.update_time",">=", $start_time];
            $where_s1[1]=["a.update_time","<=", $end_time];
        }

        //导出必须要在查询条件之上
        if (isset($param['export']) && $param['export'] == 1) {
            return $this->exportList($where, $whereStr1);
        }
        if (isset($param['id']) && !empty($param['id'])) {
            $rsps['count']=$UserModel->alias("a")->where($where)->where($where_str)->where($whereStr1)->count();
            $data = $UserModel->alias("a")->where($where)->where($where_str)->where($whereStr1)->limit((($page-1)*$limit),$limit)->order("id desc")->select();
        }else{
//            halt($where);
            $rsps['count']=$UserModel->alias("a")->join("dq_user_agent b","a.id=b.uid")->where($whereStr1)->where($where)->group('b.uid')->count();
            $data = $UserModel->alias("a")->join("dq_user_agent b","a.id=b.uid")->where($whereStr1)->where($where)->limit((($page-1)*$limit),$limit)->order("id desc")->field("a.*, b.type")->select();
        }
        $inviteStartTIme = 0;
        $inviteEndTIme = 99999999999;
        if( isset($param['invite_time']) && $param['invite_time'] !== '' ){
            $timeData = explode(' - ', $param['invite_time']);
            $inviteStartTIme = strtotime($timeData[0]);
            $inviteEndTIme = strtotime($timeData[1]);
            $where_s[0]=["a.add_time",">=", $inviteStartTIme];
            $where_s[1]=["a.add_time","<=", $inviteEndTIme];
            $where_s1[0]=["a.update_time",">=", $inviteStartTIme];
            $where_s1[1]=["a.update_time","<=", $inviteEndTIme];
            $where_dt[0]=["su_dt",">=", date('Ymd', $inviteStartTIme)];
            $where_dt[1]=["su_dt","<=", date('Ymd', $inviteEndTIme)];
        }

        $arr=[];

        foreach ($data as $key => $value) {
            $agent_sql = unLevelSql($value['id'], 0, 0, $value['tree']);
            $user = $UserModel->where("id", $value['id'])->find();
            $arr[$key]['count_pnumber'] = $UserModel->where("tree like '".$value['tree']."%'")->count() - 1;#减一出去本身
            $arr[$key]['new_reg'] = $UserModel->alias('a')->where("tree like '".$value['tree']."%'")->where($where_s)->count();
            $arr[$key]['rec_code']=$value['rec_code'];
            $arr[$key]['username']=$value['username'];
            $arr[$key]['mail']=$value['mail'];
            $arr[$key]['phone']=$value['phone'];
            $arr[$key]['uid']=$value['id'];
            $arr[$key]['type']=$value['type'];
            if (isset($param['id']) && !empty($param['id'])) {
                $arr[$key]['recharge_pnumber'] = UserOfflineModel::getRechargeUserCount($where_s1, "uid in(".$agent_sql.")");
                $recharge = UserOfflineModel::getRechargeUserAmount($where_s1, "uid in(".$agent_sql.")");
                $arr[$key]['recharge_amount'] = $recharge;
                $arr[$key]['first_recharge_pnumber'] =  UserOfflineModel::getRechargeUserCount($where_s1, "uid in(".$agent_sql.") and a.first=1");
                $arr[$key]['first_recharge_zb'] = round((($arr[$key]['first_recharge_pnumber']!=0?$arr[$key]['first_recharge_pnumber']/$arr[$key]['recharge_pnumber']:0)*100),2)."%";
                $arr[$key]['two_recharge_pnumber'] =  $arr[$key]['recharge_pnumber'] - $arr[$key]['first_recharge_pnumber'];
                $arr[$key]['two_recharge_zb']=round((($arr[$key]['two_recharge_pnumber']!=0?$arr[$key]['two_recharge_pnumber']/$arr[$key]['recharge_pnumber']:0)*100),2)."%";
                $arr[$key]['two_recharge_pnumber2'] =  UserOfflineService::getAgainRechargeAmount2($where_s1,"TotalRechargeUser2_3_agent","uid in(".$agent_sql.")");
                $arr[$key]['two_recharge_zb2']=round((($arr[$key]['two_recharge_pnumber2']!=0?$arr[$key]['two_recharge_pnumber2']/$arr[$key]['recharge_pnumber']:0)*100),2)."%";
                $arr[$key]['withdrawal_pnumber'] =  UserWithdrawalModel::getWithdrawUserCount($where_s1, "uid in(".$agent_sql.")");
                $withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where_s1, "uid in(".$agent_sql.")");
                $arr[$key]['withdrawal_amount'] = $withdrawal[2];
                $arr[$key]['max_level'] = $value['rec_level'] - $rec_user['rec_level'];
                $is_agnet = UserAgentModel::where("uid",$value['id'])->where("status",3)->count();
                $arr[$key]['is_agnet'] = $is_agnet>0?true:false;
            }
            //下级有效总人数
            $arr[$key]['total_valid_people'] = UserFactory::getUserInvitePerson($value['id'], $inviteStartTIme, $inviteEndTIme);
            //提现总余额
            $arr[$key]['total_withdrawal'] =  UserWithdrawalModel::getWithdrawArrivalUserAmount($where_s1, "uid in(".$agent_sql.")")[0];
            $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
            //游戏下注
            $arr[$key]['game_bets'] = Db::name($table['_stats_global'])->where("su_uid in(".$agent_sql.")")->where($where_dt)->sum('su_bet');
        }
        if (isset($param['export']) && $param['export'] == 1) {
            $list = $arr;
            $header = [
                'phone' => lang('手机号'),
                'mail' => lang('邮箱'),
                'rec_code' => lang('推广码'),
                'type' => lang('代理类型'),
                'count_pnumber' => lang('下级总人数'),
                'total_valid_people' => lang('下级有效总人数'),
                'total_withdrawal' => lang('总提现'),
                'game_bets' => lang('游戏下注'),
                'new_reg' => lang('新注册人数'),
            ];
            $file_name = genCsv($header, $list,lang('微商报表').date('YmdHis',time()), false);
            $s3_url = Uploads::uploadS3(public_path() . $file_name);
            if (is_null($where)) {
                Utils::addOptLogByHandle('微商报表导出');
            }
            $result['data'] = $s3_url ?: toMedia($file_name, '/');
            $result['code']=1;
            return Respons::run($result);
        }
        $rsps['data']=$arr;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function exportList($where=null, $where1 = null, $where2 = null){
        $params=Request::param();
        $params['where']=$where;
        $params['where1']=$where1;
        $params['where2']=$where2;
        $params['positionField']='a.id';
        $params['positionOrder']='asc';
        $params['muid']=$this->uid;
        $rlt=ReportExport::export(new rptParams('代理报表导出',
            [
                'id:UID',
                'phone:手机号',
                'qh:区号',
                'mail:邮箱',
                'rec_code:推广码',
                new rptCol('tree','下级总人数','app\manage\factory\AgentReportFactory::childTotalPeople'),
                new rptCol('id','下级有效总人数','app\manage\factory\AgentReportFactory::childValidTotalPeople'),
                new rptCol('id','总提现','app\common\service\ReportDownloadService::getWithdrawal'),
                new rptCol('tree','佣金总数','app\manage\factory\AgentReportFactory::feeBalanceTotal'),
                new rptCol('id','游戏下注','app\common\service\ReportDownloadService::teamBetTotal'),
                new rptCol('tree','新注册人数','app\common\service\ReportDownloadService::newRegUser'),
            ],
            __CLASS__,'exportListMethod',
            $params,
            ['phone_bind_status','mail_bind_status','is_recharge','level', 'login_time'],
            'a.id','asc'
        ));
        if ($rlt){
            return Respons::run(1);
        }else{
            return Respons::run(0,[],'创建导出任务失败');
        }
    }

    //导出代理查看列表
    public function exportListUser($where=null, $whereStr = '',$whereDate = ''){
        $params=Request::param();
        $params['where']=$where;
        $params['whereStr'] = $whereStr;
        $params['positionField']='a.id';
        $params['positionOrder']='desc';
        $params['muid']=$this->uid;
        $rlt=ReportExport::export(new rptParams('代理报表-查看记录',
            [
                'id:UID',
                new rptCol('id','用户账号','app\manage\factory\AgentReportFactory::getUserName'),
                'rec_level:所属层级',
                'rec_code:推广码',
                new rptCol('rec_id','推广用户','app\manage\factory\AgentReportFactory::getRecUserName'),
                'balance:余额',
                new rptCol('id','充值总额','app\manage\factory\AgentReportFactory::getRecharge'),
                new rptCol('id','提现总额','app\manage\factory\AgentReportFactory::getWithdrawal'),
                new rptCol('id','投注数量','app\manage\factory\AgentReportFactory::getBetNum',$whereDate),
                new rptCol('id','投注总额','app\manage\factory\AgentReportFactory::getSuBet',$whereDate),
                new rptCol('id','派彩总额','app\manage\factory\AgentReportFactory::getSuWin',$whereDate),
                new rptCol('id','游戏盈亏','app\manage\factory\AgentReportFactory::getBetWinLess',$whereDate),
                new rptCol('id','人工增加','app\manage\factory\AgentReportFactory::getArtificialInc',$whereDate),
                new rptCol('id','人工减少','app\manage\factory\AgentReportFactory::getArtificialDec',$whereDate),
                new rptCol('id','推广奖励','app\manage\factory\AgentReportFactory::getPromotionalRebates',$whereDate),
                new rptCol('id','活动优惠','app\manage\factory\AgentReportFactory::getActivityOffer',$whereDate),
                new rptCol('id','赠送彩金','app\manage\factory\AgentReportFactory::getGiveBonuses',$whereDate),
                new rptCol('id','转盘奖励','app\manage\factory\AgentReportFactory::getLottery',$whereDate),
                new rptCol('id','任务奖励','app\manage\factory\AgentReportFactory::getTaskReward',$whereDate),
                new rptCol('id','周月奖励','app\manage\factory\AgentReportFactory::getLevelReward',$whereDate),
                new rptCol('id','游戏反水','app\manage\factory\AgentReportFactory::getGameBack',$whereDate),
                new rptCol('id','签到奖励','app\manage\factory\AgentReportFactory::getSignReward',$whereDate),
                new rptCol('id','代理返佣','app\manage\factory\AgentReportFactory::getAgentFyfy',$whereDate),
                new rptCol('add_time', '注册时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
            ],
            __CLASS__,'exportListMethodUser',
            $params,
            ['rec_level','rec_id'],
            'a.id','desc'
        ));
        if ($rlt){
            return Respons::run(1);
        }else{
            return Respons::run(0,[],'创建导出任务失败');
        }
    }

    //获取用户账号
    public static function getUserName($val, $row){
        $user = UserModel::where("id", $row['id'])->field("username,mail,phone")->find();
        if(empty($user)){
            return "";
        }else{
            if(!empty($user['mail'])){
                return $user['mail'];
            }
            if(!empty($user['phone'])){
                return $user['phone'];
            }
            if(!empty($user['username'])){
                return $user['username'];
            }
            return "";
        }
    }

    //获取推广用户
    public static function getRecUserName($val, $row){
        $user = UserModel::where("id", $row['rec_id'])->field("username,mail,phone")->find();
        if(empty($user)){
            return "";
        }else{
            if(!empty($user['mail'])){
                return $user['mail'];
            }
            if(!empty($user['phone'])){
                return $user['phone'];
            }
            if(!empty($user['username'])){
                return $user['username'];
            }
            return "";
        }
    }

    //获取充值总额
    public static function getRecharge($val, $row){
        return MemberService::getRechargeByUid($row['id']);// 充值总额
    }

    //获取提现总额
    public static function getWithdrawal($val, $row){
        return MemberService::getWithdrawByUid($row['id']);
    }

    //投注数量
    public static function getBetNum($val, $row,$dateArr){
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        return Db::name($table['_stats_global'])->where('su_uid', '=', $row['id'])->where('su_dt', 'between', $dateArr)->sum('su_betnum');//投注数量
    }

    //投注总额
    public static function getSuBet($val, $row,$dateArr){
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        return Db::name($table['_stats_global'])->where('su_uid', '=', $row['id'])->where('su_dt', 'between', $dateArr)->sum('su_bet');//投注总额
    }
    //派彩总额
    public static function getSuWin($val, $row,$dateArr){
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        return Db::name($table['_stats_global'])->where('su_uid', '=', $row['id'])->where('su_dt', 'between', $dateArr)->sum('su_win');//派彩总额
    }
    //游戏盈亏
    public static function getBetWinLess($val, $row,$dateArr){
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        return Db::name($table['_stats_global'])->where('su_uid', '=', $row['id'])->where('su_dt', 'between', $dateArr)->sum(Db::raw('su_syswin*-1'));//游戏盈亏
    }

   //人工增加
    public static function getArtificialInc($val, $row,$dateArr){
        $whereAdd = [];
        $whereAdd[]=["add_time",">=", strtotime($dateArr[0])];
        $whereAdd[]=["add_time","<=", strtotime($dateArr[1])];
        return ArtificialBalanceModel::where(['uid', '=', $row['id']])->where("a_type",1)->where($whereAdd)->sum("price");//人工增加
    }

    //人工减少
    public static function getArtificialDec($val, $row,$dateArr){
        $whereAdd = [];
        $whereAdd[]=["add_time",">=", strtotime($dateArr[0])];
        $whereAdd[]=["add_time","<=", strtotime($dateArr[1])];
        return ArtificialBalanceModel::where(['uid', '=', $row['id']])->where("a_type",2)->where($whereAdd)->sum("price");//人工增加
    }
    //推广奖励
    public static function getPromotionalRebates($val, $row,$dateArr){
        $whereCreate = [];
        $whereCreate[]=["create_time",">=", strtotime($dateArr[0])];
        $whereCreate[]=["create_time","<=", strtotime($dateArr[1])];
        return AgentSellLogModel::where(['uid', '=', $row['id']])->where("status",3)->where($whereCreate)->sum("money");//推广奖励
    }
    //活动优惠
    public static function getActivityOffer($val, $row,$dateArr){
        return ActionApplyModel::alias("a")->join("dq_user b","a.uid=b.id")->where('b.id', '=', $row['id'])->where([['a.ps_deta', ">=",  strtotime($dateArr[0])], ['a.ps_deta', "<=",  strtotime($dateArr[1])], ['a.ps_status', '=', 1], ['b.lebal', '=', 1]])->sum(Db::raw('a.`gd_amount`+a.`ck_hl_amount`'));
    }

    //赠送彩金
    public static function getGiveBonuses($val, $row,$dateArr){
        $whereAdd = [];
        $whereAdd[]=["add_time",">=", strtotime($dateArr[0])];
        $whereAdd[]=["add_time","<=", strtotime($dateArr[1])];
       return ArtificialBalanceModel::where(['uid', '=', $row['id']])->where([["a_type", 'in', '3,4']])->where($whereAdd)->sum("price");//赠送彩金
    }

    //转盘奖励
    public static function getLottery($val, $row,$dateArr){
        return Db::name('lottery_record')->where(['uid', '=', $row['id']])->where('prize_type', 1)->whereBetweenTime("time", strtotime($dateArr[0]), strtotime($dateArr[1]))->sum("price");
    }
    //任务奖励
    public static function getTaskReward($val, $row,$dateArr){
        return Db::name('task_record')->where(['uid', '=', $row['id']])->whereBetweenTime("create_time", strtotime($dateArr[0]), strtotime($dateArr[1]))->where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)->sum('reward_amount_or_integral');
    }

    //VIP周月奖励
    public static function getLevelReward($val, $row,$dateArr){
        return Db::name('user_level_reward')->where(['uid', '=', $row['id']])->whereBetweenTime("create_time", strtotime($dateArr[0]), strtotime($dateArr[1]))->sum('reward_amount');
    }

    //游戏反水
    public static function getGameBack($val, $row,$dateArr){
        return Db::name('third_stats_back')
            ->where('sb_uid', '=', $row['id'])
            ->where('sb_dt', 'between',[$dateArr[0], $dateArr[1]])
            ->where([['sb_payed','=',2]])
            ->sum('sb_backmoney');//游戏反水
    }

    //签到奖励
    public static function getSignReward($val, $row,$dateArr){
        return SignRecordModel::where('is_receive_reward', '=', 1)
            ->where(['uid', '=', $row['id']])
            ->whereBetweenTime("create_time", strtotime($dateArr[0]), strtotime($dateArr[1]))
            ->sum('reward_amount');//签到奖励
    }

    //代理返佣
    public static function getAgentFyfy($val, $row,$dateArr){
        return Db::name('third_stats_fyfy')->where('sff_payed', '=', 2)
            ->where('sff_uid', '=', $row['id'])
            ->where('sff_w', 'between', [$dateArr[0], $dateArr[1]])
            ->sum('sff_fy');//代理返佣
    }

    //佣金总数
    public static function feeBalanceTotal($val, $row){
        //佣金总数
        return (new UserModel())->where("tree like '".$val."%'")->sum('feebalance');
    }

    //下级有效总人数
    public static function childValidTotalPeople($val, $row){
        //下级有效总人数
        return UserFactory::getUserInvitePerson($val);
    }

    //下级总人数
    public static function childTotalPeople($val, $row){
        return (new UserModel())->where("tree like '".$val."%'")->count() - 1;#减一出去本身
    }

    public static function exportListMethod($param)
    {
        return (new UserModel())->alias("a")
            ->join("dq_user_agent b","a.id=b.uid")
            ->where($param['where'])->where($param['where1'])
            ->field("a.*");
    }

    public static function exportListMethodUser($param)
    {
        return (new UserModel())->alias("a")
            ->where($param['where'])->where($param['whereStr'])
            ->field("a.*");
    }
}
