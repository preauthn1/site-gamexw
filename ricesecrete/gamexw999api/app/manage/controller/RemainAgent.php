<?php
namespace app\manage\controller;
use app\api\redis\ApiRedis;
use app\common\lib\ReportExport;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\lib\Uploads;
use app\common\model\UserModel;
use app\common\lib\Respons;
use app\common\model\UserOfflineModel;
use app\common\redis\CommomRedis;
use app\common\Utils;
use think\Request;
use think\facade\Db;

class RemainAgent extends Base
{	
    protected $middleware = ['ManageCheckLogin',"Language"];
    public function index(Request $request, $updateData = false){
        $info = $request->param();
        $page = $info['page'] ?? 1;
        $limit = $info['limit'] ?? 10;
        $condition = [];
        if( isset($info['username']) && $info['username'] !== '' ){
            if(is_numeric($info['username'])){
                $condition[]=["a.id|a.rec_code|a.phone", "=", $info['username']];
            }else{
                $condition[]=["a.rec_code|a.username|a.mail|a.phone", "=", $info['username']];
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
        $userList = UserModel::alias('a')
            ->join('user_agent b', 'b.uid=a.id')
            ->where($condition)->field('a.id,a.tree,a.mail,a.phone,b.uid')
            ->page($page, $limit)
            ->order('a.id desc')->paginate($limit,false,['query' => $info]);
        foreach ($userList as &$userDetail){
            //先生成对应数据
            if( $updateData ){
                $this->createData($start_time, $end_time, $userDetail);
            }else{
                //获取对应数据
                $_data = self::getDayStatistics($start_time, $end_time, $userDetail);
                foreach ($_data as $k => $v){
                    $userDetail[$k] = $v;
                }
            }
        }
        if( $updateData ){
            return true;
        }
        $userList = $userList->toArray();
        if( $userList&&$userList['data'] ){
            $total = [];
            foreach($userList['data'] as $v){
                foreach ($v AS $key => $val) {
                    if( strpos($key, 'remain_rate') !== false ){
                        $val = str_replace('%', '', $val);
                    }else if ( in_array($key, ['uid', 'tree', 'mail', 'phone']) ) {
                        $total[$key] = $val;
                        continue;
                    }

                    if( isset($total[$key]) ){
                        $total[$key] = round($total[$key] + $val, 2);
                    }else{
                        $total[$key] = $val;
                    }
                }
            }
            $day = [1,2,3,4,5,6,7,15,30,60];
            foreach ($day as $d) {
                $temp_remain_rate = round($total["remain_rate{$d}"]/count($userList['data']),2);
                $total["remain_rate{$d}"] = "{$temp_remain_rate}%";
                $total["remain_rate{$d}"] = str_replace('%', '', $total["remain_rate{$d}"]);
                $total["remain_rate{$d}"] = round($total["remain_rate{$d}"]/count($userList['data']),2)."%";
            }
            $total['uid'] = "合计";
            $userList['data'][] = $total;
        }
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

        $rlt = ReportExport::export(new rptParams('代理留存率报表',
            [
                new rptCol(rptCol::BuildRowData,'','app\manage\controller\RemainAgent::getRowData',['start_time' => $startTime, 'end_time' => $endTime]),
                'uid:代理id',
                'mail:代理邮箱',
                'phone:代理号码',
                'recharge_user:首充人数',
                'recharge_amount:首充金额',
                'remain_amount1:1日充值金额',
                'remain_people1:1日充值人数',
                'remain_rate1:1日留存率',
                'remain_amount2:2日充值金额',
                'remain_people2:2日充值人数',
                'remain_rate2:2日留存率',
                'remain_amount3:3日充值金额',
                'remain_people3:3日充值人数',
                'remain_rate3:3日留存率',
                'remain_amount4:4日充值金额',
                'remain_people4:4日充值人数',
                'remain_rate4:4日留存率',
                'remain_amount5:5日充值金额',
                'remain_people5:5日充值人数',
                'remain_rate5:5日留存率',
                'remain_amount6:6日充值金额',
                'remain_people6:6日充值人数',
                'remain_rate6:6日留存率',
                'remain_amount7:7日充值金额',
                'remain_people7:7日充值人数',
                'remain_rate7:7日留存率',
                'remain_amount15:15日充值金额',
                'remain_people15:15日充值人数',
                'remain_rate15:15日留存率',
                'remain_amount30:30日充值金额',
                'remain_people30:30日充值人数',
                'remain_rate30:30日留存率',
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
        $_data = self::getDayStatistics($transparams['start_time'], $transparams['end_time'], $userDetail);
        return array_merge($datarow, $_data);
    }

    public static function exportListList($params){
        return UserModel::alias('a')
            ->join('user_agent b', 'b.uid=a.id')
            ->where($params['where'])->field('a.id,a.tree,a.mail,a.phone,b.uid');
    }

    private static function getDayStatistics($startTime, $endTime, $userDetail){
        $arr = [];
        $default = 0;
        $day = [1,2,3,4,5,6,7,15,30,60];
        $temp_count = [
            'recharge_user' => 0,
            'recharge_amount' => 0,
        ];
        for($i = $startTime; $i <= $endTime; $i+=86400){
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
            $date_info = Db::name('remain_report_agent')->alias('a')
                ->join('user b', 'b.id=a.user_id')
                ->where(['remain_stat' => $remain_stat_date, 'user_id' => $userDetail->id])
                ->field('a.*,b.id as uid,b.phone,b.mail')
                ->find();
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
        foreach ($day as $d) {
            $temp_remain_rate = round($temp_count["remain_rate{$d}"]/count($arr),2);
            $temp_count["remain_rate{$d}"] = "{$temp_remain_rate}%";
            $temp_count["remain_amount{$d}"] = floatval($temp_count["remain_amount{$d}"]);
            $temp_count["remain_people{$d}"] = intval($temp_count["remain_people{$d}"]);
        }
        return $temp_count;
    }

    //更新缓存
    public function updateData(Request $request){
        $this->index($request, true);
        return $this->success([
            'data' => [],
        ]);
    }

    //生成报表数据
    public function createData($startTime, $endTime, $userDetail){
        $day = [1,2,3,4,5,7,15,30,60];
        $apiRedis = new ApiRedis();
        $time = time();
        $tomorrowTime = strtotime('tomorrow') - 1;
        for($i = $startTime; $i <= $endTime; $i+=86400){
            $remain_stat_date = date("Y-m-d", $i);
            $redisKey = 'agent_remain_redis_key_'.$remain_stat_date.'_'.$userDetail->id;
            if( $apiRedis->has($redisKey) ){
                continue;
            }
            foreach ($day as $d) {
                $this->remainStatRecharge($remain_stat_date, $d, $userDetail);
            }
            $apiRedis->set($redisKey, 1, $tomorrowTime - $time);
        }
        return $this->success([
            'data' => [],
        ]);
    }

    protected function remainStatRecharge($date, $daynum, $userDetail){
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
        $sql = UserModel::where([['tree','like', $userDetail['tree'].'%']])->field('id')->buildSql();
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
        $this->saveAgentRemain($userDetail, $date, $daynum, $remain_rate, $remain_uids, $remain_amount, $recharge_amount, $recharge_user, 2, $remain_people);

    }

    private function saveAgentRemain($userDetail, $date, $daynum, $remain_rate, $remain_uids, $remain_amount, $recharge_amount, $recharge_user, $type, $remain_people) {
        $data = [];
        $data['type'] = $type;
        $data['remain_stat'] = $date;
        $data['remain_rate'.$daynum] = $remain_rate;
        $data['remain_people'.$daynum] = $remain_people;
        $data['remain_uids'.$daynum] = $remain_uids;
        $data['remain_amount'.$daynum] = $remain_amount;
        $where = ['remain_stat' => $date, 'type' => $type, 'user_id' => $userDetail->id];
        $report = Db::name('remain_report_agent')->where($where)->find();
        if(empty($report)){
            $data['recharge_amount'] = $recharge_amount;
            $data['recharge_user'] = $recharge_user;
            $data['user_id'] = $userDetail->id;
            Db::name('remain_report_agent')->insert($data);
        }else{
            $ret = Db::name('remain_report_agent')->where('id', $report['id'])->update($data);
        }
    }
    
}
