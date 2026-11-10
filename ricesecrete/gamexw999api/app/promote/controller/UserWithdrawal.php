<?php
namespace app\promote\controller;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\UserAdminLogModel;
use app\common\model\UserReportModel;
use app\common\model\UserModel;
use app\common\model\LangModel;
use app\common\lib\Respons;
use app\manage\factory\UserWithdrawalFactory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use app\common\model\PaySetModel;
use app\common\payment\Entry;
use app\common\redis\CommomRedis;
use app\common\lib\Util;
use app\common\Utils;
use think\facade\Session;
use think\facade\View;
use think\facade\Request;
use think\facade\Db;
use think\facade\Config;
class UserWithdrawal extends Base
{
    public function get_user_data(){
        $user = $this->user;
        return (new UserWithdrawalFactory($this->user_id))->get_user_data([
            ['a.uid', "IN", function($query) use($user){
                $query->name('user')->where([['tree','like', $user['tree'].'%']])->field('id');
            }]
        ]);
    }

    public function user_list(){
        $param=Request::param();
        if(!isset($param['uid'])){
            $param['uid'] = 0;
        }
        $channel = Util::getWOpenChannel();
        $p_wh['isdel'] = 0;
        $pay_type = PaySetModel::where($p_wh)->column('id,pay_name');
        View::assign('pay_type',$pay_type);
        View::assign("channel",$channel);
        View::assign('uid',$param['uid']);
        return View::fetch('user_withdrawal/list');
    }

    public function get_count(){
        $param = Request::instance()->param();
        $channel = Util::getWOpenChannel();
        $b_w = []; //银行卡的条件
        $u_w = []; //USDT的条件
        $b_w['a.channel'] = 0;
        $u_w['a.channel'] = 1;
        $w_w['a.channel'] = 2;
        $p_w['a.channel'] = 3;
        $b_w['a.status'] = 1;
        $u_w['a.status'] = 1;
        $w_w['a.status'] = 1;
        $p_w['a.status'] = 1;

        $where[] = ['a.lebal','=',1];
        $whereStr = '';
        $agent = $this->user_agent;
        $sql = UserModel::where([['tree','like', $agent['tree'].'%']])->field('id')->buildSql();
        $whereStr = 'a.uid in ('.$sql .')';
        
        if(isset($param['type']) && $param['type'] == 'verify'){
            $where[] = ['a.status','=',1];
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] =['a.uid','=',$param['uid']];
        }

        if (isset($param['username']) && $param['username']!='') {
            $uid = UserModel::where([['username','=',$param['username']]])->value("id");
            $where[] = ["a.uid","=", $uid];
        }

        if (isset($param['order_sn']) && $param['order_sn']!='') {
            $where[]=["a.order_sn", "=", $param['order_sn']];
        }

        if (isset($param['status']) && $param['status']!='') {
            $where[]=["a.status","=", $param['status']];
        }

        if (isset($param['bankcrad']) && $param['bankcrad']!='') {
            $where[]=["a.bankcrad","=", $param['bankcrad']];
        }

