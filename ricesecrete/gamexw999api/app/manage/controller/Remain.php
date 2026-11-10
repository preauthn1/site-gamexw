<?php
namespace app\manage\controller;
use app\common\lib\Uploads;
use app\common\model\UserModel;
use app\common\lib\Respons;
use app\common\redis\CommomRedis;
use app\common\Utils;
use think\facade\Request;
use think\facade\Db;

class Remain extends Base
{	
    protected $middleware = ['ManageCheckLogin',"Language"];

    public function index(){
        $date = $this->request->param('date/s');
        $export = $this->request->param('export/d', 0);
        $type = $this->request->param('type/d', 2);
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
            $date_info = Db::name('remain_report')->where(['remain_stat' => $remain_stat_date, 'type' => $type])->find();
            if(!empty($date_info)){
                $row = $date_info;
            }
            $row["recharge_amount"] = floatval($row["recharge_amount"]);
            foreach ($day as $d) {
                $row["remain_rate{$d}"] = floatval($row["remain_rate{$d}"]);
                if ($row["remain_rate{$d}"] > 0) {
                    $row["remain_rate{$d}"] .= "%";
                    $row["remain_amount{$d}"] = floatval($row["remain_amount{$d}"]);
                } else {
                    $row["remain_rate{$d}"] = "0%";
                    $row["remain_amount{$d}"] = "0";
                }
                $temp_remain_rate = str_replace('%','',$row["remain_rate{$d}"]);
                if(isset($temp_count["remain_rate{$d}"])){
                    $temp_count["remain_rate{$d}"] += floatval($temp_remain_rate);
                }else{
                    $temp_count["remain_rate{$d}"] = floatval($temp_remain_rate);
                }
                $temp_remain_amount = floatval($row["remain_amount{$d}"]);
                if(isset($temp_count["remain_amount{$d}"])){
                    $temp_count["remain_amount{$d}"] += $temp_remain_amount;
                }else{
                    $temp_count["remain_amount{$d}"] = $temp_remain_amount;
                }
                $temp_remain_people = intval($row["remain_people{$d}"]);
                if(isset($temp_count["remain_people{$d}"])){
                    $temp_count["remain_people{$d}"] += intval($row["remain_people{$d}"]);
                }else{
                    $temp_count["remain_people{$d}"] = $temp_remain_people;
                }
            }
            $temp_count["recharge_amount"] += $row["recharge_amount"];
            $temp_count["recharge_user"] += floatval($row["recharge_user"]);
            $arr[] = $row;
        }
        $temp_count["remain_stat"] = "合计";
        foreach ($day as $d) {
            $temp_remain_rate = round($temp_count["remain_rate{$d}"]/count($arr),2);
            $temp_count["remain_rate{$d}"] = "{$temp_remain_rate}%";
            $temp_count["remain_amount{$d}"] = floatval($temp_count["remain_amount{$d}"]);
            $temp_count["remain_people{$d}"] = intval($temp_count["remain_people{$d}"]);
        }
        $arr[] = $temp_count;
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
                $header["remain_amount{$d}"] = "{$d}充值金额";
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
            Utils::addOptLogByHandle('留存率报表导出');
            $result['data'] = $s3_url ?: toMedia($file_name, '/');
            $result['lists'] = $lists;
            $result['code']=1;
            return Respons::run($result);
        }
        return $this->success([
            'data' => $arr
        ]);
    }
    
    //用户
    public function user(){
    	$param = Request::param();
        $id = $param['id'];
        $day = $param['day'];
        $date_info = Db::name('remain_report')->where(['id' => $id])->find();
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

    //代理下级用户
    public function agentuser(){
        $param = Request::param();
        $id = $param['id'] ??0;
        $page = $param['page'] ??1;
        $limit = $param['limit'] ??10;
        $where=[];
        $where[] = ['a.tree','like', '%/'.$id.'/%'];
        $where[] = ['a.id','<>', $id];

        $count = UserModel::where([['tree','like', '%/'.$id.'/%'],['id','<>', $id]])->count();
        $list = Db::name("user")
            ->alias("a")
            ->leftJoin("dq_user_level b","a.level_id=b.id")
            ->where($where)
            ->order("a.id desc")
            ->field("a.*,b.level_name")
            ->page($page, $limit)
            ->select();

        $data = [];
        foreach ($list as $key => $value) {
            $value['add_time'] = date("Y-m-d H:i:s", $value['add_time']);
            $value['login_time'] = date("Y-m-d H:i:s", $value['login_time']);
            $data['data'][] = $value;
        }
        $data['count'] = $count;
        return $this->success($data);
    }
}
