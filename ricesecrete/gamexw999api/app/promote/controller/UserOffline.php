<?php
namespace app\promote\controller;
use app\common\model\BankSkModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserModel;
use app\common\lib\Respons;
use app\common\lib\Util;
use app\common\Utils;
use app\manage\factory\UserofflineFactory;
use think\facade\Config;
use think\facade\View;
use think\facade\Request;
use app\common\model\PaySetModel;
class UserOffline extends Base
{
    public function list(){
        $user = $this->user;
        return (new UserofflineFactory($this->user_id))->list([
            ['a.uid', "IN", function($query) use($user){
                $query->name('user')->where([['tree','like', $user['tree'].'%']])->field('id');
            }]
        ]);
    }

    public function get_count(\think\Request $request){
        $user = $this->user;
        $where = [];
        $where[] = ['a.uid', "IN", function($query) use($user){
            $query->name('user')->where([['tree','like', $user['tree'].'%']])->field('id');
        }];
        $param=Request::param();
        if (is_null($where)) {
            $where = [];
        }
        $whereStr = '1=1';
        $time_type = 'a.add_time';
        if (isset($param['set_content']) && $param['set_content']!='') {
            $where[] = ["a.set_content","like","%".$param['set_content']."%"];
        }
        if (isset($param['username']) && !empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field,'=',$param['username']]])->value("id");
            $where[] = ["a.uid","=", $uids];
        }
        if (isset($param['order_id']) && $param['order_id']!='') {
            $where[] = ["a.id", "=", $param['order_id']];
        }
        if (isset($param['order_sn']) && $param['order_sn']!='') {
            $where[] = ["a.order_sn", "=", $param['order_sn']];
        }
        if (isset($param['three_order_sn']) && $param['three_order_sn']!='') {
            $where[] = ["a.othr_number", "=", $param['three_order_sn']];
        }
        if (isset($param['min_price']) && $param['min_price']!='') {
            $where[] = ['apply_price',">=",$param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price']!='') {
            $where[] = ['apply_price',"<=",$param['max_price']];
        }

        //增加代理线搜索条件
        if(!empty($param['agent_name'])){
            $agent_id = UserModel::where("username|mail|phone", $param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree','like', '%/'.$agent_id.'/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= ' and a.uid in ('.$sql .')';
        }

        if(!empty($param['level_id'])){
            $sql = UserModel::where("level_id",$param['level_id'])->field('id')->buildSql();
            $whereStr .= ' and a.uid in ('.$sql .')';
        }

        if (isset($param['status']) && $param['status']!='') {
            $where[] = ["a.status","=", $param['status']];
        }

        if (isset($param['bankcrad']) && $param['bankcrad']!='') {
            $where[] = ["a.bankcrad","like", "%".$param['bankcrad']."%"];
        }

        if (isset($param['channel']) && $param['channel']!='') {
            $where[] = ["a.channel","=", $param['channel']];
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }
        if(isset($param['pay_type']) && $param['pay_type'] > 0){
            $where[] = ['a.pay_id','=',$param['pay_type']];
        }
        if(isset($param['recharge_type']) && $param['recharge_type'] > 0){
            if($param['recharge_type'] == 1){
                $where[] = ['a.first','=',1];
            }else{
                $where[] = ['a.first','<>',1];
            }
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
            $where[]=[$time_type,">=", Utils::getQueryDateTime($date[0])];
            $where[]=[$time_type,"<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['lebals']) && $param['lebals'] != '') {
            if ($param['lebals'] < 0) {
                $where[]=["a.lebal", "=", abs($param['lebals'])];
            } else {
                $tag_id = $param['lebals'];
                $where[]=["a.uid", "IN", function($query) use($tag_id) {
                    return $query->name('user_tag')->where('tag_id', $tag_id)->field('uid');
                }];
            }
        }

        // 初始化各个渠道的状态值
        $channels = [
            'bank' => ['title' => '银行卡', 'stay' => 0, 'yes' => 0, 'no' => 0, 'count' => 0, 'people' => 0, 'channel' => [0,1]],
            'usdt' => ['title' => 'USDT', 'stay' => 0, 'yes' => 0, 'no' => 0, 'count' => 0, 'people' => 0, 'channel' => [2,3]],
            'wallet' => ['title' => '电子钱包', 'stay' => 0, 'yes' => 0, 'no' => 0, 'count' => 0, 'people' => 0, 'channel' => 4],
            'pix' => ['title' => 'PIX', 'stay' => 0, 'yes' => 0, 'no' => 0, 'count' => 0, 'people' => 0, 'channel' => 6],
            'agent' => ['title' => '银商', 'stay' => 0, 'yes' => 0, 'no' => 0, 'count' => 0, 'people' => 0, 'channel' => 20]
        ];

        // 遍历各个渠道并查询相应的值
        foreach ($channels as $channelName => $row) {
            $stay = $this->getSum(1, $row['channel'], $where, $whereStr, 'apply_price');
            $no = $this->getSum(2, $row['channel'], $where, $whereStr, 'apply_price');
            $yes = $this->getSum([3, 4], $row['channel'], $where, $whereStr);
            $count = $this->getCount($row['channel'], $where, $whereStr, false);
            $people = $this->getCount($row['channel'], $where, $whereStr);
            // 将查询结果存入各个渠道的状态值中
            $channels[$channelName]['stay'] = $stay ?? 0;
            $channels[$channelName]['no'] = $no ?? 0;
            $channels[$channelName]['yes'] = $yes ?? 0;
            $channels[$channelName]['count'] = $count ?? 0;
            $channels[$channelName]['people'] = $people ?? 0;
            unset($channels[$channelName]['channel']);
            if (array_sum([$stay, $no, $yes]) == 0) {
                unset($channels[$channelName]);
            }
        }

        $data['data'] = $channels;
        $data['code'] = 1;
        return Respons::run($data);
    }
    /**
     * 统计金额
     * @return float
     */
    private function getSum($status, $channel, $where, $whereStr, $field = "arrival_price") {
        return UserOfflineModel::alias('a')
            ->whereIn('status', $status)
            ->whereIn('channel', $channel)
            ->where($where)
            ->where($whereStr)
            ->sum($field);
    }

    /**
     * 统计数量
     * @return float
     */
    private function getCount($channel, $where, $whereStr, $distinct = true) {
        return UserOfflineModel::alias('a')
            ->whereIn('channel', $channel)
            ->where($where)
            ->where($whereStr)
            ->count($distinct ? "distinct uid" : "uid");
    }

    public function edit_detail(){
        $param = $this->request->param();
        if (!isset($param['id'])) {
            throw new \think\Exception(lang("用户id不能为空"), 0);
        }
        $res = UserOfflineModel::alias("a")
            ->join("dq_user b","a.uid=b.id")
            ->leftjoin("dq_bank_sk e","a.bank_sk_id=e.id")
            ->where("a.id",$param['id'])
            ->order("a.id desc")
            ->field("a.*,b.username,b.mail,b.phone,b.lebal,e.bank_number as s_bank_number,e.bank_open as s_bank_open,e.bank_khm as s_bank_khm,e.bank_name as s_bank_name,e.bank_qrcode,e.financial as s_financial")
            ->find();
        $res['add_time'] = date("Y-m-d H:i:s",$res['add_time']);
        $res['update_time'] = date("Y-m-d H:i:s",$res['update_time']);
        $res['arrival_price'] = $res['apply_price'] * $res['exchange_rate'];
        $res['cutrr_status'] = $res['status'];
        if (!is_null($res['mail'])) {
            $res['username'] = $res['mail'];
        } elseif (!is_null($res['phone'])) {
            $res['username'] = $res['phone'];
        }
        return Respons::run(1, $res);

    }

    //充值排行榜
    public function leaderboard(){
        $param=Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where[] = ['a.arrival_price','>',0];
        $where[] = ['a.status','>',2];
        $time_type = 'a.add_time';
        $model = new UserOfflineModel();
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

        $model = $model->alias("a")
            ->join("dq_user b","a.uid = b.id")
            ->where($where)
            ->field('b.id,b.username,b.lebal,sum(a.arrival_price) as sum_arrival_price')
            ->order("sum_arrival_price desc")
            ->group('uid')
            ->having($where1);

        if(isset($param['exl'])){
            $list = $model->select();
            $data = [];
            foreach ($list as $v){
                $v['lebal_name'] = $v['lebal'] == 1 ? '普通用户':'测试用户';
                $data[] = [$v['username'], $v['lebal_name'], $v['sum_arrival_price']];
            }
            $header = [
                lang('用户名'),lang('用户标签'),lang('充值总金额')
            ];
            Respons::run(1,'','','','',new UserOfflineModel(),lang('充值排行榜数据导出'));
            Excel($header,$data,lang('充值排行榜数据').date('Ymd',time()));
        }else{
            $rsps['data'] = $model->paginate($limit,false,['query' => $param]);
        }

        $rsps['code']=1;
        return Respons::run($rsps);

    }

    public function pay_channel() {
        $channel_arr = Config::get('custom.channel');
        $online = PaySetModel::where(["isdel" => 0])
            ->filter(function($data) use($channel_arr) {
                $data['p_type'] = 'online';
                $data['t_name'] = $data['pay_type'] == 0 ? lang($channel_arr[1]) : lang($channel_arr[2]);
            })
            ->order("id desc")
            ->select()->toArray();
        //将线上渠道修改成线下渠道
        $offline = BankSkModel::where(['bank_status' => 1])
            ->filter(function($data) use($channel_arr) {
                $data['p_type'] = 'offline';
                switch($data['type']){
                    case 0 :
                        $data['t_name'] = lang($channel_arr[0]);
                        break;
                    case 1 :
                        $data['t_name'] = lang($channel_arr[3]);
                        break;
                    case 2 :
                        $data['t_name'] = lang($channel_arr[4]);
                        break;
                    case 3 :
                        $data['t_name'] = lang($channel_arr[6]);
                        break;
                }
            })
            ->order("id desc")
            ->select()->toArray();
        $channel = array_merge($offline ?? [], $online ?? []);
        return Respons::run(1,$channel);
    }
}