<?php

namespace app\common\service;

use app\common\lib\Util;
use app\common\model\BankSkModel;
use app\common\model\GameTypeModel;
use app\common\model\PaySetModel;
use app\common\model\TaskModel;
use app\common\model\ThirdStatsGloBalModel;
use app\common\model\UserLevelModel;
use app\common\model\UserModel;
use app\common\model\UserTagModel;
use app\common\Utils;
use Carbon\Carbon;
use think\Exception;
use think\facade\Db;

class ReportDownloadService{

    //获取提现
    public static function getWithdrawal($value,$row){
        return MemberService::getWithdrawByUid($value);
    }
    //获取充值
    public static function getRecharge($value,$row){
        return MemberService::getRechargeByUid($value);
    }
    //获取顶级id
    public static function getTopId($value,$row){
        return MemberService::getTopAgentByTree($value, true);
    }
    //团队下注总数ID,tree
    public static function teamBetTotal($value,$row){
        $agent_sql = unLevelSql($value, 0, 0, $row['tree']);
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        return Db::name($table['_stats_global'])->where("su_uid in(".$agent_sql.")")->sum('su_bet');
    }
    //新注册人数（一天）
    public static function newRegUser($value,$row){
        $start_time = Carbon::now()->startOfDay()->timestamp;
        $end_time = Carbon::now()->endOfDay()->timestamp;
        $condition = [];
        $condition[] = ["lebal","=",1];//正式用户
        $condition[]=["add_time",">=", $start_time];
        $condition[]=["add_time","<=", $end_time];
        return (new UserModel())->where("tree like '".$value."%'")->where($condition)->count();
    }

    //获取用户回访状态
    public static function televisedStatus($value,$row){
        $timeout = Db::name('distribution_config')->where('id', 1)->value('timeout');
        $timeouts = $row['assign_time'] + ($timeout * 3600);
        if ($row['televised_status'] == 1 && time() > $timeouts) {
            $row['televised_status'] = 4;
        } else if ($row['televised_status'] == 2 && $row['login_time'] < $row['televised']) {
            $row['televised_status'] = 5;
        }
        $televised_status = ['未安排','待电访','已电访','已电访2次以上','待电访(已超时)','已电访(未回归)'];
        return $televised_status[$row['televised_status']];
    }

    //获取数据来源
    public static function getChannelName($value,$row){
        $channel = Util::getOpenChannel();
        return isset($channel[$row['channel']])?lang($channel[$row['channel']]):"";
    }
    //获取总代
    public static function getUsernameByUid($value,$row){
        return MemberService::getUsernameByUid($row['rec_id']);
    }
    //获取上级
    public static function getTopAgentByTree($value,$row){
        return MemberService::getTopAgentByTree($row['tree']);
    }
    //获取支付渠道
    public static function getPayChannel($value,$row){
        $pay_arr = [];
        if(empty($value)){
            return "";
        }
        try {
            $pay_list = PaySetModel::field('id, pay_name')->select();
            foreach ($pay_list as $va) {
                $pay_arr[$va['id']] = $va;
            }
            return isset($pay_arr[$row['pay_id']]['pay_name']) ? $pay_arr[$row['pay_id']]['pay_name']:'';
        }catch (Exception $e){
            return "";
        }

    }

    //获取用户银行卡信息
    public static function getBankInfo($value,$row){
        if($row['type'] == 1){
            $bank_info = "";
        }else{
            $bank_info = lang('银行名称').':'.$row['bankname']." ".lang("银行卡号").":".$row['bankcrad']." "." ".lang("户名").':'.$row['realname'];
        }
        return $bank_info;
    }

    //获取收款银行信息
    public static function getSysInfo($value,$row){
        $bank_arr = [];
        $bank_list = BankSkModel::field('id, bank_number, bank_open, bank_khm, bank_name')->select();
        foreach ($bank_list as $val) {
            $bank_arr[$val['id']] = $val;
        }
        if($row['type'] == 1){
            $sys_info = "";
        }else{
            $bank_name = (isset($bank_arr[$row['bank_sk_id']]['bank_name']) ? $bank_arr[$row['bank_sk_id']]['bank_name']:'');
            $bank_number = (isset($bank_arr[$row['bank_sk_id']]['bank_number']) ? $bank_arr[$row['bank_sk_id']]['bank_number']:'');
            $bank_open = (isset($bank_arr[$row['bank_sk_id']]['bank_open']) ? $bank_arr[$row['bank_sk_id']]['bank_open']:'');
            $bank_khm = (isset($bank_arr[$row['bank_sk_id']]['bank_khm']) ? $bank_arr[$row['bank_sk_id']]['bank_khm']:'');
            $sys_info=lang('银行名称').':'.$bank_name." ".lang("银行卡号").":".$bank_number." ".lang('开户行').':'.$bank_open." ".lang("户名").':'.$bank_khm;
        }
        return $sys_info;
    }

