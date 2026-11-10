<?php
namespace app\promote\controller;
use app\common\model\TaskModel;
use app\common\model\UserAgentModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\service\BetService;
use app\common\service\GameService;
use app\common\service\UserOfflineService;
use app\common\Utils;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use app\common\model\PaySetModel;
use app\common\lib\Util;
use app\common\lib\Respons;
use think\facade\Db;
use think\facade\Session;
use app\common\model\ArtificialBalanceModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\ActionApplyModel;
use think\facade\View;
use think\facade\Request;
use app\common\redis\CommomRedis;
class AgentReport extends Base
{
    public function index(){
        $param = Request::param();
        if (isset($param['select'])) {
            $page=$param['page'];
            $limit=$param['limit'];
            $where=[];
            $UserModel =new UserModel();
            if (isset($param['username']) && $param['username']!='') {
                $where[]=["a.username","like","%".$param['username']."%"];
            }
            
            if (isset($param['rec_code']) && $param['rec_code']!='') {
                $where[]=["a.rec_code","=",$param['rec_code']];
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
                $where[] = ["a.rec_id","=",0];//获取顶级代理
                $where[]=["b.status","=",3];//通过的代理
            }
            $start_time = $end_time = '';
            if (isset($param['date']) && $param['date']!='') {
                $date=explode(' - ',$param['date']);
                $start_time = strtotime($date[0]." 00:00:00");
                $end_time = strtotime($date[1]." 23:59:59");
            }else{
                $start_time = strtotime(date("Y-m-d")." 00:00:00");
                $end_time = strtotime(date("Y-m-d")." 23:59:59");
            }
            
            $where_s[] = ["a.lebal","=",1];//正式用户
            $where_s1[] = ["a.lebal","=",1];//正式用户
            if(!empty($end_time) && !empty($start_time)){
                $where_s[0]=["a.add_time",">=", $start_time];
                $where_s[1]=["a.add_time","<=", $end_time];
                $where_s1[0]=["a.update_time",">=", $start_time];
                $where_s1[1]=["a.update_time","<=", $end_time];
            }
            if (isset($param['id']) && !empty($param['id'])) {     
                $rsps['count']=$UserModel->alias("a")->where($where)->where($where_str)->count();
                $data = $UserModel->alias("a")->where($where)->where($where_str)->limit((($page-1)*$limit),$limit)->order("id desc")->select();
            }else{
                $rsps['count']=$UserModel->alias("a")->join("dq_user_agent b","a.id=b.uid")->where($where)->group('b.uid')->count();
                $data = $UserModel->alias("a")->join("dq_user_agent b","a.id=b.uid")->where($where)->limit((($page-1)*$limit),$limit)->order("id desc")->field("a.*")->select();
            }

            $arr=[];

            foreach ($data as $key => $value) {
                $agent_sql = unLevelSql($value['id'], 0, 0, $value['tree']);
                $user = $UserModel->where("id", $value['id'])->find();
                $arr[$key]['count_pnumber'] = $UserModel->where("tree like '".$value['tree']."%'")->count();
                $arr[$key]['new_reg'] = $UserModel->alias('a')->where("tree like '".$value['tree']."%'")->where($where_s)->count();
                $arr[$key]['rec_code']=$value['rec_code'];
                $arr[$key]['username']=$value['username'];
                $arr[$key]['uid']=$value['id'];
                if (isset($param['id']) && !empty($param['id'])) {  
                    $arr[$key]['recharge_pnumber'] = UserOfflineModel::getRechargeUserCount($where_s1, "uid in(".$agent_sql.")");
                    $recharge = UserOfflineModel::getRechargeUserAmount($where_s1, "uid in(".$agent_sql.")");
                    $arr[$key]['recharge_amount'] = $recharge;                    
                    $arr[$key]['first_recharge_pnumber'] =  UserOfflineModel::getRechargeUserCount($where_s1, "uid in(".$agent_sql.") and a.first=1");
                    $arr[$key]['first_recharge_zb'] = round((($arr[$key]['first_recharge_pnumber']!=0?$arr[$key]['first_recharge_pnumber']/$arr[$key]['recharge_pnumber']:0)*100),2)."%";
                    $arr[$key]['two_recharge_pnumber'] =  $arr[$key]['recharge_pnumber'] - $arr[$key]['first_recharge_pnumber'];  
                    $arr[$key]['two_recharge_zb']=round((($arr[$key]['two_recharge_pnumber']!=0?$arr[$key]['two_recharge_pnumber']/$arr[$key]['recharge_pnumber']:0)*100),2)."%";
                    $arr[$key]['withdrawal_pnumber'] =  UserWithdrawalModel::getWithdrawUserCount($where_s1, "uid in(".$agent_sql.")");
                    $withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where_s1, "uid in(".$agent_sql.")");
                    $arr[$key]['withdrawal_amount'] = $withdrawal[2];
                    $arr[$key]['zd_price'] = 0;
                    $arr[$key]['zd_pnumber'] = 0;
                    $arr[$key]['max_level'] = $value['rec_level'] - $rec_user['rec_level'];
                    $is_agnet = UserAgentModel::where("uid",$value['id'])->where("status",3)->count();
                    $arr[$key]['is_agnet'] = $is_agnet>0?true:false;
                }
            }
            $rsps['data']=$arr;
            $rsps['code']=1;
            return Respons::run($rsps);
        }
        if(isset($param['id'])){
            $UserModel =new UserModel();
            $id = intval($param['id']);
            $agent_sql = unLevelSql($id);
            $count_pnumber = $UserModel->where("id in(".$agent_sql.")")->count();
            $user = $UserModel->where("id", $id)->find();
            View::assign('count_pnumber',$count_pnumber);
            View::assign('user',$user);
            $temp = 'agent_report/index1';
        }else{
            $id = '';
            $temp = 'agent_report/index';
        }
        View::assign('id',$id);
        View::assign('selected',$param['selected']??0);
        return View::fetch($temp);
    }
    
    public function user(){
        $param = Request::param();
        $page = $param['page'];
        $agent = $this->user;
        $username = $agent['username'];
        $tree = $agent['tree'];
        if(!isset($param['rec_level'])){
            $param['rec_level'] = 0;
        }
        $limit = $param['limit'];
        if (isset($param['date']) && $param['date']!='') {
            $date = explode(' - ',$param['date']);
        }else{
            $date = [date("Y-m-d"), date("Y-m-d")];
        }
        $where1 = [];
        $CommomRedis= new CommomRedis();
        $redis_key = md5(json_encode(['list', $date, $username, $param, $page, $limit]));
        $where1[]=["a.update_time",">=", Utils::getQueryDateTime($date[0])];
        $where1[]=["a.update_time","<=", Utils::getQueryDateTime($date[1], true)];
//        if (!(isset($param['username']) && $param['username']!='')) {
//            return $this->error('请输入账号');
//        }
        $user = UserModel::alias("a")
            ->join("dq_user_agent b","a.id=b.uid")
            ->where("b.status",3)
            ->where("a.username", $username)
            ->field("a.id,a.rec_level,a.tree")
            ->find();

        if (!isset($user['id']) || strpos($user['tree'], $tree) === false) {
            return $this->error('账号信息不存在');
        }
        else {
            if ($CommomRedis->has($redis_key)) {
                $data = json_decode($CommomRedis->get($redis_key), true);
            } else {
                if(!empty($param['rec_level'])){
                    $rec_level = $user['rec_level'] + intval($param['rec_level']);
                    $where_str = "a.rec_level=".$rec_level." and a.tree like '".$user['tree']."%'";
                }else{
                    $where_str = "a.tree like '".$user['tree']."%'";
                }
                $_condition = [];
                if (isset($param['username']) && $param['username']!='') {
                    if(is_numeric($param['username'])){
                        $_condition[]=["a.id|a.phone", "=", $param['username']];
                    }else{
                        $_condition[]=["a.username|a.mail|a.phone", "=", $param['username']];
                    }
                }
                if (isset($param['rec_id']) && $param['rec_id']!='') {
                    //查询rec_id
                    $sqlwhere = [];
                    if (is_numeric($param['rec_id'])) {
                        $sqlwhere[] = ["id|rec_code|phone", "=", $param['rec_id']];
                    } else {
                        $sqlwhere[] = ["mail|username", "=", $param['rec_id']];
                    }
                    $rec_info = UserModel::where($sqlwhere)->find();
                    if (empty($rec_info)) {
                        $result['code'] = 0;
                        $result['msg'] = '上级UID或邀请码有误';
                        return Respons::run($result);
                    }
                    if (isset($param['like_all_next']) && $param['like_all_next'] == 1) {
                        $_condition[]=["a.tree", "like", "{$rec_info['tree']}%"];
                    } else {
                        $_condition[]=["a.rec_id", "=", $rec_info['id']];
                    }
                }
                $_condition[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
                $_condition[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];

                $data = UserModel::alias("a")->where("a.lebal=1")->where($_condition)->where($where_str)->order("id desc")->paginate($limit,false,['query' => $param]);
                foreach ($data as $key => $value) {
                    $data[$key]['rec_name'] = UserModel::where("id", $value['rec_id'])->value("username");
                    $data[$key]['add_time']=date("Y-m-d H:i:s",$value['add_time']);
                    $data[$key]['recharge'] = UserOfflineModel::getRechargeUserAmount($where1, [["a.uid","=",$value['id']]]);// 充值总额
                    $withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where1, [["a.uid","=",$value['id']]]);
                    $data[$key]['withdrawal'] = $withdrawal[2]; // 提现总额
                    $data[$key]['rec_level'] = $value['rec_level'] - $user['rec_level'];
                    $data[$key]['balance'] = $value['balance'] * 1;
                }
                $CommomRedis->set(md5('list'.$redis_key.$page), json_encode($data), 60);
            }
        }
        $result = [
            'data' => $data,
        ];
        if ($page == 1) {
            $max_level = UserModel::where('lebal = 1')->max("rec_level");
            $result['max_level'] = $max_level;

            $where = [];
            $where1 = [];
            $where[] = ["a.lebal","=",1];//正式用户
            $where1[] = ["a.lebal","=",1];//正式用户
            $ag_where = [];
            if (isset($param['date']) && $param['date']!='') {
                $date = explode(' - ',$param['date']);
            }else{
                $date = [date("Y-m-d"), date("Y-m-d")];
            }
            $CommomRedis= new CommomRedis();
            $redis_key = md5(json_encode([$username, $param, $date]));
            $where[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
            $where1[]=["a.update_time",">=", Utils::getQueryDateTime($date[0])];
            $where1[]=["a.update_time","<=", Utils::getQueryDateTime($date[1], true)];
//            if (isset($param['username']) && $param['username']!='') {
//                $ag_where[]=['a.username',"=",$param['username']];
//            }
            if (isset($param['username']) && $param['username']!='') {
                if(is_numeric($param['username'])){
                    $ag_where[]=["a.id|a.phone", "=", $param['username']];
                    $where[]=["a.id|a.phone", "=", $param['username']];
                    $where1[]=["b.id|b.phone", "=", $param['username']];
                }else{
                    $ag_where[]=["a.username|a.mail|a.phone", "=", $param['username']];
                    $where[]=["a.username|a.mail|a.phone", "=", $param['username']];
                    $where1[]=["b.username|b.mail|b.phone", "=", $param['username']];
                }
            }else{
                $ag_where[]=['a.username',"=", $username];
            }
            if (isset($param['rec_id']) && $param['rec_id']!='') {
                //查询rec_id
                $sqlwhere = [];
                if (is_numeric($param['rec_id'])) {
                    $sqlwhere[] = ["id|rec_code|phone", "=", $param['rec_id']];
                } else {
                    $sqlwhere[] = ["mail|username", "=", $param['rec_id']];
                }
                $rec_info = UserModel::where($sqlwhere)->find();
                if (empty($rec_info)) {
                    $result['code'] = 0;
                    $result['msg'] = '上级UID或邀请码有误';
                    return Respons::run($result);
                }
                if (isset($param['like_all_next']) && $param['like_all_next'] == 1) {
                    $ag_where[]=["a.tree", "like", "{$rec_info['tree']}%"];
                    $where[]=["a.tree", "like", "{$rec_info['tree']}%"];
                    $where1[]=["b.tree", "like", "{$rec_info['tree']}%"];
                } else {
                    $ag_where[]=["a.rec_id", "=", $rec_info['id']];
                    $where[]=["a.rec_id", "=", $rec_info['id']];
                    $where1[]=["b.rec_id", "=", $rec_info['id']];
                }
            }
            $default_data = [
                'off_cz_pnumber' => 0, // 充值人数
                'off_cz_price' => 0, // 充值总额
                'sc_price' => 0, // 充值总额
                'off_tpnumber' => 0, // 提现人数
                'off_tprice' => 0, // 提现总额
                'sc_pnumber' => 0, //首充总人数
                'sc_bfb' => 0, //首充比例
                'fc_pnumber' => 0, //复充总人数
                'fc_bfb' => 0, //复充比例
                'reg_number' => 0,
                'count_pnumber' => 0, //总人数
                'count_balance' => 0, //总余额
            ];
            $stat_data = [];
            if (isset($ag_where[0])) {
                if (!empty($CommomRedis->get($redis_key))) {
                    $stat_data = json_decode($CommomRedis->get($redis_key), true);
                } else {
                    //TODO 后面修改，不需要必须是代理
                    $rec= UserModel::alias("a")
                        ->join("dq_user_agent b","a.id=b.uid")
                        ->where($ag_where)
//                        ->where("b.status",3)
                        ->field("a.id,a.rec_level,a.tree")
                        ->find();//要是代理且是通过
                    if (!isset($rec['id']) || strpos($rec['tree'], $tree) === false) {
                        $stat_data = $default_data;
                    } else {
                        $str_uid = unLevelSql($rec['id'], $rec['rec_level'], $param['rec_level'], $rec['tree']);
                        $stat_data['off_cz_pnumber'] = UserOfflineModel::getRechargeUserCount($where1, "a.uid in(".$str_uid.")"); // 充值人数
                        $recharge = UserOfflineModel::getRechargeUserAmount($where1, "uid in(".$str_uid.")");
                        $stat_data['off_cz_price'] = $recharge; // 充值总额
                        $stat_data['off_tpnumber'] =  UserWithdrawalModel::getWithdrawUserCount($where1, "uid in(".$str_uid.")"); // 提现人数
                        $withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where1, "uid in(".$str_uid.")");
                        $stat_data['off_tprice'] = $withdrawal[2]; // 提现总额
                        $stat_data['sc_pnumber'] =  UserOfflineModel::getRechargeUserCount($where1, "uid in(".$str_uid.") and a.first=1"); //首充总人数
                        $recharge = UserOfflineModel::getRechargeUserAmount($where1, "uid in(".$str_uid.") and a.first=1");
                        $stat_data['sc_price'] = $recharge; // 首充总额
                        $stat_data['sc_bfb'] = round((($stat_data['sc_pnumber']!=0?$stat_data['sc_pnumber']/$stat_data['off_cz_pnumber']:0)*100),2);//首充比例
                        $stat_data['fc_pnumber'] = $stat_data['off_cz_pnumber'] - $stat_data['sc_pnumber'];
                        $stat_data['fc_bfb']=round((($stat_data['fc_pnumber']!=0?$stat_data['fc_pnumber']/$stat_data['off_cz_pnumber']:0)*100),2);//复充比例
                        $stat_data['reg_number'] = UserModel::alias('a')->where("id in(".$str_uid.")")->where($where)->count();
                        $stat_data['count_pnumber'] = UserModel::alias("a")->where($where)->where("id in(".$str_uid.") and a.lebal=1")->count();//总人数
//                        $stat_data['count_pnumber'] = 99;
                        $stat_data['count_balance'] = UserModel::alias("a")->where($where)->where("id in(".$str_uid.") and a.lebal=1")->sum("a.balance");//总余额
                        $CommomRedis->set($redis_key, json_encode($stat_data), 60);
                    }
                }
            }
            $result['stat'] = [$stat_data];
        }
        return $this->success($result);
    }

    public function userzhudan(){
        $param = Request::param();
        $agent = $this->user;
//        if(isset($param['username']) || $param['username']){
//            $param['username'] = $agent['username'];
//        }
        $username = $agent['username'];
        $tree = $agent['tree'];
        $page = $param['page'];
        $limit = $param['limit'];
        if(!isset($param['rec_level'])){
            $param['rec_level'] = 0;
        }
        if (isset($param['date']) && $param['date']!='') {
            $date = explode(' - ',$param['date']);
        }else{
            $date = [date("Y-m-d"), date("Y-m-d")];
        }
        $start_time = Utils::getQueryDateTime($date[0]);
        $end_time = Utils::getQueryDateTime($date[1], true);
        $user = UserModel::alias("a")
            ->join("dq_user_agent b","a.id=b.uid")
            ->where("b.status",3)
            ->where("a.username", $username)
            ->field("a.id,a.rec_level,a.tree")
            ->find();
        if (!isset($user['id']) || strpos($user['tree'], $tree) === false) {
            return $this->error('账号信息不存在');
        }

        $CommomRedis= new CommomRedis();
        $redis_key = md5(json_encode(["listzhudan", $date, $param]));
        $CommomRedis->delete($redis_key);
        if ($CommomRedis->has($redis_key)) {
            $data = json_decode($CommomRedis->get($redis_key), true);
        } else {
            if(!empty($param['rec_level'])){
                $rec_level = $user['rec_level'] + intval($param['rec_level']);
                $where_str = "a.rec_level=".$rec_level." and a.tree like '".$user['tree']."%'";
            }else{
                $where_str = "a.tree like '".$user['tree']."%'";
            }
            $where = [];
            $where[] = ['a.lebal', '=', 1];
            if (isset($param['username']) && $param['username']!='') {
                if(is_numeric($param['username'])){
                    $where[]=["a.id|a.phone", "=", $param['username']];
                }else{
                    $where[]=["a.username|a.mail|a.phone", "=", $param['username']];
                }
            }
            if (isset($param['rec_id']) && $param['rec_id']!='') {
                //查询rec_id
                $sqlwhere = [];
                if (is_numeric($param['rec_id'])) {
                    $sqlwhere[] = ["id|rec_code|phone", "=", $param['rec_id']];
                } else {
                    $sqlwhere[] = ["mail|username", "=", $param['rec_id']];
                }
                $rec_info = UserModel::where($sqlwhere)->find();
                if (empty($rec_info)) {
                    $result['code'] = 0;
                    $result['msg'] = '上级UID或邀请码有误';
                    return Respons::run($result);
                }
                if (isset($param['like_all_next']) && $param['like_all_next'] == 1) {
                    $where[]=["a.tree", "like", "{$rec_info['tree']}%"];
                } else {
                    $where[]=["a.rec_id", "=", $rec_info['id']];
                }
            }
            $where[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];

            $data = UserModel::alias("a")->where($where)->where($where_str)->order("id desc")->paginate($limit,false,['query' => $param]);

            //总站的投注数据
            $startDate = date('Ymd', $start_time);
            $endDate = date('Ymd', $end_time);
            $totalCondition[] = ['su_dt', 'between', [$startDate, $endDate]];
            $table = BetService::getThirdStatsTable();
            $totalValidBet = Db::name($table['_stats_global'])
                ->where($totalCondition)
                ->sum('su_valid_bet')?:0;
            foreach ($data as $key => $value) {
                $data[$key]['rec_name'] = $username;
                //用户的投注数据
                $tz_data = GameService::statGameBet($value['id'], $start_time, $end_time);
                $data[$key]['zd_price'] = $tz_data['tz_price'];//投注金额
                $data[$key]['zd_number'] = $tz_data['tz_num'];//投注笔数
                $data[$key]['bet_valid_amount'] = $tz_data['bet_valid_amount'];
                $data[$key]['win_price'] = $tz_data['tz_win'];
                $data[$key]['order_loss'] = $tz_data['total_win_lost'];
                $data[$key]['lose_price'] = 0;
                $data[$key]['rec_level'] = $value['rec_level'] - $user['rec_level'];
                $data[$key]['total_contribution_rate'] = '0%';
                if( $totalValidBet > 0 && $tz_data['bet_valid_amount'] > 0 ){
                    $data[$key]['total_contribution_rate'] = round($tz_data['bet_valid_amount'] / $totalValidBet * 100, 2).'%';
                }
            }
            $CommomRedis->set($redis_key, json_encode($data), 60);
        }
        $result = [
            'data' => $data,
        ];
        if ($page == 1) {
            $redis_key = md5(json_encode([$date, $param]));
            $stat_data = [];
            $CommomRedis= new CommomRedis();
            if ($CommomRedis->has($redis_key)) {
                $stat_data = json_decode($CommomRedis->get($redis_key), true);
            } else {
                $str_uid = unLevelSql($user['id'], $user['rec_level'], $param['rec_level'], $user['tree']);
                $tz_data = GameService::statGameBet(Db::raw($str_uid), $start_time, $end_time);
                $stat_data['zd_price'] = $tz_data['tz_price'];//投注金额
                $stat_data['zd_number'] = $tz_data['tz_number'];//投注人数
                $stat_data['zd_count'] = $tz_data['tz_num'];//投注笔数
                $stat_data['bet_valid_amount'] = $tz_data['bet_valid_amount'];
                $stat_data['order_win'] = $tz_data['tz_win'];
                $stat_data['order_loss'] = $tz_data['total_win_lost'];

                $CommomRedis->set($redis_key, json_encode($data), 60);
            }
            $result['stat'] = [$stat_data];
            $result['max_level'] = UserModel::where('lebal = 1')->max("rec_level");
        }

        return $this->success($result);
    }

    public function usergiving(){
        $param = Request::param();
        $agent = $this->user;
//        if(isset($param['username']) || $param['username']){
//            $param['username'] = $agent['username'];
//        }
        $username = $agent['username'];
        $tree = $agent['tree'];
        $page = $param['page'];
        if(!isset($param['rec_level'])){
            $param['rec_level'] = 0;
        }
        $limit = $param['limit'];
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
        }else{
            $date = [date("Y-m-d"), date("Y-m-d")];
        }
        $start_time = Utils::getQueryDateTime($date[0]);
        $end_time = Utils::getQueryDateTime($date[1], true);
        $where[]=["a.add_time",">=", $start_time];
        $where[]=["a.add_time","<=", $end_time];
        $where1[]=["c.lebal","=",1];//正式用户
        $where1[]=["b.add_time",">=",$start_time];
        $where1[]=["b.add_time","<=",$end_time];
        $where2[]=["a.create_time",">=",$start_time];
        $where2[]=["a.create_time","<=",$end_time];
        $user = UserModel::alias("a")
            ->join("dq_user_agent b","a.id=b.uid")
            ->where("b.status",3)
            ->where("a.username",$username)
            ->field("a.id,a.rec_level,a.tree")
            ->find();

        if (!isset($user['id']) || strpos($user['tree'], $tree) === false) {
            return $this->error('账号信息不存在');
        }

        $CommomRedis= new CommomRedis();
        $redis_key = md5(json_encode(['usergiving', $date, $param]));
        $CommomRedis->delete($redis_key);
        if (false && !empty($CommomRedis->get($redis_key))) {
            $data = json_decode($CommomRedis->get($redis_key), true);
        } else {
            if(!empty($param['rec_level'])){
                $rec_level = $user['rec_level'] + intval($param['rec_level']);
                $where_str = "a.rec_level=".$rec_level." and a.tree like '".$user['tree']."%'";
            }else{
                $where_str = "a.tree like '".$user['tree']."%'";
            }
            $where = $whereUser = [];
            $where[] = ['a.lebal', '=', 1];
            $whereUser[] = ['c.lebal', '=', 1];
            if (isset($param['username']) && $param['username']!='') {
                if(is_numeric($param['username'])){
                    $where[]=["a.id|a.phone", "=", $param['username']];
                    $whereUser[]=["c.id|c.phone", "=", $param['username']];
                }else{
                    $where[]=["a.username|a.mail|a.phone", "=", $param['username']];
                    $whereUser[]=["c.username|c.mail|c.phone", "=", $param['username']];
                }
            }
            if (isset($param['rec_id']) && $param['rec_id']!='') {
                //查询rec_id
                $sqlwhere = [];
                if (is_numeric($param['rec_id'])) {
                    $sqlwhere[] = ["id|rec_code|phone", "=", $param['rec_id']];
                } else {
                    $sqlwhere[] = ["mail|username", "=", $param['rec_id']];
                }
                $rec_info = UserModel::where($sqlwhere)->find();
                if (empty($rec_info)) {
                    $result['code'] = 0;
                    $result['msg'] = '上级UID或邀请码有误';
                    return Respons::run($result);
                }
                if (isset($param['like_all_next']) && $param['like_all_next'] == 1) {
                    $where[]=["a.tree", "like", "{$rec_info['tree']}%"];
                    $whereUser[]=["c.tree", "like", "{$rec_info['tree']}%"];
                } else {
                    $where[]=["a.rec_id", "=", $rec_info['id']];
                    $whereUser[]=["c.rec_id", "=", $rec_info['id']];
                }
            }
            $where[]=["a.add_time",">=", Utils::getQueryDateTime($date[0])];
            $where[]=["a.add_time","<=", Utils::getQueryDateTime($date[1], true)];
            $whereUser[]=["c.add_time",">=", Utils::getQueryDateTime($date[0])];
            $whereUser[]=["c.add_time","<=", Utils::getQueryDateTime($date[1], true)];


            $data = UserModel::alias("a")->where($where)->where($where_str)->order("id desc")->paginate($limit,false,['query' => $param]);
            foreach ($data as $key => $value) {
                $data[$key]['rec_level'] = $value['rec_level'] - $user['rec_level'];
                $data[$key]['rec_name'] = $username;
                $data[$key]['a_add'] = ArtificialBalanceModel::alias("a")->join("dq_user_balance_mx b","a.mx_id=b.id")->join("dq_user c","b.uid=c.id")->where($whereUser)->where('c.id', $value['id'])->where("a.a_type",1)->where($where1)->sum("b.price");//人工增加
                $data[$key]['a_reduce'] = ArtificialBalanceModel::alias("a")->join("dq_user_balance_mx b","a.mx_id=b.id")->join("dq_user c","b.uid=c.id")->where($whereUser)->where("a.a_type",2)->where($where1)->where('c.id', $value['id'])->sum("b.price");//人工减少
                $data[$key]['agent_price'] = AgentRakeBackModel::alias("a")->join("dq_user c","a.uid=c.id")->where("a.status in (3,4)")->where($whereUser)->where($where2)->where('c.id', $value['id'])->sum("a.money");//代理返佣金额
                $data[$key]['tg_price'] = AgentSellLogModel::alias("a")->join("dq_user c","a.uid=c.id")->where("a.status",3)->where($whereUser)->where($where2)->where('c.id', $value['id'])->sum("a.money");//推广奖励金额
                $data[$key]['action_price'] = ActionApplyModel::alias("a")->join("dq_user c","a.uid=c.id")->where($whereUser)->where([['a.ps_deta', ">=", $start_time], ['a.ps_deta', "<=", $end_time], ['a.ps_status', '=', 1]])->where('c.id', $value['id'])->sum("a.gd_amount");//活动优惠
                $data[$key]['give_price'] = ArtificialBalanceModel::alias("a")->join("dq_user_balance_mx b","a.mx_id=b.id")->join("dq_user c","b.uid=c.id")->where($whereUser)->where([["a.a_type", 'in', '3,4']])->where($where1)->where('c.id', $value['id'])->sum("b.price");//赠送彩金
            }
            $CommomRedis->set($redis_key, json_encode($data), 60);
        }
        $result = ['data' => $data];
        if ($page == 1) {
            $where1[]=["c.lebal","=",1];//正式用户
            $where1[]=["b.add_time",">=",$start_time];
            $where1[]=["b.add_time","<=",$end_time];
            $where2[]=["a.create_time",">=",$start_time];
            $where2[]=["a.create_time","<=",$end_time];
            $redis_key = md5(json_encode(['usergiving_total', $date, $param]));
            if (false && !empty($CommomRedis->get($redis_key))) {
                $stat_data = json_decode($CommomRedis->get($redis_key), true);
            } else {
                $str_uid = unLevelSql($user['id'], $user['rec_level'], $param['rec_level'], $user['tree']);
                $stat_data['a_add'] = ArtificialBalanceModel::alias("a")->join("dq_user_balance_mx b","a.mx_id=b.id")->join("dq_user c","b.uid=c.id")->where("b.uid in(".$str_uid.")")->where("a.a_type",1)->where($where1)->sum("b.price");//人工增加
                $stat_data['a_reduce'] = ArtificialBalanceModel::alias("a")->join("dq_user_balance_mx b","a.mx_id=b.id")->join("dq_user c","b.uid=c.id")->where("a.a_type",2)->where($where1)->where("b.uid in(".$str_uid.")")->sum("b.price");//人工减少
                $stat_data['agent_price'] = AgentRakeBackModel::alias("a")->join("dq_user c","a.uid=c.id")->where("a.status in (3,4)")->where($where2)->where("a.uid in(".$str_uid.")")->sum("a.money");//代理返佣金额
                $stat_data['tg_price'] = AgentSellLogModel::alias("a")->join("dq_user c","a.uid=c.id")->where("a.status",3)->where($where2)->where("a.uid in(".$str_uid.")")->sum("a.money");//推广奖励金额
                $stat_data['action_price'] = ActionApplyModel::alias("a")->join("dq_user c","a.uid=c.id")->where([['a.ps_deta', ">=", $start_time], ['a.ps_deta', "<=", $end_time], ['a.ps_status', '=', 1]])->where("a.uid in(".$str_uid.")")->sum("a.gd_amount");//活动优惠
                $stat_data['give_price'] = ArtificialBalanceModel::alias("a")->join("dq_user_balance_mx b","a.mx_id=b.id")->join("dq_user c","b.uid=c.id")->where([["a.a_type", 'in', '3,4']])->where($where1)->where("b.uid in(".$str_uid.")")->sum("b.price");//赠送彩金
                $CommomRedis->set($redis_key, json_encode($stat_data), 60);
            }
            $result['stat'] = [$stat_data];
            $result['max_level'] = UserModel::where('lebal = 1')->max("rec_level");
        }

        return $this->success($result);
    }

    public function user_data_view(){
        $param = Request::instance()->param();
        View::assign('param',$param);
        return View::fetch('agent_report/user_data_view');
    }

    public function user_data(){
        $param = Request::instance()->param();
        $page = $param['page'];
        $limit = $param['limit'];
        $channel = Util::getOpenChannel();
        $agent = $this->user_agent;
        $tree = $agent['tree'];
//        $where = $this->getDate($param);
        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=["a.add_time",">=", strtotime($date[0]." 00:00:00")];
            $where[]=["a.add_time","<=", strtotime($date[1]." 23:59:59")];
            $where1[]=["a.update_time",">=", strtotime($date[0]." 00:00:00")];
            $where1[]=["a.update_time","<=", strtotime($date[1]." 23:59:59")];
        }else{
            $where[]=["a.add_time",">=", strtotime(date("Y-m-d")." 00:00:00")];
            $where[]=["a.add_time","<=", strtotime(date("Y-m-d")." 23:59:59")];
            $where1[]=["a.update_time",">=", strtotime(date("Y-m-d")." 00:00:00")];
            $where1[]=["a.update_time","<=", strtotime(date("Y-m-d")." 23:59:59")];
        }
        if (!(isset($param['username']) && $param['username']!='')) {
            return Respons::run(0,[],lang('请输入账号'));
        }
        $where[] = ['lebal','=',1];
        $user = UserModel::alias("a")
            ->join("dq_user_agent b","a.id=b.uid")
            ->where(["b.status"=>3,"a.username"=>$param['username']])
            ->field("a.id,a.tree,a.rec_level")
            ->find();
        if (!isset($user['id']) || strpos($user['tree'], $tree) === false) {
            return Respons::run(0,[],lang('账号信息不存在'));
        }
        
        $sql = unLevelSql($user['id'], $user['rec_level'], $param['rec_level']);

        $wh = [];
        $is_up = 0;
        if($param['type'] == 1){
            $model = new UserModel;
            $field = 'a.id';
            $w_id = 'a.id';
        }else if($param['type'] == 3 || $param['type'] == 4){
            if($param['type'] == 3){
                $wh[] = ['a.first', '=', 1];
            }
            $wh[] = ['a.status', "in", "3,4"];
            $model = new UserOfflineModel;
            $field = 'a.uid';
            $w_id = 'a.uid';
            $is_up = 1;
        }else if($param['type'] == 5){
            $model = new UserWithdrawalModel;
            $field = 'a.uid';
            $w_id = 'a.uid';
            $wh['a.status'] = [3,4];
            $is_up = 1;
        }else if($param['type'] == 6){
            $where[] = ['a.status', "in", "3,4"];
            $model = new UserOfflineModel;
            $field = 'a.uid';
            $w_id = 'a.uid';
            $is_up = 1;
        }
        if($is_up == 1){ // 如果是充提的话 时间戳字段要用update_time
            $nsql = $model::alias("a")
                ->where($wh)
                ->where($where1)
                ->where($w_id.' in ('.$sql.')')
                ->field($field)
                ->buildSql();
        }else{
            $nsql = $model::alias("a")
                ->where($wh)
                ->where($where)
                ->where($w_id.' in ('.$sql.')')
                ->field($field)
                ->buildSql();
        }
        if($param['type'] == 6){ // 充值列表的时候
                $data = UserOfflineModel::alias("a")
                    ->join("dq_user b","a.uid = b.id")
                    ->where('a.uid in ('.$nsql.')')
                    ->where('a.status in (3,4)')
                    ->where($where1)
                    ->order("id desc")
                    ->field("a.*,b.username")
                    ->paginate($limit,false,['query' => $param]);
                $rsps['data'] = $data;
            foreach($rsps['data'] as $key => &$val){
                $val['channel_name'] = isset($channel[$val['channel']])?lang($channel[$val['channel']]):"";
                $val['add_time'] = date("Y-m-d H:i:s", $val['add_time']);
                if($val['update_time']){
                    $val['update_time'] = date("Y-m-d H:i:s", $val['update_time']);
                }else{
                    $val['update_time'] = '';
                }
                if($val['pay_id'] > 0){
                    $val['pay_name'] = PaySetModel::where(['id'=>$val['pay_id']])->value('pay_name');
                }
            }
        }else{
            $data = UserModel::alias("a")
                ->where('a.id in ('.$nsql.')')
                ->order("id desc")
                ->paginate($limit,false,['query' => $param]);
            $rsps['data'] = $data;
            foreach ($rsps['data'] as $key => $value) {
                $rsps['data'][$key]['rec_name'] = UserModel::where("id", $value['rec_id'])->value("username");
                $rsps['data'][$key]['add_time']=date("Y-m-d H:i:s",$value['add_time']);
                if($param['type'] == 6){
                    $rsps['data'][$key]['recharge'] = $value['arrival_price']; // 充值总额
                }else{
                    $recharge = UserOfflineModel::getRechargeUserAmount($where1, [["a.uid","=",$value['id']]]);
                    $rsps['data'][$key]['recharge'] = $recharge; // 充值总额
                }

                $withdrawal = UserWithdrawalModel::getWithdrawUserAmount($where1, [["a.uid","=",$value['id']]]);
                $rsps['data'][$key]['withdrawal'] = $withdrawal[2]; // 提现总额

                $rsps['data'][$key]['zd_price'] = 0;//投注金额
                $rsps['data'][$key]['zd_number'] = 0;//投注笔数
                $rsps['data'][$key]['win_price'] = 0;
                $rsps['data'][$key]['lose_price'] = 0;
                $rsps['data'][$key]['rec_level'] = $value['rec_level'] - $user['rec_level'];
            }
        }

        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function game_report(){
        $param=Request::param();
        if( isset($param['date']) && $param['date'] ){
            $date=explode(' - ',$param['date']);
        }else{
            $date = [date("Y-m-d"), date("Y-m-d")];
        }
        $startTime = Utils::getQueryDateTime($date[0]);
        $endTime = Utils::getQueryDateTime($date[1], true);
        $start_date = date("Y-m-d", $startTime);
        $end_date = date("Y-m-d", $endTime);
        $start_date1 = date("Ymd", $startTime);
        $end_date1 = date("Ymd", $endTime);
        $data = [];
        $userSql = UserModel::where('tree', 'like', $this->user['tree'].'%')->field('id')->buildSql();
        $tree = $this->user['tree'];
        $condition = [];
        //投注相关
        $_condition = $totalCondition = $condition;
        $totalCondition[] = $_condition[] = ['su_dt', 'between', [$start_date1, $end_date1]];
        $_condition[] = ['tree', 'like', "{$this->user['tree']}%"];


        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        //总投注
        $bet = [];
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
        //贡献比
        $totalValidBet = Db::name($table['_stats_global'])
            ->where($totalCondition)
            ->sum('su_valid_bet')?:0;
        $bet['total_contribution_rate'] = '0%';
        if($totalValidBet > 0 && $bet['total_valid_bet'] > 0){
            $bet['total_contribution_rate'] = round($bet['total_valid_bet'] / $totalValidBet * 100, 2).'%';
        }
        $data['bet'] = $bet;

        //充值提现(成功)
        $_condition = [];
        $_conditionStr = 'uid in'.$userSql;
        $_condition[] = ['add_time', 'between', [$startTime, $endTime]];
        $_condition[] = ['status', 'in', [3,4]];
        $_condition1 = [];
        $_condition1[] = ['a.add_time', 'between', [$startTime, $endTime]];
        $_condition1Str = 'a.uid in'.$userSql;
        $recharge = [];
        //总充值
        $recharge['recharge_total_amount'] = UserOfflineModel::where($_condition)->where($_conditionStr)->sum('arrival_price');
        //首充金额
        $recharge['first_amount'] = UserOfflineModel::where($_condition)->where($_conditionStr)->where('first', '=', 1)->sum('arrival_price');
        //复充金额
        $recharge['recharge_amount'] = UserOfflineModel::where($_condition)->where($_conditionStr)->where('first', '<>', 1)->sum('arrival_price');
        //首充人数
        $recharge['first_person_count'] = UserOfflineModel::where($_condition)->where($_conditionStr)
            ->where('first', '=', 1)->group('uid')
            ->count();
        //复充人数
        $recharge['recharge_person_count'] = (new UserOfflineService())->getAgainRechargeAmount($_condition1, '', $_condition1Str);
        //提现总额
        $recharge['withdrawal_total_amount'] = UserWithdrawalModel::where($_condition)->where($_conditionStr)->sum('arrival_price');
        //提现人数
        $recharge['withdrawal_person_count'] = UserWithdrawalModel::where($_condition)->where($_conditionStr)
            ->group('uid')
            ->count();
        $data['recharge'] = $recharge;

        //彩金相关
        // 人工赠送彩金 + 会员等级彩金
        $__condition = [];
        $__condition[] = ['uid', 'in', function($query) use($tree) {
            $query->name('user')->where("tree", "LIKE", "{$tree}%")->field('id');
        }];
        $__condition[] = ['add_time', 'between', [$startTime, $endTime]];
        $__condition[] = ['a_type', 'in', [3,4]];
        $arr['bonus'] = ArtificialBalanceModel::where($__condition)->sum('price');

        // 免费转盘奖励
        $__condition = [];
        $__condition[] = ['is_free', '=', 1];
        $__condition[] = ['prize_type', '=', 1];
        $__condition[] = ['uid', 'in', function($query) use($tree) {
            $query->name('user')->where("tree", "LIKE", "{$tree}%")->field('id');
        }];
        $arr['free_lottery_price'] = Db::name('lottery_record')
            ->where($__condition)
            ->whereBetweenTime("time", $startTime, $endTime)->sum("price");
        // 优惠活动奖金
        $__condition = [];
        $__condition[] = ['ps_status', '=', 1];
        $__condition[] = ['uid', 'in', function($query) use($tree) {
            $query->name('user')->where("tree", "LIKE", "{$tree}%")->field('id');
        }];
        $arr['action_apply'] = Db::name('action_apply')
            ->where($__condition)->whereBetweenTime("add_time", $startTime, $endTime)
            ->sum(Db::raw('`gd_amount`+`ck_hl_amount`'));

        // 推广奖励
        $__condition = [];
        $__condition[] = ['status', '=', 3];
        $__condition[] = ['uid', 'in', function($query) use($tree) {
            $query->name('user')->where("tree", "LIKE", "{$tree}%")->field('id');
        }];
        $__condition[] = ['create_time', 'between', [$startTime, $endTime]];
        $arr['promotional_rebates'] = AgentSellLogModel::where($__condition)->sum('money');

        //任务奖励
        $arr['task_reward'] = Db::name('task_record')
            ->whereBetweenTime("create_time", $startTime, $endTime)
            ->where('reward_type', '=', TaskModel::BALANCE_REWARD_TYPE)
            ->where('uid', 'in', $userSql)
            ->sum('reward_amount_or_integral');

        //阶段任务奖励
        $arr['task_stage_reward'] = Db::name('task_stage_record')
            ->whereBetweenTime("create_time", $startTime, $endTime)
            ->where('uid', 'in', $userSql)
            ->sum('reward_amount');

        //等级奖励
        $arr['level_reward'] = Db::name('user_level_reward')
            ->whereBetweenTime("create_time", $startTime, $endTime)
            ->where('uid', 'in', function($query) use($tree) {
                $query->name('user')->where("tree", "LIKE", "{$tree}%")->field('id');
            })
            ->sum('reward_amount');
        $data['bonus'] = $arr;
        $rsps['data'] = $data;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

}