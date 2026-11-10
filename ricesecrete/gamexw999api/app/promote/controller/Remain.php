<?php
namespace app\promote\controller;
use app\common\lib\Uploads;
use app\common\model\UserModel;
use app\common\lib\Respons;
use app\common\model\UserOfflineModel;
use app\common\Utils;
use think\facade\Request;
use think\facade\Db;

class Remain extends Base
{	
    public function index(){
        $date = request()->param('date/s');
        $export = request()->param('export/d', 0);
        $type = request()->param('type/d', 2);
        $start_time = time();
        $end_time = time() - 7*86400;
        if (!empty($date)) {
            $date_arr = explode(' - ', $date);
            $end_time = Utils::getQueryDateTime($date_arr[0]);
            $start_time = Utils::getQueryDateTime($date_arr[1], true);
        }

        $arr = [];
        $default = 0;
        $day = [1,2,3,4,5,6,7,15,30,60];
        $temp_count = [
            'recharge_user' => 0,
            'recharge_amount' => 0,
        ];
        for($i = $start_time; $i >= $end_time; $i-=86400){
            $remain_stat_date = date("Y-m-d", $i);
            $row = [
                'id' => 0,
                'remain_stat' => $remain_stat_date,
                'recharge_amount' => 0,
                'recharge_user' => 0,
            ];
            foreach ($day as $d) {
                $row["remain_rate{$d}"] = $default;
                $row["remain_amount{$d}"] = $default;
                $row["remain_people{$d}"] = $default;
            }
            $date_info = Db::name('remain_report_agent')->where(['remain_stat' => $remain_stat_date, 'type' => $type, 'user_id' => $this->user_id])->find();
            if(!empty($date_info)){
                $row = $date_info;
            }
            $row["recharge_amount"] = floatval($row["recharge_amount"]);
            foreach ($day as $d) {
                $row["remain_rate{$d}"] = floatval($row["remain_rate{$d}"]);
                if ($row["remain_rate{$d}"] > 0) {
                    $row["remain_rate{$d}"] .= "%";
                    $row["remain_people{$d}"] = $row["remain_amount{$d}"];
                    $row["remain_amount{$d}"] = floatval($row["remain_amount{$d}"]);
                } else {
                    $row["remain_rate{$d}"] = "0%";
                    $row["remain_amount{$d}"] = "0";
                    $row["remain_people{$d}"] = 0;
                }
                $temp_remain_rate = str_replace('%','',$row["remain_rate{$d}"]);
                if(isset($temp_count["remain_rate{$d}"])){
                    $temp_count["remain_rate{$d}"] += floatval($temp_remain_rate);
                }else{
                    $temp_count["remain_rate{$d}"] = floatval($temp_remain_rate);
                }
                $temp_remain_amount = floatval($row["remain_amount{$d}"]);
                if(isset($temp_count["remain_amount{$d}"])){
                    $temp_count["remain_amount{$d}"] += floatval($temp_remain_amount);
                }else{
                    $temp_count["remain_amount{$d}"] = floatval($temp_remain_amount);
                }
                $temp_remain_people = intval($row["remain_people{$d}"]);
                if(isset($temp_count["remain_people{$d}"])){
                    $temp_count["remain_people{$d}"] += intval($row["remain_people{$d}"]);
                }else{
                    $temp_count["remain_people{$d}"] = $temp_remain_people;
                }
            }
            $temp_count["recharge_amount"] += $row["recharge_amount"];
            $temp_count["recharge_user"] += intval($row["recharge_user"]);
            $arr[] = $row;
        }
        if ($export == 1) {
            $header = [
                'remain_stat' => '日期',
                'recharge_user' => '首充人数',
                'recharge_amount' => '首充金额',
            ];
            foreach ($day as $d) {
//                $header["remain_rate{$d}_0"] = "{$d}日登陆留存率";
//                $header["remain_rate{$d}_1"] = "{$d}日游戏留存率";
                $header["remain_rate{$d}"] = "{$d}日留存率";
                $header["remain_amount{$d}"] = "充值金额";
            }
            $lists = $arr;
//            foreach ($arr AS $a) {
//                $row = [
//                    'remain_stat' => $a['remain_stat'],
//                ];
//                foreach ($day as $d) {
//                    foreach ($a["remain_rate{$d}"] AS $k => $a2) {
//                        $row["remain_rate{$d}_{$k}"] = "{$a2}%";
//                    }
//                }
//                $lists[] = $row;
//            }
            $file_name = genCsv($header, $lists,lang('留存率报表').date('YmdHis',time()), false);
            $s3_url = Uploads::uploadS3(public_path() . $file_name);
//            Utils::addOptLogByHandle('留存率报表导出');
            $result['data'] = $s3_url ?: toMedia($file_name, '/');
            $result['lists'] = $lists;
            $result['code']=1;
            return Respons::run($result);
        }
        $temp_count["remain_stat"] = "合计";
        foreach ($day as $d) {
            $temp_remain_rate = round($temp_count["remain_rate{$d}"]/count($arr),2);
            $temp_count["remain_rate{$d}"] = "{$temp_remain_rate}%";
            $temp_count["remain_amount{$d}"] = floatval($temp_count["remain_amount{$d}"]);
            $temp_count["remain_people{$d}"] = intval($temp_count["remain_people{$d}"]);
        }
        $arr[] = $temp_count;
        return $this->success([
            'data' => $arr,
        ]);
    }

