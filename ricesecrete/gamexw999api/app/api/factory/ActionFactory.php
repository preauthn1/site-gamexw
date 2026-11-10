<?php
namespace app\api\factory;
use app\common\model\ActionModel;
use app\common\model\ActionApplyModel;
use app\common\model\ActionTypeModel;
use app\common\model\GameBetsModel;
use app\common\model\GamesModel;
use app\common\model\IntegralMxModel;
use app\common\model\LotteryConditionRecordModel;
use app\common\model\LotteryNewsModel;
use app\common\model\TaskModel;
use app\common\model\TaskRecordModel;
use app\common\model\TaskStageRecordModel;
use app\common\model\UserAwardCfgsModel;
use app\common\model\UserAwardLogsModel;
use app\common\model\UserBankModel;
use app\common\model\UserLoginModel;
use app\common\model\UserLogModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\model\UserCkModel;
use app\common\model\UserOfflineModel;
use app\common\model\OlPayModel;
use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\model\UserTagModel;
use app\common\service\AgentService;
use app\common\service\BetService;
use app\common\service\LotteryService;
use app\common\service\MemberService;
use app\common\service\MessageService;
use app\common\service\ReportService;
use app\common\service\WalletService;
use app\common\Utils;
use Carbon\Carbon;
use think\facade\Request;
use think\facade\Db;
use app\common\model\LotteryConfigModel;
use app\common\model\LotteryRecordModel;
use app\common\model\LotteryPrizeModel;
use app\common\model\UserBalanceMxModel;