    //获取用户标签
    public static function getLebal($value,$row){
        return ($row['lebal']==1?lang('一般账号'):lang('测试账号'));
    }

    //获取提现信息
    public static function getWithdrawalInfo($value,$row){
        if($row['channel'] == 0 ){
            $bank_info=lang('银行名称')."：".$row['bank_name']." ".lang('银行卡号')."：".$row['bank_number']." ".lang('开户行')."：".$row['bank_open']." ".lang('户名')."：".$row['bank_hm'];
        }elseif($row['channel'] == 1){
            $bank_info=lang('链名称')."：".$row['bank_name']." ".lang('收币地址')."：".$row['bank_number'];
        }elseif($row['channel'] == 2 || $row['channel'] == 3){
            $bank_info=lang('钱包名称')."：".$row['bank_name']." ".lang('钱包地址')."：".$row['bank_number'];
        }
        return $bank_info;
    }

    //获取代理用户
    public static function getUserName($value,$row){
        if(!empty($row['phone'])){
            return $row['phone'];
        }
        if(!empty($row['mail'])){
            return $row['mail'];
        }
        return "";
    }

    //获取返佣周期
    public static function getWeekText($value,$row){
        if (strlen($row['sff_w']) == 6) {
            // 获取指定年份的第 N 周的第一天和最后一天
            $year = substr($row['sff_w'], 0, 4);
            $week2 = substr($row['sff_w'], 4);
            $startDate = Carbon::parse("{$year}-W{$week2}-1")->startOfDay()->format("Y-m-d");
            $endDate = Carbon::parse("{$year}-W{$week2}-7")->endOfDay()->format("d");
            return "{$startDate}～{$endDate}";
        }
        return "";
    }

    //获取会员等级
    public static function getUserLevel($value,$row){
        $user_level_data = UserLevelModel::column('level_name', 'id');
        return $user_level_data[$row['level_id']];
    }

    //获取代理类型
    public static function getAgencyType($value,$row){
        if($row['type']==0){
            return lang('外部代理');
        }
        return lang('内部代理');
    }

    //获取返佣比例
    public static function getAgencyRate($value,$row){
        if($row['type']==1){
            return $row['rate']."%";
        }
        return "";
    }

    //获取游戏类型
    public static function getGameType($value,$row){
        $game_type = GameTypeModel::column("name","id");
        return isset($game_type[$row['sb_gtid']])?$game_type[$row['sb_gtid']]:'';
    }

    //获取vip等级
    public static function getVips($value,$row){
        return "VIP".($row['sb_viplvl']-1);
    }

    //获取卡片名称
    public static function getCName($value,$row){
        if( !$row['c_name'] ){
            if( $row['card_id'] == 0 ){
                $c_name = '集齐所有兑换';
            }else{
                $c_name = '已删除';
            }
            return $c_name;
        }
        return "";
    }
    //卡片记录-卡片名称
    public static function getCardName($value,$row){
        if( !$row['card_name'] ){
            if( $row['card_id'] == -1 ){
                $c_name = '未中奖';
            }elseif ( $row['card_id'] == 0 ){
                $c_name = '未抽奖';
            }else{
                $c_name = '已删除';
            }
            return $c_name;
        }
        return "";
    }

    //获取任务条件
    public static function getTaskCondition($value,$row){
        $condition = TaskModel::getTaskTypeArr();
        if(empty($row['task_condition'])){
            return "";
        }
        $con_str ="";
        if(strpos($row['task_condition'],',')>0){
            $con_arr = explode(',',$row['task_condition']);
            for($i=0;$i<count($con_arr);$i++){
                $con_str .= $condition[intval($con_arr[$i])].",";
            }
            return $con_str;
        }else{
            return $condition[intval($row['task_condition'])];
        }
    }

    //获取日期转化
    public static function getDateStr($value,$row){
        if(empty($row['su_dt'])){
            return "";
        }
        return date("Y-m-d", strtotime($row['su_dt']));
    }

    //获取用户标签
    public static function getUserLabelByUser($value,$row){
        $userTagStrArr = [];
        $lebalArr = [0 => '测试用户', 1 => '普通用户'];
        if( isset($lebalArr[$row['lebal']]) ){
            $userTagStrArr[] = $lebalArr[$row['lebal']];
        }
        //获取用户设置的标签
        $userTagArr = MemberService::getTagByUid($row['id']);
        foreach ($userTagArr as $v){
            $userTagStrArr[] = $v['name'];
        }
        return implode('|', $userTagStrArr);
    }
}