    //生成报表数据
    public function createData(){
        $start_time = time();
        $end_time = time() - 7*86400;
        if (!empty($date)) {
            $date_arr = explode(' - ', $date);
            $end_time = Utils::getQueryDateTime($date_arr[0]);
            $start_time = Utils::getQueryDateTime($date_arr[1], true);
        }
        if( $end_time > strtotime('today') ){
            return Respons::run(0, [], '结束不能选择今天过后得时间');
        }
        $day = [1,2,3,4,5,7,15,30,60];
        for($i = $start_time; $i >= $end_time; $i-=86400){
            $remain_stat_date = date("Y-m-d", $i);
            foreach ($day as $d) {
                $this->remainStatRecharge($remain_stat_date, $d);
            }
        }
        return $this->success([
            'data' => [],
        ]);
    }

    protected function remainStatRecharge($date, $daynum){
        // 统计当天开始 结束 时间戳
        $startTime = strtotime($date.' 00:00:00');
        $endTime = strtotime($date.' 23:59:59');
        // 获取 N 天后还充值的用户 sql
        $n_day_after_start_time = $startTime + ($daynum * 86400);
        $n_day_after_end_time = $endTime + ($daynum * 86400);
        if ($endTime > time()) {
            return;
        }
        //获取当前代理用户下级用户id
        $agent = $this->user_agent;
        $sql = UserModel::where([['tree','like', $agent['tree'].'%']])->field('id')->buildSql();
        $whereStr = 'uid in ('.$sql .')';


        // 当天首充用户数和金额
        $recharge_user = count(array_unique(UserOfflineModel::where("(status=3 or status=4) and update_time>=".$startTime." and update_time<=".$endTime." and first = 1 ")->where($whereStr)->distinct(true)->column("uid")));
        $recharge_amount = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$startTime." and update_time<=".$endTime." and first = 1")->where($whereStr)->sum("arrival_price");
        // 获取当天首充且 N 天后还有充值的用户(到账时间为准)
        $uids_arr = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$startTime." and update_time<=".$endTime." and first = 1 ")->where($whereStr)->column("uid");
        if(!empty($uids_arr)){
            $uids_sql = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$startTime." and update_time<=".$endTime." and first = 1")->where($whereStr)->field("uid")->buildSql();
            // 获取当天首次充值用户总数
            $uids_all = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$n_day_after_start_time." and update_time<=".$n_day_after_end_time." and uid in(".$uids_sql.")")->distinct(true)->column("uid");
            $uids_all = array_unique($uids_all);
            $remain_people = count($uids_all);
            //计算留存率 保留两位小数（四舍五入） 当天活跃用户数 / N 前注册且充值过的用户数
            $remain_rate = sprintf("%.4f", $remain_people / count($uids_arr)) * 100;
            $remain_uids = implode(",", $uids_all);
            $remain_amount = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$n_day_after_start_time." and update_time<=".$n_day_after_end_time." and uid in(".$uids_sql.")")->sum("arrival_price");
        } else {
            $remain_rate = 0;
            $remain_uids = "";
            $remain_amount = 0;
            $remain_people = 0;
        }
        $this->saveAgentRemain($date, $daynum, $remain_rate, $remain_uids, $remain_amount, $recharge_amount, $recharge_user, 2, $remain_people);

    }

    private function saveAgentRemain($date, $daynum, $remain_rate, $remain_uids, $remain_amount, $recharge_amount, $recharge_user, $type, $remain_people) {
        $data = [];
        $data['type'] = $type;
        $data['remain_stat'] = $date;
        $data['remain_rate'.$daynum] = $remain_rate;
        $data['remain_uids'.$daynum] = $remain_uids;
        $data['remain_amount'.$daynum] = $remain_amount;
        $data['remain_people'.$daynum] = $remain_people;
        $where = ['remain_stat' => $date, 'type' => $type, 'user_id' => $this->user_id];
        $report = Db::name('remain_report_agent')->where($where)->find();
        if(empty($report)){
            $data['recharge_amount'] = $recharge_amount;
            $data['recharge_user'] = $recharge_user;
            $data['user_id'] = $this->user_id;
            Db::name('remain_report_agent')->insert($data);
        }else{
            $ret = Db::name('remain_report_agent')->where('id', $report['id'])->update($data);
        }
    }
    
    //用户
    public function user(){
    	$param = Request::param();
        $id = $param['id'];
        $day = $param['day'];
        $date_info = Db::name('remain_report_agent')->where(['id' => $id])->find();
        $page = $param['page'];
        $limit = $param['limit'];
        if (empty($date_info) || !isset($date_info["remain_uids".$day])) {
            return $this->error();
        }
        $uids = $date_info["remain_uids".$day];
        $uid_arr = explode(",", $uids);
        $count = count($uid_arr);
        $num = $page * $limit;
        if($num > $count){
            $num = $count;
        }
        $uid = [];
        for($i=($page-1)*$limit;$i<$num;$i++){
            $uid[] = $uid_arr[$i];
        }

        $where=[];
        $where[] = ["a.id", "in", $uid];
        $UserModel =new UserModel();

        $result['count'] = $count;
        $result['data'] = $UserModel
                ->alias("a")
                ->leftJoin("dq_user_level b","a.level_id=b.id")
                ->where($where)
                ->order("a.id desc")
                ->field("a.*,b.level_name")
                ->select();

        foreach ($result['data'] as $key => &$value) {
            $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
            $value['login_time'] = date("Y-m-d H:i:s", $value['login_time']);
        }
        return $this->success($result);
    }
    
    
}