class ActionFactory
{
    private $param;//请求参数
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->param=Request::param();
        $this->uid=$uid;
    }

    //活动列表
    public function list_run(){
        $pagenow=$this->param['page']??1;
        $limit=$this->param['limit']??10;
        $is_hot=$this->param['is_hot']??"";
        $typeId = $this->param['type_id'] ?? 0;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $where = [];
        $where[] = ["a.t_show_start_time", "<=", time()];
        $where[] = ["a.t_show_end_time", ">=", time()];
        if(!empty($lang)){
            $where[] = ["b.lang", "=", $lang];
        }

        if($is_hot != ""){
            $where[] = ["a.is_hot", "=", $is_hot];
        }
        if( $typeId ){
            $where[] = ['a.type_id', '=', $typeId];
        }
        $where_str = []; $tree = -1;
        $isNeedZeroWhere = AgentService::judgeIsNeedZeroWhere($this->uid);
        $agentIdEqSql = AgentService::getAgentWhere('agent_id', true, 'a', true, '', $isNeedZeroWhere);
        $agentIdNoEqSql = AgentService::getAgentWhere('agent_id', true, 'a', false, '', $isNeedZeroWhere);
        //限定代理線 ->未登入前 不可查看、登入後 可查看
        //排除代理線 ->未登入前 可查看    、登入後 不可查看
        //無設置代理線->登入前後皆可查看

        if ($this->uid > 0) {
            $where_str[] = "(a.is_consist = 2 and ($agentIdEqSql)) or (a.is_consist = 1 and ($agentIdNoEqSql))";

            $uinfo = UserModel::where("id", $this->uid)->field("tree,level_id")->find();
            if(isset($uinfo)){
                $tree1 = explode("/", $uinfo['tree']);
                if(isset($tree1[1])){
                    $tree = $tree1[1];
                }
                $level_id = $uinfo['level_id'];
                $where_str[] = "(a.base_show_level='all' or a.base_show_level='{$level_id}')";
            }

            $user_tag_id = $user_tag_id1 = MemberService::getUserTagId($this->uid);
            $user_tag_id[] = 0;
            $user_tag_id1[] = -1;#加上这个是为了计算 NOT IN时不报错
            $user_tag_id_str = implode(',', $user_tag_id);
            $user_tag_id_str1 = implode(',', $user_tag_id1);
            $where_str[] = "(a.is_consist = 2 and a.tag_id IN ({$user_tag_id_str})) or (a.is_consist = 1 and a.tag_id NOT IN ({$user_tag_id_str1}))";
//            $where_str[] = "IF(a.base_type <> 6 ,a.tag_id IN ({$user_tag_id_str}),a.base_type = 6)";
            // $user_tag = UserTagModel::where(['uid'=>$this->uid])->value('tag_id') ??-1;

            $where_str[] = "(a.is_consist = 2 and (find_in_set($tree,a.agent_id) || a.agent_id = 0)) or (a.is_consist = 1 and !find_in_set($tree,a.agent_id))";
//            $where_str[] = "IF(a.base_type = 6 ,(a.is_consist = 2 and (".
        } else {
            $where_str[] = "a.tag_id = 0";
            $where_str[] = "a.base_show_level = 'all'";
            $where_str[] = "a.tag_id = 0";
            $uinfo = [];
            //排除代理線 ->未登入前 可查看    、登入後 不可查看
            $where_str[] = "(a.is_consist = 2 and ($agentIdEqSql)) or (a.is_consist = 1)";
        }
        $action = ActionModel::alias("a")
        ->join("dq_news b","a.id=b.action_id")
        ->where(implode(" and ", $where_str))
        ->where($where)
        ->field("a.id,a.base_type,a.type_id,a.auto_apply,a.base_name,a.dis_code,a.c_label,a.auto_apply,a.t_apply_start_time,a.t_apply_end_time, b.news_title as c_title, b.news_intro as c_intro, b.news_images as c_img, b.news_content as c_detail,a.is_hot,a.jump_url,a.date_type,a.game_type,a.level_json,a.reg_json,a.rebate_type,a.reward_max")
        ->limit((($pagenow-1)*$limit),$limit)
        ->order("a.sort DESC, a.id desc")
        ->select();

        foreach($action as &$val){
            if($val['base_type'] == 6){
                $val = $this->jyjdata($val,$uinfo);
            }
            $val['t_apply_start_time'] = Util::i18n_datetime2str($val['t_apply_start_time']);
            $val['t_apply_end_time'] = Util::i18n_datetime2str($val['t_apply_end_time']);
            unset($val['level_json'],$val['game_type'],$val['reg_json'],$val['rebate_type'],$val['reward_max']);
        }
        $rsps['data']=$action;
        $rsps['code']=1;
        $rsps['total']= ActionModel::alias("a")
            ->join("dq_news b","a.id=b.action_id")
            ->where(implode(" and ", $where_str))
            ->where($where)->count();
        $rsps['type_list'] = ActionTypeModel::field('id,name,icon')->order('sort desc,id desc')->select();
        return $rsps;
    }

    //活动详情
    public function detail_run(){
        $action_id = $this->param['action_id'];
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $where = [];
        $where[] = ["a.t_show_start_time", "<=", time()];
        $where[] = ["a.t_show_end_time", ">=", time()];
        if(!empty($lang)){
            $where[] = ["b.lang", "=", $lang];
        }

        $vip=UserModel::uinfo($this->uid);
        $action = ActionModel::alias("a")
                ->join("dq_news b","a.id=b.action_id")
                ->where("a.base_show_level='all' or a.base_show_level<=".$vip['level_id'])
                ->where($where)
                ->where("a.id",$action_id)
                ->field("a.id,a.base_type,a.base_name,a.auto_apply,a.dis_code,a.c_label,a.c_img,a.c_detail,a.t_apply_start_time,a.t_apply_end_time, b.news_title as c_title, b.news_intro as c_intro, b.news_images as c_img, b.news_content as c_detail,a.jump_url,a.date_type,a.game_type,a.level_json,a.reg_json,a.rebate_type,a.reward_max")
                ->find();
        if($action['base_type'] == 6){
            $action = $this->jyjdata($action,$vip);
        }
        $action['t_apply_end_time'] = Util::i18n_datetime2str($action['t_apply_end_time']);
        $action['t_apply_start_time'] = Util::i18n_datetime2str($action['t_apply_start_time']);
        unset($action['level_json'],$action['game_type'],$action['reg_json'],$action['rebate_type'],$action['reward_max']);
        $rsps['data']=$action;
        $rsps['code']=1;
        return $rsps;
    }


    //活动申请
    public function apply_run($params = [], $isJudge = false){
        $params = $params ?: $this->param;
        $action_id=$params['action_id'];
        $ActionModel=new ActionModel();
        $ActionApplyModel=new ActionApplyModel();
        $ApiRedis=new ApiRedis();
        //锁
        if (!$ApiRedis->lock($this->uid, 60 * 5) && !$isJudge) {
            ajax_return("请勿频繁访问", 0);
        }
        // 判断是否禁止参与活动
        if (MemberService::tagBan($this->uid, 'is_activity')) {
            $ApiRedis->unlock($this->uid);
            ajax_return("禁止参与活动，请联系客服", 0);
        }
        $user=UserModel::uinfo($this->uid);
        $action=$ActionModel
                ->alias("a")
                ->where("a.base_show_level='all' or a.base_show_level<=".$user['level_id'])
                ->where(time()." >= a.t_show_start_time and ".time()."<= a.t_show_end_time")
                ->where("a.id",$action_id)
                ->field("a.*")
                ->find();
        if (!isset($action['id'])) {
            $ApiRedis->unlock($this->uid);
            ajax_return("数据不存在", 0);
        }
        //双钱包判断当前用户是否可以重复领取优惠
        if( !WalletService::judgeUserIsRePeatReceive(0, $user) && $action['wallet_water_type'] == 1 ){
            $ApiRedis->unlock($this->uid);
            ajax_return('你的账户还存在彩金，请使用完后再来领取');
        }
        // 0 所有用户，1新用户，2老用户
        // 新用户为活动申请开始时间之后注册的用户，
        // 老用户为活动申请开始时间之前注册的用户
        if (isset($action['user_type']) && $action['user_type'] > 0) {
            if ($action['user_type'] == 1) {
                if ($user['add_time'] < $action['t_apply_start_time']) {
                    $ApiRedis->unlock($this->uid);
                    ajax_return("不符合活动申请条件", 0);
                }
            } else {
                if ($user['add_time'] > $action['t_apply_start_time']) {
                    $ApiRedis->unlock($this->uid);
                    ajax_return("不符合活动申请条件", 0);
                }
            }
        }
        if (!(time()>=$action['t_apply_start_time'] && time()<=$action['t_apply_end_time'])) {
            $msg=time()<=$action['t_apply_end_time']?'未开始':'已结束';
            $ApiRedis->unlock($this->uid);
            ajax_return("申请".$msg, 0);
        }
        if ($action['zg_level']!="all" && $user['level_id']!=$action['zg_level']) {
            $ApiRedis->unlock($this->uid);
            ajax_return("申请资格会员等级不满足", 0);
        }
        if ($action['zg_cz_type'] != "all") {
            $olpay=UserOfflineModel::is_pay($this->uid);
            if (!isset($olpay['id'])) {
                $ApiRedis->unlock($this->uid);
                ajax_return("充值要求不满足", 0);
            }
        }
        if (isset($action['mutually_exclusive_id']) && $action['mutually_exclusive_id'] > 0) {
            $mutually_exclusive_action = $ActionApplyModel->where("action_id", $action['mutually_exclusive_id'])->where("uid",$this->uid)->count();
            if (!empty($mutually_exclusive_action)) {
                $ApiRedis->unlock($this->uid);
                ajax_return("你无法同时参与互斥活动");
            }
        }

        if (isset($action['tag_id']) && $action['tag_id'] > 0 && $action['base_type'] != 6) {
            $user_tags = MemberService::getUserTagId($this->uid);
            if (!in_array($action['tag_id'], $user_tags)) {
                $ApiRedis->unlock($this->uid);
                ajax_return("用户标签条件不符合");
            }
        }
        //验证用户每日领取次数
        if( isset($action['everyday_person_num']) && $action['everyday_person_num'] > 0 ){
            //获取已申请次数
            $applyNum = $this->getUserApplyNum($this->uid, $action['id']);
            if( $applyNum >= $action['everyday_person_num'] ){
                $ApiRedis->unlock($this->uid);
                ajax_return('今日领取次数已达上限，请明日再来');
            }
        }

        //验证实名认证
        if( !MemberService::judgeUserIsCanReceiveDiscount($this->uid) ){
            $ApiRedis->unlock($this->uid);
            ajax_return('请先实名后再参与');
        }

        // 首存活动，累计充值活动强制用户领取一次
        if ($action['base_type']==1 || ($action['base_type']==2 && $action['zg_cz_type']==1)) {
            $action['base_apply_dr_max'] = 1;
        }

        $apply_count=$ActionApplyModel->where("action_id", $action['id'])->where("uid",$this->uid)->count();
        if (!($action['base_apply_dr_max'] > $apply_count) && $action['base_type'] != 6 && $action['base_type'] != 7) {
            $ApiRedis->unlock($this->uid);
            ajax_return("申请次数已满，无法再次申请", 0);
        }

        $apply_count=$ActionApplyModel->where("action_id", $action['id'])->count();
        if (!($action['base_apply_number_max'] > $apply_count) && $action['base_type'] != 6 && $action['base_type'] != 7) {
            $ApiRedis->unlock($this->uid);
            ajax_return("申请次数已满，无法再次申请", 0);
        }
        //判断是否设置有参与指定活动后才能参与
        if( isset($action['previous_action_id']) && $action['previous_action_id'] > 0 ){
            if( (new ActionFactory())->getUserApplyNum($this->uid, $action['previous_action_id'], $action['t_apply_start_time'], strtotime('today')) < 1 ){
                $ApiRedis->unlock($this->uid);
                ajax_return('昨天前未参与上个活动，无法申请该活动');
            }
        }
        //限制代理活动
        if( isset($action['agent_id']) && $action['agent_id'] > 0){
            foreach (explode(',', $action['agent_id']) as $v){
                $tree = UserModel::where([["tree",'like','%/'.$v.'/%']])->column("id");
                if(!in_array($this->uid,$tree) && $action['is_consist'] == 2){
                    $ApiRedis->unlock($this->uid);
                    ajax_return("未达到活动要求,无法参与", 0);
                }
                if(in_array($this->uid,$tree) && $action['is_consist'] == 1){
                    $ApiRedis->unlock($this->uid);
                    ajax_return("未达到活动要求,无法参与", 0);
                }
            }
        }
        Db::startTrans();
        try {
            $msg_content = "";
            if ($action['base_type']==1) {
                $othr_id=$this->apply_first($action,$user, $isJudge);
                $msg_content = "注册首存申请成功";
            }else if ($action['base_type']==2) {
                $othr_id=$this->apply_two($action,$user, $isJudge);
                $msg_content="再存优惠申请成功";
            }else if ($action['base_type']==3) {
                $othr_id=$this->apply_three($action, $isJudge);
                $msg_content="一般优惠申请成功";
            }else if ($action['base_type']==4) {
                $othr_id=$this->apply_four($action, $isJudge);
                $msg_content="体验金申请成功";
            }else if ($action['base_type']==6) {
                $othr_id=$this->apply_six($action,$user, $isJudge);
                $msg_content="游戏救援金申请成功";
            }else if ($action['base_type']==7){
                $othr_id=$this->apply_seven($action,$user, $isJudge);
                $msg_content="推荐奖励申请成功";
            }else{
                $ApiRedis->unlock($this->uid);
                ajax_return("类型错误", 0);
            }
            if( $isJudge ){
                return $othr_id;
            }
            $upd['base_y_apply_dr']=$action['base_y_apply_dr']+1;
            $upd['id']=$action['id'];
            $ActionModel->update($upd);//修改已申请人数
            UserMsgModel::add_msg($this->uid,"优惠申请",lang($msg_content),2,$othr_id);
            // 提交事务
            Db::commit();
            $rsps['code']=1;
        } catch (\Throwable $e) {
            $ApiRedis->unlock($this->uid);
            Db::rollback();
            $rsps['code']=0;
            $rsps['msg']=$e->getMessage();
        }
        $ApiRedis->unlock($this->uid);//释放锁
        return $rsps;
    }

    //获取用户今日已申请活动次数
    public function getUserApplyNum($uid, $actionId, $startTime = 0, $endTime = 0){
        if($startTime == 0) $startTime = strtotime('today');
        if($endTime == 0) $endTime = time();
        $condition = [];
        $condition[] = ['action_id', '=', $actionId];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['add_time', 'between', [$startTime, $endTime]];
        $condition[] = ['ps_status', 'in', [0, 1]];
        return ActionApplyModel::where($condition)->count();
    }


    //注册首存
    private function apply_first($action,$user, $isJudge = false){
        $recharge = UserOfflineModel::where(['uid' => $this->uid, 'first' => 1])->where("status in (3,4)")->find();
        if(empty($recharge)){
            ajax_return("存款金额未达到活动要求", 0);
        }
        //有选择活动则只能参与充值选择的活动
        if( isset($recharge['not_auto_action']) && $recharge['not_auto_action'] > 1 ){
            //大于1为充值时有选择活动
            if( $recharge['not_auto_action'] != $action['id'] ){
                ajax_return("充值时选择的活动不正确", 0);
            }
        }
        $ck_r_id = $recharge['id'];
        $ck_amount = $recharge['arrival_price'];
        $reg_json = json_decode($action['reg_json'],true);
        if (empty($reg_json)) {
            ajax_return("暂无数据", 0);
        }
        $last_names = array_column($reg_json,'min_reg');
        array_multisort($last_names,SORT_DESC,$reg_json);//最低 降序

        $now_jl=[];//奖励方式
        foreach ($reg_json as $key => $value) {
            if ($ck_amount >= $value['min_reg'] && $ck_amount <= $value['max_reg']) {
                $now_jl = $value;
            }
        }

        if (empty($now_jl)) {
            ajax_return("存款金额未达到活动要求", 0);
        }
        if (!isset($now_jl['integral_amount'])) {
            $now_jl['integral_amount'] = 0;
        }
        if( $isJudge ){
            return true;
        }
        $add_ck = [
            'add_time' => time(),
            'uid' => $this->uid,
            'ck_r_id' => $ck_r_id,
            'action_id' => $action['id'],
            'ck_amount' => $ck_amount,
            //1 固定， 2 比例
            'ck_bl' => ($now_jl['type'] == 2) ? $now_jl['bl_or_amount'] : 0,
            'ck_hl_amount' => ($now_jl['type'] == 2) ? round($ck_amount * $now_jl['bl_or_amount'] / 100, 2) : $now_jl['bl_or_amount'],
            'ck_integral_rate' => ($now_jl['type'] == 2) ? $now_jl['integral_amount'] : 0,
            'ck_integral' => ($now_jl['type'] == 2) ? round($ck_amount * $now_jl['integral_amount'] / 100, 2) : $now_jl['integral_amount'],
        ];

        $ck_id = UserCkModel::insertGetId($add_ck);
        //流水是否加上本金计算
        if( $action['savings_is_include_fee'] == 1 ){
            //包含本金
            $water = $action['water'] * ($add_ck['ck_hl_amount'] + $ck_amount);
        }else{
            //不包含本金
            $water = $action['water'] * $add_ck['ck_hl_amount'];
        }
        if(!empty($ck_id)){
            $upd_ck['ck_number']=date("YmdHi").$ck_id;
            $upd_ck['id']=$ck_id;
            UserCkModel::update($upd_ck);//存款编号

            $add_apply['add_time']=time();
            $add_apply['uid']=$this->uid;
            $add_apply['action_id']=$action['id'];
            $add_apply['user_ck_id']=$ck_id;
            $add_apply['ck_bl']=$add_ck['ck_bl'];
            $add_apply['ck_hl_amount']=$add_ck['ck_hl_amount'];
            $add_apply['integral_rate']=$add_ck['ck_integral_rate'];
            $add_apply['integral']=$add_ck['ck_integral'];
            $add_apply['water'] = $water;
            $apply_id = ActionApplyModel::insertGetId($add_apply);
            //自动审核
            if($apply_id && $action['auto_review'] == 1){
                $this->auto_review($apply_id);
            }
            return $apply_id;
        }else{
            return false;
        }
    }


    //再存优惠
    private function apply_two($action,$user, $isJudge = false){
        $where = [
            ['uid', '=', $this->uid],
//            ["receive", '=', 0],
            ['status', 'IN', '3,4'],
            ['first', '<>', 1],
            ['add_time', '>=', $action['t_apply_start_time']]
        ];
        if( $action['zg_cz_type'] != 7 ){
            $where[] = ['first', '<>', 1];
        }
        //如果设置了首充第二天后才能参与
        if( $action['is_first_tomorrow_join'] == 1 ){
            $_condition = [];
            $_condition[] = ['first', '=', 1];
            $_condition[] = ['uid', '=', $user['id']];
            $_condition[] = ['status', 'in', '3,4'];
            $firstTime = UserOfflineModel::where($_condition)->value('update_time');
            if( !$firstTime || $firstTime >= Carbon::now()->startOfDay()->timestamp ){
                ajax_return("该活动只能明天后参与", 0);
            }
        }
        //有选择活动则只能参与充值选择的活动
        $where1 = 'not_auto_action = 0 or not_auto_action = '.$action['id'];
        $ck_amount = null;
        $ck_r_id = 0;
            if($action['zg_cz_type'] == 2){
            $my_ck_id = UserCkModel::where('action_id', $action['id'])->where('uid', $this->uid)->column('ck_r_id');
            $recharge = UserOfflineModel::where($where)->where($where1)->whereNotIn('id', $my_ck_id)->order('arrival_price desc')->find();
            if (empty($recharge)) {
                ajax_return("申请次数已满，无法再次申请", 0);
            }
            $ck_r_id = $recharge['id'];
            $ck_amount = $recharge['arrival_price'];
        } elseif ($action['zg_cz_type'] == 3) { // 上周
            $where[] = ['add_time', '>=', Carbon::now()->subWeek()->startOfWeek()->timestamp];
            $where[] = ['add_time', '<=', Carbon::now()->subWeek()->endOfWeek()->timestamp];
            $toweek_count = ActionApplyModel::whereWeek("add_time")->where("action_id", $action['id'])->where("uid", $this->uid)->count();
            // 每星期限制领取一次
            if ($toweek_count > 0) {
                ajax_return("申请次数已满，无法再次申请", 0);
            }
        } elseif (in_array($action['zg_cz_type'], [4, 7])) { // 昨天
            $where[] = ['add_time', '>=', Carbon::yesterday()->startOfDay()->timestamp];
            $where[] = ['add_time', '<', Carbon::yesterday()->endOfDay()->timestamp];
            $today_count = ActionApplyModel::whereDay("add_time")->where("action_id", $action['id'])->where("uid", $this->uid)->count();
            // 每天限制领取一次
            if ($today_count > 0) {
                ajax_return("申请次数已满，无法再次申请", 0);
            }
        }elseif ($action['zg_cz_type'] == 5){ //今日累计
            $where[] = ['add_time', '>=', strtotime('today')];
            $where[] = ['add_time', '<=', time()];
            $today_count = ActionApplyModel::whereDay("add_time")->where("action_id", $action['id'])->where("uid", $this->uid)->count();
            // 每天限制领取一次
            if ($today_count > 0) {
                ajax_return("申请次数已满，无法再次申请", 0);
            }

        }elseif ($action['zg_cz_type'] == 6){//今日单笔
            $where[] = ['add_time', '>=', strtotime('today')];
            $where[] = ['add_time', '<=', time()];
            $ck_amount = UserOfflineModel::where($where)->where($where1)->max('arrival_price');
        }
        if ($action['zg_cz_type'] != 2 && $action['zg_cz_type'] != 6) {
            $ck_amount = UserOfflineModel::where($where)->where($where1)->sum('arrival_price');
        }

        if(empty($ck_amount)){
//            $ck_amount = 0;
            ajax_return("存款金额未达到活动要求", 0);
        }

        $reg_json = json_decode($action['reg_json'],true);//充值规则
        if (empty($reg_json)) {
            ajax_return("暂无数据", 0);
        }
        $last_names = array_column($reg_json,'min_reg');
        array_multisort($last_names,SORT_DESC,$reg_json);//最低 降序

        $now_jl=[];//奖励方式
        foreach ($reg_json as $key => $value) {
            if ($ck_amount >= $value['min_reg'] && $ck_amount <= $value['max_reg']) {
                $now_jl = $value;
            }
        }
        if (empty($now_jl)) {
            ajax_return("存款金额未达到活动要求", 0);
        }

        if( $isJudge ){
            return true;
        }

        if (!isset($now_jl['integral_amount'])|| $now_jl['integral_amount'] == '') {
            $now_jl['integral_amount'] = 0;
        }
        if (!isset($now_jl['bl_or_amount']) || $now_jl['bl_or_amount'] == '') {
            $now_jl['bl_or_amount'] = 0;
        }

        $add_ck = [
            'add_time' => time(),
            'uid' => $this->uid,
            'ck_r_id' => $ck_r_id,
            'action_id' => $action['id'],
            'ck_amount' => $ck_amount,
            //1 固定， 2 比例
            'ck_bl' => ($now_jl['type'] == 2) ? $now_jl['bl_or_amount'] : 0,
            'ck_hl_amount' => ($now_jl['type'] == 2) ? round($ck_amount * $now_jl['bl_or_amount'] / 100, 2) : $now_jl['bl_or_amount'],
            'ck_integral_rate' => ($now_jl['type'] == 2) ? $now_jl['integral_amount'] : 0,
            'ck_integral' => ($now_jl['type'] == 2) ? round($ck_amount * $now_jl['integral_amount'] / 100, 2) : $now_jl['integral_amount'],
        ];

        $ck_id = UserCkModel::insertGetId($add_ck);
        //流水是否加上本金计算
        if( $action['savings_is_include_fee'] == 1 ){
            //包含本金
            $water = $action['water'] * ($add_ck['ck_hl_amount'] + $ck_amount);
        }else{
            //不包含本金
            $water = $action['water'] * $add_ck['ck_hl_amount'];
        }
        if(!empty($ck_id)){
            $upd_ck['ck_number']=date("YmdHi").$ck_id;
            $upd_ck['id']=$ck_id;
            UserCkModel::update($upd_ck);//存款编号
            $add_apply['add_time']=time();
            $add_apply['uid']=$this->uid;
            $add_apply['action_id']=$action['id'];
            $add_apply['user_ck_id']=$ck_id;
            $add_apply['ck_bl']=$add_ck['ck_bl'];
            $add_apply['ck_hl_amount']=$add_ck['ck_hl_amount'];
            $add_apply['integral_rate']=$add_ck['ck_integral_rate'];
            $add_apply['integral']=$add_ck['ck_integral'];
            $add_apply['water'] = $water;
            $apply_id = ActionApplyModel::insertGetId($add_apply);
//            UserOfflineModel::where($where)->update(['receive' => 1]);
            //自动审核
            if($apply_id && $action['auto_review'] == 1){
                $this->auto_review($apply_id);
            }
            return $apply_id;
        }else{
            return false;
        }
    }


    //固定金额
    private function apply_three($action, $user, $isJudge = false){
        if( $isJudge ){
            return true;
        }
        $ActionApplyModel=new ActionApplyModel();
        $add_apply['add_time']=time();
        $add_apply['uid']=$this->uid;
        $add_apply['action_id']=$action['id'];
        $add_apply['gd_amount']=$action['jl_bl_price'];
        $add_apply['water'] = $action['water'] * $action['jl_bl_price'];
        $apply_id=$ActionApplyModel->insertGetId($add_apply);
        //自动审核
        if($apply_id && $action['auto_review'] == 1){
            $this->auto_review($apply_id);
        }
        return $apply_id;
    }

    //固定金额
    private function apply_four($action, $user, $isJudge = false){
        if( $isJudge ){
            return true;
        }
        $ActionApplyModel=new ActionApplyModel();
        $add_apply['add_time']=time();
        $add_apply['uid']=$this->uid;
        $add_apply['action_id']=$action['id'];
        $add_apply['tyj_amount']=$action['jl_bl_price'];
        $add_apply['tyj_date']=$action['base_ty_ts'];
        $add_apply['water'] = $action['water'] * $action['jl_bl_price'];
        $apply_id=$ActionApplyModel->insertGetId($add_apply);
        //自动审核
        if($apply_id && $action['auto_review'] == 1){
            $this->auto_review($apply_id);
        }
        return $apply_id;
    }

    //游戏救援金领取
    private function apply_six($action,$user, $isJudge = false){
        if($action['auto_apply'] == 1){
            ajax_return("该活动为自动发放，无需手动领取", 0);
        }
        $wh = [
            ['uid','=',$this->uid],
            ['action_id','=',$action['id']],
            ['ps_status','in','0,1']
        ];
        $t_apply_start_time = date('Ymd',$action['t_apply_start_time']);
        $t_apply_end_time = date('Ymd',$action['t_apply_end_time']);
        $where = [];$whereStr = '';
        $where[] = ['su_uid','=',$this->uid];
        $where[] = ['su_dt','between',[$t_apply_start_time,$t_apply_end_time]];
        if($action['date_type'] == 1){
            $andtime = date('Ymd',Carbon::yesterday()->timestamp);
            $where [] = ['su_dt','=',$andtime];
            $msg = '昨日';
            $info = ActionApplyModel::whereDay("add_time")->where($wh)->value("id");
        }else{
            $andtime = date('Ymd',Carbon::now()->startOfWeek()->timestamp);
            $endtime = date('Ymd',Carbon::now()->endOfWeek()->timestamp);
            $where [] =  ['su_dt','between',[$andtime,$endtime]];
            $msg = '本周';
            $info = ActionApplyModel::whereWeek("add_time")->where($wh)->value("id");
        }
        if($info){
            ajax_return($msg."救援金已存在");
        }
        $game_type = json_decode($action['game_type'],true) ??[];
        $level_json = json_decode($action['level_json'],true) ??[];
        if($game_type){
            $sql = GamesModel::where([['g_type','in',$game_type]])->field('g_id')->buildSql();
            $whereStr = 'su_gid in ('.$sql .')';
        }
        $tz_loss = getTransportTzloss($where,$whereStr);
        if($tz_loss <= 0){
            ajax_return("暂无救援金");
        }
        //获取金额
        $action = $this->jyjdata($action, $user);
        $price = $action['jyj_price'];
        if($price <= 0){
            ajax_return("暂无救援金");
        }
        if( $isJudge ){
            return true;
        }
        $add_apply['add_time']=time();
        $add_apply['uid']=$this->uid;
        $add_apply['action_id']=$action['id'];
        $add_apply['water'] = $action['water'] * $price;
        $add_apply['ck_hl_amount']=$price;
        $apply_id = ActionApplyModel::insertGetId($add_apply);
        //自动审核
        if($apply_id && $action['auto_review'] == 1){
            $this->auto_review($apply_id);
        }
        return $apply_id;
    }

    //月活动奖励领取
    private function apply_seven($action,$user, $isJudge = false){
        if($action['auto_apply'] == 1){
            ajax_return("暂无活动", 0);
        }
        $action['month_activity'] = json_decode($action['month_activity']);
        // 获取直属下级Id
        $result = UserModel::where('rec_id', $user['id'])->column('id');

        $sub_user = 0;
        $award = null;
        foreach ($action['month_activity'] AS $value){
            // 获取已领奖的下级
            $apply_user = ActionApplyModel::where('action_id',$action['id'])
                ->where('uid',$user['id'])
                ->where('consecutive_days',$value->day)
                ->whereIn('ps_status',[0,1])
                ->column('contributor');
            // 验证未领取的下级进行领取
            foreach ($result AS $item){
                // 验证奖励是否已经发放
                if (!in_array($item,$apply_user)){
                    // 获取用户充值总数
                    $user_recharge = UserOfflineModel::field("FROM_UNIXTIME(update_time,'%Y%m%d') AS days,SUM(apply_price) AS amount")
                        ->where('update_time','>=',$action['t_apply_start_time'])
                        ->where('uid', $item)
                        ->whereIn('status', [3,4])
                        ->group("days")
                        ->select();
                    // 验证用户充值总数是否足够
                    if (count($user_recharge) >= $value->day){
                        $total_num = 0 ;
                        foreach ($user_recharge AS $v ){
                            if ($v['amount'] >= $action['day_recharge_amount'] && $v['days'] != null){
                                $total_num += 1;
                            }
                        }
                        // 验证有效充值天数是否足够
                        if ($total_num >= $value->day){
                            $sub_user = $item;
                            $award = $value;
                            break;
                        }
                    }
                }
            }
        }
        if (empty($sub_user)){
            ajax_return(empty($apply_user)?"未有已达标的下级":"达标奖励已发放");
        }
        if( $isJudge ){
            return true;
        }

        $add_apply['add_time']=time();
        $add_apply['uid']=$this->uid;
        $add_apply['action_id']=$action['id'];
        $add_apply['contributor']=$sub_user;
        $add_apply['consecutive_days'] = $award->day;
        $add_apply['water'] = $action['water'] * $award->bonus;
        $add_apply['ck_hl_amount']=$award->bonus;
        $apply_id = ActionApplyModel::insertGetId($add_apply);
        //自动审核
        if($apply_id && $action['auto_review'] == 1){
            $this->auto_review($apply_id);
        }
        return $apply_id;
    }


    //自动通过审核
    public function auto_review($apply_id){
        $ActionApplyModel = new ActionApplyModel();
        $UserCkModel = new UserCkModel();
        $UserModel = new UserModel();
        $apply=$ActionApplyModel
            ->alias("a")
            ->join("dq_user b","a.uid=b.id")
            ->join("dq_action c","a.action_id=c.id")
            ->where(["a.id"=>$apply_id,'a.ps_status'=>0])
            ->field("a.*,c.base_type,c.water_type,c.water_type_ids,c.water as action_water,c.wallet_water_type")
            ->find();
        if($apply) {
            //判断阻断状态
            if(
                #1、判断实名认证，未实名认证，不能领取
                ($apply['queue_status'] == ActionApplyModel::QUEUE_STATUS_REAL_NAME_BLOCK && !MemberService::judgeUserIsCanReceiveDiscount($this->uid)) ||
                #2、判断是否有未使用优惠彩金
                ($apply['queue_status'] == ActionApplyModel::QUEUE_STATUS_BALANCE_BLOCK && !WalletService::judgeUserIsRePeatReceive($apply['uid']))
            ){
                return false;
            }
            $priceType = 1;
            if( $apply['wallet_water_type'] == 0  ){
                //流水钱包类型（0真金1彩金），真金时重置priceType
                $priceType = 0;
            }
            Db::startTrans();
            try {
                $water = $apply['action_water'];
                if( in_array($apply['water_type'], [1, 2]) ){
                    //指定流水下不能增加用户流水，不然多增加一次
                    $water = 0;
                }
                $msg = '';
                $amount = 0;
    //        UserMsgModel::add_msg($pay['uid'], "充值送积分", '积分入账 {:integral}', 11, $pay['id'],['integral'=>$Config['integral']]);
                if ($apply['base_type'] == 1 || $apply['base_type'] == 2) {
                    $ck=$UserCkModel->where(["id"=>$apply['user_ck_id'],'ck_status'=>0])->find();
                    if($ck){
                        $amount = $apply['ck_hl_amount'];
                        $msg = $apply['base_type'] == 1 ? '注册首存奖励' : '再存优惠奖励';
                        $UserModel->cz_balance($apply['uid'], $ck['ck_hl_amount'], "+", true, $msg, 5, $water, $priceType, $apply['id'], true, 101);
                        $upd_ck['id'] = $ck['id'];
                        $upd_ck['ck_status'] = 1;
                        $UserCkModel->update($upd_ck);
                    }
                } elseif ($apply['base_type'] == 3) {
                    $msg = "优惠固定奖励";
                    $amount = $apply['gd_amount'];
                    $UserModel->cz_balance($apply['uid'], $apply['gd_amount'], "+", true, $msg, 5, $water, $priceType, $apply['id'], true, 101);
                } elseif ($apply['base_type'] == 4) {
                    $msg = "体验金";
                    $amount = $apply['tyj_amount'];
                    $UserModel->cz_balance($apply['uid'], $apply['tyj_amount'], "+", true, $msg, 5, $water, $priceType, $apply['id'], true, 101);
                    $day = "+" . $apply['tyj_date'] . ' day';
                    $upd_user['id'] = $apply['uid'];
                    $upd_user['ty_balance'] = $apply['tyj_amount'];
                    $upd_user['ty_end_date'] = strtotime($day);
                    $UserModel->update($upd_user);
                }elseif ($apply['base_type'] == 6) {
                    $msg = "游戏救援金";
                    $amount = $apply['ck_hl_amount'];
                    $UserModel->cz_balance($apply['uid'],$apply['ck_hl_amount'], "+", true, $msg, 5, $water, $priceType, $apply['id'], true, 101);
                }elseif($apply['base_type'] == 7){
                    $msg = "推荐奖励";
                    $amount = $apply['ck_hl_amount'];
                    $UserModel->cz_balance($apply['uid'],$apply['ck_hl_amount'], "+", true, $msg, 5, $water, $priceType, $apply['id'], true, 101);
                }
                if ($apply['integral']) {
                    IntegralMxModel::cz_mx($apply['uid'], 1, $apply['integral'], $msg ?? '活动积分奖励');
                    MessageService::sendJsonMsg([
                        'msg' => "活动积分奖励",
                        'amount' => 0
    //                        'amount' => $apply['integral']
                    ], $apply['uid'], 'activity', 5, "活动优惠");
                }
                $upd['ps_deta'] = time();
                $upd['ps_status'] = 1;
                $ActionApplyModel->where("id", $apply['id'])->update($upd);
                if( in_array($apply['water_type'], [1, 2]) ){
                    UserAwardCfgsModel::createData($apply, $amount, $apply['wallet_water_type']);
                    MemberService::addUserWaterDetail($apply['uid'], $apply['water'], 2, "活动优惠", $apply['id'], $amount, $apply['action_water']);
                }
                //保存阻断状态
                if( ActionApplyModel::QUEUE_STATUS_NORMAL != $apply['queue_status'] ){
                    ActionApplyModel::where("id", $apply['id'])->update(['queue_status' => ActionApplyModel::QUEUE_STATUS_NORMAL]);
                }
                // 提交事务
                Db::commit();
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
            }
            MessageService::sendJsonMsg([
                'msg' => $msg,
                'amount' => $amount
            ], $apply['uid'], 'activity', 5, "活动优惠");
        }
    }

    //抽奖配置
    public function lotteryconfig_run(){
        $time = time();
        $where = 'id=1 and status=1 and ((starttime = 0 and endtime = 0) or (starttime > 0 and endtime > 0 and starttime < '.$time.' and endtime > '.$time.'))';

        $lang = strtoupper(\think\facade\Lang::getLangSet());

        $isNeedZeroWhere = AgentService::judgeIsNeedZeroWhere($this->uid);
        $condition1 = AgentService::getAgentWhere('agent_id', true, '', true, '', $isNeedZeroWhere);
        $config = LotteryConfigModel::where($where)->where($condition1)->find();
        if($config){
            $currencySymbol = Util::getCurrencyDefaultSymbol('symbol');
            $config['starttime'] = empty($config['starttime']) ? '':date('Y-m-d H:i:s',$config['starttime']);
            $config['endtime'] = empty($config['endtime']) ? '':date('Y-m-d H:i:s',$config['endtime']);
            $user =  UserModel::where('id',$this->uid)->field('balance,integral,free_count')->find();
            $config['balance'] =$user['balance'];
            $config['integral'] =$user['integral'];

            if($config['free_time'] > 0){ // 设置免费间隔时间
                $free_num = LotteryRecordModel::where(['lottery_id'=>$config['id'],'uid'=>$this->uid,'is_free'=>1,'is_fake'=>0,'is_away'=>0])->where("time >".(time() - $config['free_time']))->count();
                if($free_num == 0){
                    $times = $config['free_count'] - $free_num;
                    $config['free_count'] = max($times, 0);
//                    $config['free_count'] = 1;
                } else {
                    $config['free_count'] = 0;
                }
            }else{ // 免费次数生效
                if($config['free_count'] > 0){
                    $free_count =  $config['free_count'] - LotteryRecordModel::where(['lottery_id'=>$config['id'],'uid'=>$this->uid,'is_free'=>1,'is_fake'=>0,'is_away'=>0])->count();
                    $config['free_count'] = $free_count <= 0 ? 0:$free_count;
                }else{
                    $config['free_count'] = 0;
                }
            }
            $config['free_count'] = $config['free_count']+$user['free_count'];
            //获取用户最后一次抽奖时间
            $config['last_lottery_time'] = LotteryRecordModel::where(['lottery_id'=>$config['id'],'uid'=>$this->uid,'is_free'=>1,'is_fake'=>0,'is_away'=>0])
                ->order('time desc')->value('time') ?: 0;
            if($config['limit_count'] > 0){
                $andtime = strtotime(date('Y-m-d 00:00:00', time()));
                $endtime = strtotime(date('Y-m-d 23:59:59', time()));
                $where2 = [
                    ['lottery_id','=',$config['id']],
                    ['uid','=',$this->uid],
                    ['is_fake','=',0],
                    ['time','between',[$andtime,$endtime]],
                ];
                $limit_count = $config['limit_count'] - LotteryRecordModel::where($where2)->count();
                $config['limit_count'] = $limit_count <= 0 ? 0:$limit_count;
            }
            $list = LotteryPrizeModel::order('sort desc')->where('lottery_id',$config['id'])->select();
            $config['Prize'] = $list;
            $config['currency_symbol'] = $currencySymbol;
            $config['day_time'] = LotteryService::getTimes($config);
            //获取用户任务完成情况
            $finishData = LotteryService::getUserTaskDetailRate($config,$this->uid);
            $finishData = LotteryService::getConditionStatus($finishData, $config, $this->uid, -1);
            $config['task_rate'] = $finishData['data'];
            $config['task_status'] = $finishData['status'];
            $config['task_is_receive'] = $finishData['is_receive'];
            //获取对应的规则或简介
            $_condition = [];
            $_condition[] = ['lottery_id', '=', $config['id']];
            $_condition[] = ['lang', '=', $lang];
            $configNew = LotteryNewsModel::where($_condition)->find();
            $config['content'] = '';
            if( $configNew ){
                $configNew['title'] = $configNew['news_title'];
                $configNew['img'] = $configNew['news_images'];
                $configNew['rule'] = $configNew['news_intro'];
                $configNew['content'] = $configNew['news_content'];
            }

            return ['code'=>1,'data'=>$config];
        }else{
            ajax_return("暂无抽奖活动", 0);
        }
    }

    //抽奖配置列表
    public function lotteryconfiglist_run(){
        $time = time();
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        
        $where = ' status=1 and ((starttime = 0 and endtime = 0) or (starttime > 0 and endtime > 0 and starttime < '.$time.' and endtime > '.$time.'))';
        $isNeedZeroWhere = AgentService::judgeIsNeedZeroWhere($this->uid);
        $condition1 = AgentService::getAgentWhere('agent_id', true, '', true, '', $isNeedZeroWhere);
        $config_list = LotteryConfigModel::where($where)->where($condition1)->select();
        $config_arr = [];
        foreach ($config_list as $config){
            $currencySymbol = Util::getCurrencyDefaultSymbol('symbol');
            $config['starttime'] = empty($config['starttime']) ? '':date('Y-m-d H:i:s',$config['starttime']);
            $config['endtime'] = empty($config['endtime']) ? '':date('Y-m-d H:i:s',$config['endtime']);
            $user =  UserModel::where('id',$this->uid)->field('balance,integral,free_count')->find();
            $config['balance'] =$user['balance'];
            $config['integral'] =$user['integral'];

            if($config['free_time'] > 0){ // 设置免费间隔时间
                $free_num = LotteryRecordModel::where(['lottery_id'=>$config['id'],'uid'=>$this->uid,'is_free'=>1,'is_fake'=>0,'is_away'=>0])->where("time >".(time() - $config['free_time']))->count();
                if($free_num == 0){
                    $times = $config['free_count'] - $free_num;
                    $config['free_count'] = max($times, 0);
//                    $config['free_count'] = 1;
                } else {
                    $config['free_count'] = 0;
                }
            }else{ // 免费次数生效
                if($config['free_count'] > 0){
                    $free_count =  $config['free_count'] - LotteryRecordModel::where(['lottery_id'=>$config['id'],'uid'=>$this->uid,'is_free'=>1,'is_fake'=>0,'is_away'=>0])->count();
                    $config['free_count'] = $free_count <= 0 ? 0:$free_count;
                }else{
                    $config['free_count'] = 0;
                }
            }
            $config['free_count'] = $config['free_count']+$user['free_count'];
            //获取用户最后一次抽奖时间
            $config['last_lottery_time'] = LotteryRecordModel::where(['lottery_id'=>$config['id'],'uid'=>$this->uid,'is_free'=>1,'is_fake'=>0,'is_away'=>0])
                ->order('time desc')->value('time') ?: 0;
            if($config['limit_count'] > 0){
                $andtime = strtotime(date('Y-m-d 00:00:00', time()));
                $endtime = strtotime(date('Y-m-d 23:59:59', time()));
                $where2 = [
                    ['lottery_id','=',$config['id']],
                    ['uid','=',$this->uid],
                    ['is_fake','=',0],
                    ['time','between',[$andtime,$endtime]],
                ];
                $limit_count = $config['limit_count'] - LotteryRecordModel::where($where2)->count();
                $config['limit_count'] = $limit_count <= 0 ? 0:$limit_count;
            }
            $list = LotteryPrizeModel::order('sort desc')->where('lottery_id',$config['id'])->select();
            $config['Prize'] = $list;
            $config['currency_symbol'] = $currencySymbol;
            $config['day_time'] = LotteryService::getTimes($config);
            //获取用户任务完成情况
            $finishData = LotteryService::getUserTaskDetailRate($config,$this->uid);
            $finishData = LotteryService::getConditionStatus($finishData, $config, $this->uid, -1);
            $config['task_rate'] = $finishData['data'];
            $config['task_status'] = $finishData['status'];
            $config['task_is_receive'] = $finishData['is_receive'];
            //获取对应的规则或简介
            $_condition = [];
            $_condition[] = ['lottery_id', '=', $config['id']];
            $_condition[] = ['lang', '=', $lang];
            $configNew = LotteryNewsModel::where($_condition)->find();
            $config['content'] = '';
            if( $configNew ){
                $configNew['title'] = $configNew['news_title'];
                $configNew['img'] = $configNew['news_images'];
                $configNew['rule'] = $configNew['news_intro'];
                $configNew['content'] = $configNew['news_content'];
            }
            $config_arr[] = $config;
        }
        if(empty($config_arr)){
            ajax_return("暂无抽奖活动", 0);
        }
        return ['code'=>1,'data'=>$config_arr];
    }

    //抽奖记录
    public function lotteryrecord_run(){
        $id= empty($this->param['id'])? 1:$this->param['id'];
        $pagenow= empty($this->param['page'])? 1:$this->param['page'];
        $limit= empty($this->param['limit']) ? 10:$this->param['limit'];
        $ste = empty($this->param['state']) ? 0:1;
        $prize_type = empty($this->param['prize_type']) ? 0:1;
        $where = [];
        if( $id != -1 ){
            //全部
            $where[] = ['a.lottery_id','=',$id];
        }
        if($ste){
            $where[] = ['a.uid','=',$this->uid];
            $where[] = ['a.is_fake','=',0];
        }
        if($prize_type){
            $where[] = ['a.prize_type','<>','3'];
        }
        $list['data'] = LotteryRecordModel::alias('a')
            ->join('lottery_prize b','a.prize_id = b.id')
            ->where($where)->order('time desc')
            ->limit((($pagenow-1)*$limit),$limit)
            ->field('a.*,b.pic')
            ->select();
        $list['count'] = LotteryRecordModel::alias('a')
            ->join('lottery_prize b','a.prize_id = b.id')
            ->where($where)->count();
        foreach ($list['data'] as &$v){
            $userDetail = UserModel::where('id',$v['uid'])->field('username,head_img')->find();
            $username = $userDetail['username'];
            $len = mb_strlen($username);
            $str1 = mb_substr($username,0,2);
            $str2 = mb_substr($username,$len-2,2);
            $v['avatar'] = $userDetail['head_img'];
            $v['username'] = $str1.'****'.$str2;
            $v['time'] = Util::i18n_datetime2str($v['time']);
        }
        return ['code'=>1,'data'=>$list];
    }

    //领取任务条件次数
    public function receiveLotteryConditionCount_run(){
        $id = empty($this->param['id'])? 1 : $this->param['id'];
        //获取转盘信息
        $lotteryDetail = LotteryConfigModel::where('id', $id)->find();
        if (!$lotteryDetail) {
            ajax_return("活动不存在", 0);
        }
        $startTime = is_numeric($lotteryDetail['starttime']) ? $lotteryDetail['starttime'] : strtotime($lotteryDetail['starttime']);
        $endTime = is_numeric($lotteryDetail['endtime']) ? $lotteryDetail['endtime'] : strtotime($lotteryDetail['endtime']);
        //获取转盘完成情况
        $taskCondition = explode(',', $lotteryDetail->task_condition);
        $taskData = [];
        foreach ($taskCondition as  $v) {
            $taskData[] = LotteryService::getLotteryConditionDetail($v, $lotteryDetail, $this->uid, $startTime, $endTime);
        }
        $taskDataResult = LotteryService::getConditionStatus($taskData, $lotteryDetail, $this->uid, -1);
        if( !$taskDataResult['status'] ){
            ajax_return("任务未完成");
        }
        if( $taskDataResult['is_receive'] ){
            ajax_return('已领取，无需重复领取');
        }
        Db::startTrans();
        try {
            //执行领取
            $data = [
                'lottery_id' => $lotteryDetail['id'],
                'uid' => $this->uid,
                'type' => -1,#全部条件的，目前是所有条件一起
                'reward_count' => $lotteryDetail['reward_free_count'],
                'update_time' => time(),
                'create_time' => time(),
            ];
            $res = LotteryConditionRecordModel::create($data);
            if( !$res ){
                Db::rollback();
                ajax_return('网络异常');
            }
            //生成用户免费次数
            $res = UserModel::where('id', $this->uid)->inc('free_count', $lotteryDetail['reward_free_count'])->update();
            if( !$res ){
                Db::rollback();
                ajax_return('网络异常');
            }
        }catch (\Exception $e){
            Db::rollback();
            ajax_return('网络异常');
        }
        Db::commit();
        $taskData = [];
        foreach ($taskCondition as  $v) {
            $taskData[] = LotteryService::getLotteryConditionDetail($v, $lotteryDetail, $this->uid, $startTime, $endTime);
        }
        $taskDataResult = LotteryService::getConditionStatus($taskData, $lotteryDetail, $this->uid, -1);
        $rsps['data'] = ['task_rate' => $taskDataResult['data'], 'task_status' => $taskDataResult['status'], 'task_is_receive' => $taskDataResult['is_receive']];
        $rsps['code'] = 1;
        return $rsps;

    }

    //抽奖
    public function lottery_run()
    {
        $id = empty($this->param['id'])?1:$this->param['id'];
        $time = time();
        $where = 'id='.$id.' and status=1 and ((starttime = 0 and endtime = 0) or (starttime > 0 and endtime > 0 and starttime < ' . $time . ' and endtime > ' . $time . '))';
        $config = LotteryConfigModel::where($where)->find();
        if (!$config) {
            ajax_return("抽奖活动未开启", 0);
        }
        //验证实名认证
        if( !MemberService::judgeUserIsCanReceiveDiscount($this->uid) ){
            ajax_return('请先实名后再参与');
        }
        $is_free = 0;
        $is_away = 0;
        $user = UserModel::where('id', $this->uid)->find();
        //双钱包判断当前用户是否可以重复领取优惠
        if( !WalletService::judgeUserIsRePeatReceive(0, $user) ){
            ajax_return('你的账户还存在彩金，请使用完后再来领取');
        }
        $andtime = strtotime(date('Y-m-d 00:00:00', time()));
        $endtime = strtotime(date('Y-m-d 23:59:59', time()));
        if($config['free_time'] > 0){ // 设置免费间隔时间
            $free_num = LotteryRecordModel::where(['lottery_id'=>$config['id'],'uid'=>$this->uid,'is_free'=>1,'is_fake'=>0,'is_away'=>0])->where("time >".(time() - $config['free_time']))->count();
            if($config['free_count'] == -1 ){
                $is_free = 1;
                $config['free_count'] = 99999;
            }elseif ( $free_num < $config['free_count'] ){
                $is_free = 1;
                $config['free_count'] -= $free_num;
            }

//            if($free_num == 0){
//                $is_free = 1;
//            } else {
//                $is_free = 0;
//            }
//            $config['free_count'] = 0;
//            $user['free_count'] = 0;
        }
        $freeTime = 0;#给前端返回免费间隔
        if($is_free == 0){
            $where2 = [
                ['lottery_id','=',$config['id']],
                ['uid', '=', $this->uid],
                ['is_fake', '=', 0],
                ['time', 'between', [$andtime, $endtime]],
            ];
            $count = LotteryRecordModel::where($where2)->count();
            if ($config['limit_count'] > 0 && $count >= $config['limit_count']) {
                ajax_return("当日抽奖次数不足", 0);
            }
            $freeTime = $config['free_time'];
            $wheref = ['lottery_id'=>$config['id'],'uid' => $this->uid, 'is_free' => 1, 'is_fake' => 0,'is_away'=>0];
            $free_count = LotteryRecordModel::where($wheref)->count();
            $is_free = ($config['free_count'] == -1 || $free_count < $config['free_count'] || $user['free_count'] > 0) ? 1 : 0;
            $pay_type = ($config['required_integral'] > 0 && $config['required_balance'] <= 0 || ($config['required_integral'] > 0 && $user['integral'] >= $config['required_integral'])) ? 1 : 2;
            $is_away = ($free_count >= $config['free_count'] && $user['free_count'] > 0) ? 1:0;
        }
        $integral = $price = 0;
        if($is_free == 0) {
            if ($pay_type == 1) {
                if ($config['required_integral'] > 0 && $user['integral'] < $config['required_integral']) {
                    ajax_return("积分不足", 0);
                }
                $integral = $config['required_integral'];
            } else {
                if ($config['required_balance'] > 0 && $user['balance'] < $config['required_balance']) {
                    ajax_return("余额不足", 0);
                }
                $price = $config['required_balance'];
            }
            if( $integral <= 0 && $price <= 0 ){
                ajax_return('抽奖次数不足');
            }
        }
        $list = LotteryPrizeModel::where('stock > 0 or stock = -1 or type = 3')->where('lottery_id',$config['id'])->select();
        if(count($list) <= 0){
            ajax_return("暂无奖品", 0);
        }
        $arr = [];
        foreach ($list as $key => $val) {
            if($val['probability'] > 0){
                $arr[$val['id']] = $val['probability'];
            }
        }
        if(count($arr) <= 0){
            ajax_return("暂无奖品", 0);
        }
        $rid = get_rand($arr);
        $info = LotteryPrizeModel::where('id',$rid)->find();
        Db::startTrans();
        try {
            $da = [
                'uid'=>$this->uid,
                'lottery_id'=>$config['id'],
                'prize_id'=>$rid,
                'title'=>$info['title'],
                'time'=>time(),
                'prize_type'=>$info['type'],
                'price'=>$price,
                'integral'=>$integral,
                'is_free'=>$is_free,
                'prize_price'=>$info['amount'],
                'is_away'=>$is_away,
            ];

            $res = LotteryRecordModel::insertGetId($da);
            if($res){
                if($is_free == 0){
                    if($pay_type == 1){
                        UserModel::where('id',$this->uid)->dec('integral',$integral)->update();
                        UserMsgModel::add_msg($this->uid,"抽奖积分",lang('抽奖积分消费').'{:integral}',11,$res,['integral'=>$integral]);
                    }else{
                        UserModel::cz_balance($this->uid,$price,"-",true,"抽奖金额", 35);
                        UserMsgModel::add_msg($this->uid,"抽奖金额",lang('抽奖金额消费').'{:price}',11,$res,['price'=>Util::i18n_number_format($price)]);
                    }
                }else{
                    if($is_away){
                        UserModel::where('id',$this->uid)->dec('free_count')->update();
                    }
                }
                if($da['prize_type'] == 1){
                    UserModel::cz_balance($this->uid,$info['amount'],"+",true,"中奖金额", 36, $info['water'], 1, $res, true, 101);
                    UserMsgModel::add_msg($this->uid,"中奖金额",lang('抽奖奖励已到账,奖品').' {:title},'.lang('中奖金额').' {:amount}{:currency}',11,$res,['title'=>$info['title'],'amount'=>Util::i18n_number_format($info['amount']),'currency'=>Util::i18n_currency_no_amount()]);
                }else if($da['prize_type'] == 2){
                    UserMsgModel::add_msg($this->uid,"中奖物品",lang('抽奖奖励已到账,奖品').'{:title},'.lang('联系客服领取'),11,$res,['title'=>$info['title']]);
                }
                if($info['stock'] != -1 && $info['stock'] > 0 && $info['type'] !== 3){
                    LotteryPrizeModel::where(['id' => $info['id'],'lottery_id'=>$config['id']])->dec("stock")->update();
                }

            }
            $user_lottery_count = LotteryRecordModel::where('uid', $this->uid)->where('lottery_id',$config['id'])->where('time', '>=', strtotime(date('Y-m-d').' 00:00:00'))->where('time', '<=', strtotime(date('Y-m-d').' 23:59:59'))->count();

            Db::commit();
            $info['balance'] = $user['balance'];
            $info['free_count'] = $config['free_count'] - 1 ?: 0;
            $info['free_time'] = $freeTime;#免费间隔时间
            $info['limit_count'] = ($config['limit_count'] - $user_lottery_count) <= 0 ? 0 : $config['limit_count'] - $user_lottery_count;
            $rsps['code']=1;
            $rsps['data']=$info;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code']=0;
            $rsps['msg']=$e->getMessage();
        }
        return $rsps;
    }


    public function activity_statistics_run(){
        $ApiRedis=new ApiRedis();
        //活动
        $redis_key = md5('activity_statistics_'.$this->uid);
        $lists = $ApiRedis->get($redis_key);
        if($lists){
            $list = $ApiRedis->get($redis_key);
        }else{
            $list = [
                'total_price'=> Util::i18n_currency(ReportService::getBonuses(0, 99999999999, $this->uid, 2)['all_amount'] ?? 0), //总金额
                'total_distributed'=> Util::i18n_currency(ReportService::getBonuses(0, 99999999999, $this->uid, 2)['action_amount'] ?? 0), //已派发
                'total_undistributed'=> Util::i18n_currency(ReportService::getBonuses(0, 99999999999, $this->uid, 2)['not_amount'] ?? 0), //未派发
            ];
//            $list = [
//                'total_price'=> Util::i18n_currency(ActionApplyModel::getamount($this->uid) ?: 0), //总金额
//                'total_distributed'=> Util::i18n_currency(ActionApplyModel::getamount($this->uid,1) ?: 0), //已派发
//                'total_undistributed'=> Util::i18n_currency(ActionApplyModel::getamount($this->uid,0) ?: 0), //未派发
//            ];
            $ApiRedis->set($redis_key,$list,60);
        }
        //任务
        $task_redis_key = md5('task_statistics_'. $this->uid);
        $task_lists = $ApiRedis->get($task_redis_key);
        if ($task_lists) {
            $task_list = $ApiRedis->get($task_redis_key);
        } else {
            $task_list = [
                'task_total_price'=> Util::i18n_currency(TaskRecordModel::getamount($this->uid) ?: 0), //总金额
                'task_total_distributed'=> Util::i18n_currency(TaskRecordModel::getamount($this->uid, 1) ?: 0), //已派发
                'task_total_undistributed'=> Util::i18n_currency(TaskRecordModel::getamount($this->uid, 0) ?: 0), //未派发
            ];
            $ApiRedis->set($task_redis_key, $task_list, 60);
        }
        //阶段任务
        $stageTaskRedisKey = md5('stage_task_statistics_'. $this->uid);
        $stageTaskLists = $ApiRedis->get($stageTaskRedisKey);
        if ($stageTaskLists) {
            $stageTaskList = $ApiRedis->get($stageTaskRedisKey);
        } else {
            $stageTaskList = [
               'stage_task_total_price' => Util::i18n_currency(TaskStageRecordModel::getamount($this->uid) ?: 0), //总金额
               'stage_task_total_distributed' => Util::i18n_currency(TaskStageRecordModel::getamount($this->uid, 1) ?: 0), //已派发
               'stage_task_total_undistributed' => Util::i18n_currency(TaskStageRecordModel::getamount($this->uid, 0) ?: 0), //未派发
            ];
            $ApiRedis->set($stageTaskRedisKey, $stageTaskList, 60);
        }

        return ['code'=>1,'data'=>$list, 'task_list' => $task_list, 'stage_task_list' => $stageTaskList];
    }

    //获取充值优惠活动
    public static function getRechargeDiscountActionDetail($userDetail){
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $time = time();
        $condition = [];
        $condition[] = ['t_apply_start_time', '<=', $time];
        $condition[] = ['t_apply_end_time', '>=', $time];
        $condition1 = "(zg_level >= {$userDetail['level_id']} or zg_level = 'all')";#活动等级限制
        $userTagArr = implode(',', UserTagModel::where('uid', '=', $userDetail['id'])->column('tag_id'));
        //用户标签限制
        if( $userTagArr ){
            $condition1 .= " AND (tag_id in ({$userTagArr}) or tag_id = 0)";
        }
        $discountCondition = $condition;
        $first = 0;
        //判断是否是首充
        $firstRecharge = UserOfflineModel::where(["uid"=>$userDetail['id'], 'first' => 1])->where([["status", 'in',"3,4"]])->find();

        if( !$firstRecharge ){
            //首充，查找首充优惠的活动
            $condition[] = ['base_type', '=', 1];
            $first = 1;
        }else{
            //再存
            $condition[] = ['base_type', '=', 2];
            //不显示充值要求为昨天累计含首充
            $condition[] = ['zg_cz_type', '<>', 7];

            //如果设置了首充第二天后才能参与则不查询该活动
            //获取首充时间当天范围
            $firstDayEndTime = Carbon::parse(date('Y-m-d H:i:s', $firstRecharge->update_time))->startOfDay()->timestamp;
            if( time() <= $firstDayEndTime ){
                //如果在首充当天只查询没有开启'首充第二天后才能参与'的活动
                $condition[] = ['is_first_tomorrow_join', '=', 0];
            }
        }
        $regJsonArr = [];
        $isNeedZeroWhere = AgentService::judgeIsNeedZeroWhere($userDetail->id);
        //代理线条件
        $condition2 = AgentService::getAgentWhere('agent_id', true, '', true, '', $isNeedZeroWhere);
        //获取活动数据（首充和再存）
        $regJson = ActionModel::with('news')->where($condition)
            ->where($condition1)->where($condition2)->field('id,base_type,reg_json')->select();
        if($regJson){
            foreach ($regJson as $v){
                $_regJson = json_decode($v['reg_json'], true);
                $rechargeImage = '';
                foreach ($v->news as $__v){
                    //判断当前语言，获取当前语言的图
                    if($__v['lang'] == $lang){
                        //判断是首充还是再存
                        $rechargeImage = $__v['first_recharge_images'];
                        if( $v['base_type'] == 2 ){
                            //再存
                            $rechargeImage = $__v['recharge_images'];
                        }
                    }
                }
                foreach ($_regJson as $_v ){
                    $_v['recharge_image'] = $rechargeImage;
                    $regJsonArr[] = $_v;
                }
            }
        }else{
            //没有首充和再存时候显示一般优惠
            //获取活动数据（一般优惠）
            $discountCondition[] = ['base_type', '=', 3];
            $condition1 .= " and (jl_ck_or_level_s <= {$userDetail['level_id']} and jl_ck_or_level_e >= {$userDetail['level_id']})";#活动等级限制
            $regJsonDiscountList = ActionModel::with('news')->where($discountCondition)
                ->where($condition1)->field('id,jl_bl_price');
            foreach ($regJsonDiscountList as $v){
                //组合跟首充和再存一样的格式
                $arr = [
                    'type' => '1',
                    'min_reg' => 1,//一般优惠只有等级领取区间，没有最大和最小区间，这里取最小和最大的值
                    'max_reg' => 999999999,
                    'bl_or_amount' => $v,
                    'content' => $v->news,
                    'recharge_image' => '',
                ];
                $regJsonArr[] = $arr;
            }
        }
        $newActionList = [];
//        if( $first ){
            //查询活动列表
            $actionList = ActionModel::alias('a')
                ->with('news')
                ->join('action_apply b', 'b.action_id=a.id and b.uid='.$userDetail->id, 'left')
                ->where($condition)->where($condition1)->where($condition2)
                ->having('base_apply_dr_max = 0 or base_apply_dr_max > count(b.id)')#个人申请数量大于已经申请
                ->field('a.id,reg_json,base_apply_dr_max,everyday_person_num')
                ->group('a.id')
                ->order('sort desc,a.id desc')
                ->select();
            $startTime = strtotime('today');
            $endTime = strtotime('tomorrow') - 1;
            foreach ($actionList as $k => $v){
                //判断当前领取次数是否完
                if( $v['everyday_person_num'] > 0 ){
                    $_condition = [];
                    $_condition[] = ['uid', '=', $userDetail->id];
                    $_condition[] = ['action_id', '=', $v['id']];
                    $_condition[] = ['add_time', 'between', [$startTime, $endTime]];
                    if( ActionApplyModel::where($_condition)->count() >= $v['everyday_person_num'] ){
                        continue;
                    }

                }
                $v->news_title = '';
                $v->news_images = '';
                $v->reg_json = json_decode($v->reg_json, true);
                //判断当前语言，获取当前语言的图
                foreach ($v->news as $_k => $_v){
                    //判断当前语言，获取当前语言的图
                    if($_v['lang'] == $lang){
                        $v->news_title = $_v['news_title'];
                        $v->news_images = $_v['news_images'];
                        break;
                    }
                }
                //没有标题删除活动
                if( !$v->news_title ){
                    continue;
//                    unset($actionList[$k]);
                }
                unset($v->news);
                $newActionList[] = $v;
            }
//            $actionList = $actionList->toArray();
//        }else{
//            $actionList = [];
//        }

        return ['reg_json' => $regJsonArr, 'action_list' => $newActionList];
    }


    //游戏救援金列表
    public function jyjlist_run(){
        $pagenow=$this->param['page']??1;
        $limit=$this->param['limit']??10;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $ApiRedis=new ApiRedis();
        $redis_key = md5('jyjlist_run_'.$this->uid.$pagenow.$limit.$lang);
        $rsps = $ApiRedis->get($redis_key);
        if(empty($rsps[0])){
            $isNeedZeroWhere = AgentService::judgeIsNeedZeroWhere($this->uid);
            $where = [];
            $where[] = ["a.t_show_start_time", "<=", time()];
            $where[] = ["a.t_show_end_time", ">=", time()];
            $where[] = ["a.base_type", "=", 6];
            if(!empty($lang)){
                $where[] = ["b.lang", "=", $lang];
            }
            $where_str = []; $tree = -1;
            $uinfo = UserModel::where("id", $this->uid)->field("tree,level_id")->find();
            if(isset($uinfo['tree'])){
                if(isset($tree1[1])){
                    $tree = $tree1[1];
                }
            }
            $user_tag = UserTagModel::where(['uid'=>$this->uid])->value('tag_id') ??-1;
            $where_str[] = "(a.is_consist = 2 and (".
                AgentService::getAgentWhere('agent_id', true, 'a', true, '', $isNeedZeroWhere)
                .") and a.tag_id = IF(a.tag_id > 0,$user_tag,0)) or (a.is_consist = 1 and (".
                AgentService::getAgentWhere('agent_id', true, 'a', false, '', $isNeedZeroWhere)
                .") and a.tag_id <> $user_tag)";

            $action = ActionModel::alias("a")
            ->join("dq_news b","a.id=b.action_id")
            ->where(implode(" and ", $where_str))
            ->where($where)
            ->field("a.id,a.base_type,a.type_id,a.auto_apply,a.base_name,a.dis_code,a.c_label,a.auto_apply,a.t_apply_start_time,a.t_apply_end_time, b.news_title as c_title, b.news_intro as c_intro, b.news_images as c_img, b.news_content as c_detail,a.is_hot,a.jump_url,a.date_type,a.game_type,a.level_json,a.reg_json,a.rebate_type,a.reward_max")
            ->limit((($pagenow-1)*$limit),$limit)
            ->order("a.sort DESC, a.id desc")
            ->select();

            foreach ($action as &$val){
                $val = $this->jyjdata($val,$uinfo);
                $val['t_apply_start_time'] = Util::i18n_datetime2str($val['t_apply_start_time']);
                $val['t_apply_end_time'] = Util::i18n_datetime2str($val['t_apply_end_time']);
                unset($val['level_json'],$val['game_type'],$val['reg_json'],$val['rebate_type'],$val['reward_max']);
            }
            $rsps['data']=$action;
            $rsps['code']=1;
            $rsps['total']= ActionModel::alias("a")
                ->join("dq_news b","a.id=b.action_id")
                ->where(implode(" and ", $where_str))
                ->where($where)->count();
            if(!empty($action[0])){
                $ApiRedis->set($redis_key,$rsps,180);
            }
        }
        return $rsps;
    }


     public function jyjdata($val,$uinfo){
        $whereStr = '';
        $where2 = [];
         $game_type = json_decode($val['game_type'],true) ??[];
         $level_json = json_decode($val['level_json'],true) ??[];
         $t_apply_start_time = date('Ymd',$val['t_apply_start_time']);
         $t_apply_end_time = date('Ymd',$val['t_apply_end_time']);
         $where2[] = ['su_uid','=',$this->uid];
         $where2[] = ['su_dt','between',[$t_apply_start_time,$t_apply_end_time]];
         $wh = [
            ['uid','=',$this->uid],
            ['action_id','=',$val['id']],
            ['ps_status','in','0,1']
        ];
        if($val['date_type'] == 1){
            $info = ActionApplyModel::whereDay("add_time")->where($wh)->value("id");
        }else{
            $info = ActionApplyModel::whereWeek("add_time")->where($wh)->value("id");
        }
        if($info){
            $jyj_type = 1;  //明日预计领取 今日/本周
            if($val['date_type'] == 1){
                $andtime = date('Ymd',Carbon::now()->today()->timestamp);
                $where2 [] = ['su_dt','=',$andtime];
            }else{
                 $andtime =  date('Ymd',Carbon::now()->startOfWeek()->timestamp);
                 $endtime =  date('Ymd',Carbon::now()->endOfWeek()->timestamp);
                $where2 [] =  ['su_dt','between',[$andtime,$endtime]];
            }
        }else{
            $jyj_type = 2; //今日可领取 昨日/本周
            if($val['date_type'] == 1){
                $andtime = date('Ymd',Carbon::yesterday()->timestamp);
                $where2 [] = ['su_dt','=',$andtime];
            }else{
                $andtime = date('Ymd',Carbon::now()->startOfWeek()->timestamp);
                $endtime = date('Ymd',Carbon::now()->endOfWeek()->timestamp);
                $where2 [] =  ['su_dt','between',[$andtime,$endtime]];

            }
        }
         if($game_type){
            $sql = GamesModel::where([['g_type','in',$game_type]])->field('g_id')->buildSql();
            $whereStr = 'su_gid in ('.$sql .')';
        }
         $tz_loss = getTransportTzloss($where2,$whereStr);
        if($jyj_type == 2 && $tz_loss <= 0){
             $where3 = [
                 ['su_uid','=',$this->uid],
                 ['su_dt','between',[date('Ymd',$val['t_apply_start_time']),date('Ymd',$val['t_apply_end_time'])]],
             ];
             if($val['date_type'] == 1){
                 $andtimes = date('Ymd',Carbon::now()->today()->timestamp);
                 $where3 [] = ['su_dt','=',$andtimes];
            }else{
                 $andtimes =  date('Ymd',Carbon::now()->startOfWeek()->timestamp);
                 $endtimes =  date('Ymd',Carbon::now()->endOfWeek()->timestamp);
                 $where3 [] =  ['su_dt','between',[$andtimes,$endtimes]];
            }
            $tz_loss = getTransportTzloss($where3,$whereStr);
            $jyj_type = 1;
        }
         $tz_loss = trim($tz_loss,'-');
         $price = 0;
         if( isset($val['rebate_type']) && $val['rebate_type'] == 1 ){
             //按金额奖励
             $regJson = json_decode($val['reg_json'], true);
             foreach ($regJson as $v){
                 if ($tz_loss >= $v['min_reg'] && $tz_loss <= $v['max_reg']) {
                     //判断类型
                     if( $v['type'] == 1 ){
                         //固定金额奖励
                         $price = $v['bl_or_amount'];
                     }else{
                         //按比例奖励
                         $price = round( $tz_loss * ($v['bl_or_amount'] / 100), 2);
                     }
                     break;
                 }
             }
         }else{
             //按VIP等级奖励
             $proportion =0;
             if( $uinfo ){
                 foreach ($level_json as $v){
                     if($v['level'] == $uinfo['level_id']){
                         $proportion = $v['proportion'] ;
                     }
                 }
             }
             if($proportion > 0){
                 $price = round($tz_loss*$proportion/100,2);
             }
         }
         if( isset($val['reward_max']) && $val['reward_max'] > 0 ){
             //奖励上限
             $price = min($price, $val['reward_max']);
         }


        $val['jyj_price'] = $price;
        $val['jyj_type'] = $jyj_type;
        return $val;
    }

    //推送活动完成情况给用户
    public function pushMessageToFinishTaskUser($uid = 0){
        $apiRedis=new ApiRedis();
        //获取用户数据
        $userDetail = UserModel::where("id", $uid)->field('tree,level_id')->find();
        if( !$userDetail ){
            return false;
        }
        //获取手动参与活动列表
        $condition = [];
        $condition[] = ['t_apply_start_time', '<=', time()];
        $condition[] = ['t_apply_end_time', '>=', time()];
        $condition[] = ['auto_apply', '=', 0];
        $actionList = ActionModel::where($condition)->select();
        foreach ($actionList as $v){
            //两小时内推送过则不再推送
            $pushUserKey = 'push_user_finish_action_key_'.$uid.'_'.$v['id'];
            if( $apiRedis->has($pushUserKey) ){
                continue;
            }
            //判断代理线
            $userAgentIds = array_filter(explode('/', $userDetail->tree));
            $judgeAgentIdResult = false;
            foreach (explode(',', $v['agent_id']) as $_v){
                if( in_array($_v, $userAgentIds) ){
                    $judgeAgentIdResult = true;
                }
            }
            if( $v['agent_id'] && !$judgeAgentIdResult ){
                continue;
            }
            //判断标签
            $userLevelId = $userDetail->level_id;
            if( $v['zg_level'] != 0 && $v['zg_level'] != $userLevelId){
                continue;
            }
            //判断标签
            $user_tag_id = MemberService::getUserTagId($uid);
            $user_tag_id[] = 0;
            if( !in_array($v['tag_id'], $user_tag_id) ){
                continue;
            }
            //判断用户是否能申请活动
            $params = [
                'action_id' => $v['id'],
            ];
            $this->uid = $uid;
            try {
                if( $this->apply_run($params, true) ){
                    //完成任务且没有领取给用户发送没有领取通知
                    MessageService::sendTaskFinishMessageNotify( $uid, 'action');
                    //设置缓存两小时内不再推送
                    $apiRedis->set($pushUserKey, 1, 3600 * 2);
                }
            }catch (\Exception $e){
                continue;
            }

        }
        return true;
    }

}