        if (isset($param['channel']) && $param['channel']!='') {
            $where[]=["a.channel","=", $param['channel']];
        }
        if (isset($param['min_price']) && $param['min_price']!='') {
            $where[]=['w_price',">=",$param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price']!='') {
            $where[]=['w_price',"<=",$param['max_price']];
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['uid','=',$param['uid']];
        }

        if(isset($param['pay_type']) && $param['pay_type'] > 0){
            $where[] = ['a.method_id','=',$param['pay_type']];
        }
        if(isset($param['time_type']) && $param['time_type'] != ''){
            if($param['time_type'] == 1){
                $time_type = 'a.add_time';
            }else{
                $time_type = 'a.update_time';
            }
        }else{
            $time_type = 'a.add_time';
        }

        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=[$time_type,">=",strtotime($date[0]." 00:00:00")];
            $where[]=[$time_type,"<=",strtotime($date[1]." 23:59:59")];
        }
        if(isset($channel[0])){
            $bank['stay'] = UserWithdrawalModel::alias('a')
                ->where($b_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //待审核
            $b_w['a.status'] = 2;
            $bank['no'] =  UserWithdrawalModel::alias('a')
                ->where($b_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //未通过
            $b_w['a.status'] = [3,4];
            $bank['yes'] =  UserWithdrawalModel::alias('a')
                ->where($b_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //已通过
            $b_w['a.status'] = 5;
            $bank['ing'] =  UserWithdrawalModel::alias('a')
                ->where($b_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //进行中
        }else{
            $bank = [];
        }
        
        if(isset($channel[1])){
            $usdt['stay'] = UserWithdrawalModel::alias('a')
                ->where($u_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //待审核
            $u_w['a.status'] = 2;
            $usdt['no'] =  UserWithdrawalModel::alias('a')
                ->where($u_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //未通过
            $u_w['a.status'] = [3,4];
            $usdt['yes'] =  UserWithdrawalModel::alias('a')
                ->where($u_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //已通过
            $u_w['a.status'] = 5;
            $usdt['ing'] =  UserWithdrawalModel::alias('a')
                ->where($u_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //进行中
        }else{
            $usdt = [];
        }
        
        if(isset($channel[2])){
            $wallet['stay'] = UserWithdrawalModel::alias('a')
                ->where($w_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //待审核
            $w_w['a.status'] = 2;
            $wallet['no'] =  UserWithdrawalModel::alias('a')
                ->where($w_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //未通过
            $w_w['a.status'] = [3,4];
            $wallet['yes'] =  UserWithdrawalModel::alias('a')
                ->where($w_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //已通过
            $w_w['a.status'] = 5;
            $wallet['ing'] =  UserWithdrawalModel::alias('a')
                ->where($w_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //进行中
        }else{
            $wallet = [];
        }
        
        if(isset($channel[3])){
            $pix['stay'] = UserWithdrawalModel::alias('a')
                ->where($p_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //待审核
            $p_w['a.status'] = 2;
            $pix['no'] =  UserWithdrawalModel::alias('a')
                ->where($p_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //未通过
            $p_w['a.status'] = [3,4];
            $pix['yes'] =  UserWithdrawalModel::alias('a')
                ->where($p_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //已通过
            $p_w['a.status'] = 5;
            $pix['ing'] =  UserWithdrawalModel::alias('a')
                ->where($p_w)
                ->where($where)
                ->where($whereStr)
                ->sum('w_price'); //进行中
        }else{
            $pix = [];
        }
        
        $data['data']['bank'] = $bank;
        $data['data']['usdt'] = $usdt;
        $data['data']['wallet'] = $wallet;
        $data['data']['pix'] = $pix;
        $data['code']=1;
        return Respons::run($data);
    }

    //提现排行榜
    public function leaderboard(){
        $param=Request::param();
        if (isset($param['select'])) {
            $page = $param['page'] ?? 1;
            $limit = $param['limit'] ?? 10;
            $where[] = ['a.arrival_price','>',0];
            $where[] = ['a.status','>',2];
            $time_type = 'a.add_time';
            $model = new UserWithdrawalModel();
            if (isset($param['username']) && $param['username']!='') {
                $where[] = ["b.username","like","%".$param['username']."%"];
            }

            if(isset($param['time_type']) && $param['time_type'] != ''){
                if($param['time_type'] == 1){
                    $time_type = 'a.add_time';
                }else{
                    $time_type = 'a.update_time';
                }
            }else{
                $time_type = 'a.add_time';
            }
            if (isset($param['date']) && $param['date']!='') {
                $date=explode(' - ',$param['date']);
                $where[]=[$time_type,">=",strtotime($date[0]." 00:00:00")];
                $where[]=[$time_type,"<=",strtotime($date[1]." 23:59:59")];
            }

            $where1 = '';
            if (isset($param['min_price']) && !empty($param['min_price'])) {
                $where1 .= 'sum(arrival_price) >= '.$param['min_price'];
            }

            if (isset($param['max_price']) && !empty($param['max_price'])) {
                $and = empty($param['min_price']) ? '':' and ';
                $where1 .= $and.'sum(arrival_price) <= '.$param['max_price'];
            }
            
            $whereStr = '';
            $agent = $this->user_agent;
            $sql = UserModel::where([['tree','like', $agent['tree'].'%']])->field('id')->buildSql();
            $whereStr = 'a.uid in ('.$sql .')';
        
            $model = $model->alias("a")
                ->join("dq_user b","a.uid=b.id")
                ->where($where)
                ->where($whereStr)
                ->field('b.id,b.username,b.lebal,sum(a.arrival_price) as total_arrival_price')
                ->order("total_arrival_price desc")
                ->group('a.uid')
                ->having($where1);

            if(isset($param['exl'])){
                $list = $model->select();
                $data = [];
                foreach ($list as $v){
                    $v['lebal_name'] = $v['lebal'] == 1 ? '普通用户':'测试用户';
                    $data[] = [$v['username'], $v['lebal_name'], $v['total_arrival_price']];
                }
                $header = [
                    lang('用户名'),lang('用户标签'),lang('提现总金额')
                ];
                Respons::run(1,'','','','',new UserWithdrawalModel(),lang('提现排行榜数据导出'));
                Excel($header,$data,lang('提现排行榜数据').date('Ymd',time()));
            }else{
                $rsps['data'] = $model->paginate($limit,false,['query' => $param]);
            }

            $rsps['code']=1;
            return Respons::run($rsps);
        }
        return View::fetch('user_withdrawal/leaderboard');
    }
}