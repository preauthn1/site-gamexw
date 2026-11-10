<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\lib\Util;
use app\common\model\PaySetModel;
use app\common\model\UserModel;
use app\common\model\UserWithdrawalModel;
use app\common\service\MemberService;
use app\common\Utils;
use think\facade\Db;
use think\facade\Request;

class UserWithdrawalFactory
{
    private $uid;//用户id
    //验证
    public function __construct($uid=0){
        $this->uid=$uid;
    }

    public function leaderboard()
    {
        $param=Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where[] = ['a.arrival_price','>',0];
        $where[] = ['a.status','>',2];
        $time_type = 'a.add_time';
        $model = new UserWithdrawalModel();
        if (isset($param['username']) && $param['username']!='') {
            $where[] = ["b.username|b.mail|b.phone","=", $param['username']];
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

        $where1 = '';
        if (isset($param['min_price']) && !empty($param['min_price'])) {
            $where1 .= 'sum(arrival_price) >= '.$param['min_price'];
        }

        if (isset($param['max_price']) && !empty($param['max_price'])) {
            $and = empty($param['min_price']) ? '':' and ';
            $where1 .= $and.'sum(arrival_price) <= '.$param['max_price'];
        }

        $model = $model->alias("a")
            ->join("dq_user b","a.uid=b.id")
            ->where($where)
            ->field('b.id,b.username,b.mail,b.phone,b.add_time,b.login_time,b.lebal,sum(a.arrival_price) as total_arrival_price')
            ->order("total_arrival_price desc")
            ->group('a.uid')
            ->having($where1);

        if(isset($param['exl'])){
            $list = $model->select();
            $data = [];
            foreach ($list as $v){
                $v = Utils::hideUserInfo($v);
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
            foreach ($rsps['data'] as &$v){
                $v = Utils::hideUserInfo($v);
            }
        }

        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function get_user_data($where = null)
    {
        $channel = Util::getWOpenChannel();
        $param=Request::param();
        $limit = $param['limit'] ?? 10;
        if (is_null($where)) {
            $where = [];
        }
        $whereStr = '';
        if (isset($param['username']) && !empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field,'=',$param['username']]])->value("id");
            $where[] = ["a.uid","=", $uids];
        }
        if (isset($param['order_sn']) && $param['order_sn']!='') {
            $where[]=["a.order_sn", "=", $param['order_sn']];
        }
        if (isset($param['order_id']) && $param['order_id']!='') {
            $where[]=["a.id", "=", $param['order_id']];
        }
        if (isset($param['status']) && $param['status']!='') {
            $where[]=["a.status","=", $param['status']];
        }
        if (isset($param['min_price']) && $param['min_price']!='') {
            $where[]=['w_price',">=",$param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price']!='') {
            $where[]=['w_price',"<=",$param['max_price']];
        }
        if (isset($param['remarks']) && $param['remarks'] !== '') {
            $where[]=['w_remarks',"like", '%'.$param['remarks'].'%'];
        }
        //增加代理线搜索条件
        if(!empty($param['agent_name'])){
            $agent_id = UserModel::where("username|mail|phone",$param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree','like', '%/'.$agent_id.'/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= 'a.uid in ('.$sql .')';
        }

        if (isset($param['lebals']) && $param['lebals'] != '') {
            if ($param['lebals'] < 0) {
                $where[]=["a.lebal", "=", abs($param['lebals'])];
                $where[]=["a.uid", "NOT IN", function($query) {
                    return $query->name('user_tag')->group('uid')->field('uid');
                }];
            } else {
                $tag_id = $param['lebals'];
                $where[]=["a.uid", "IN", function($query) use($tag_id) {
                    return $query->name('user_tag')->where('tag_id', $tag_id)->field('uid');
                }];
            }
        } else {
            $where[] = ["a.lebal", "=", 1];
        }

        if (isset($param['channel']) && $param['channel']!='') {
            $where[]=["a.channel","=", $param['channel']];
        }
        if(isset($param['uid']) && $param['uid'] > 0){
            $where[] = ['a.uid','=',$param['uid']];
        }

        if(isset($param['pay_type']) && $param['pay_type'] !== ''){
            $where[] = ['a.method_id','=',$param['pay_type']];
        }
        $time_type = 'a.add_time';
        if(isset($param['time_type']) && $param['time_type'] != ''){
            if($param['time_type'] == 2){
                $time_type = 'a.update_time';
            }elseif ($param['time_type'] == 3){
                $time_type = 'a.check_time';
            }elseif ($param['time_type'] == 4){
                $time_type = 'a.pay_time';
            }
        }

        if (isset($param['order']) && $param['order'] != '') {
            $order = 'a.'.$param['field'].' '.$param['order'];
        } else {
            $order = 'a.id desc';
        }

        if (isset($param['date']) && $param['date']!='') {
            $date=explode(' - ',$param['date']);
            $where[]=[$time_type,">=", Utils::getQueryDateTime($date[0])];
            $where[]=[$time_type,"<=", Utils::getQueryDateTime($date[1], true)];
        }

        $field = 'a.*,b.username,b.mail,b.phone,b.remark,b.rec_id,b.tree,b.login_ip_count, c.bank_hm';
        $rsps['data']= UserWithdrawalModel::alias("a")
            ->join('user b', 'b.id=a.uid')
            ->join("dq_user_bank c","a.bank_id=c.id", 'left')
            ->where($where)->where($whereStr)
            ->order($order)
            ->field($field)
            ->paginate($limit,false,['query' => $param]);

        $pay_name = PaySetModel::column('pay_name', 'id');
        $pay_name_remarks = PaySetModel::column('remarks', 'id');
        foreach($rsps['data'] as $key => &$val){
            $val['channel_name'] = isset($channel[$val['channel']])?lang($channel[$val['channel']]):"";
            $val['add_time'] = date("Y-m-d H:i:s", $val['add_time']);
            $val['update_time'] = $val['update_time']>0?date("Y-m-d H:i:s",$val['update_time']):'';
            $val['check_time'] = $val['check_time']>0?date("Y-m-d H:i:s",$val['check_time']):'';
            $val['pay_time'] = $val['pay_time']>0?date("Y-m-d H:i:s",$val['pay_time']):'';
            if($val['method_id'] > 0){
                $val['pay_name'] = $pay_name[$val['method_id']] ?? $val['method_id'];
                $val['pay_name_remark'] = $pay_name_remarks[$val['method_id']] ?? '';
                if ($val['pay_name_remark']) {
                    $val['pay_name'] .= "[{$val['pay_name_remark']}]";
                }
            }
            if((!env('digital_currency_open', 0) == 1 && $val['pay_type'] == 'AlphapoPay')){
                unset($val['bank_hm']);
            }
            $val['rec_user'] = MemberService::getUsernameByUid($val['rec_id']);
            $val['rec_top_user'] = MemberService::getTopAgentByTree($val['tree']);
            $val['tags'] = MemberService::getTagByUid($val['uid']);
            $val = Utils::hideUserInfo($val);
        }
        //合计
        $userWithdrawalList=$rsps['data'];
        $userWithdrawalList = $userWithdrawalList->toArray();
        if( $userWithdrawalList&&$userWithdrawalList['data'] ){
            $total = [];
            foreach($userWithdrawalList['data'] as $v){
                foreach ($v AS $key => $val) {
                    if(!in_array($key,['w_price','arrival_price','arrival_prices','w_service'])){
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
            $total['id'] = "合计";
            $total['uid'] = "";
            $total['order_sn']="";
            $total['pay_name']="";
            $total['phone']="";
            $total['mail']="";
            $total['lebal']="";
            $total['content']="";
            $total['register_time']="";
            $total['reg_ip']="";
            $total['rec_top_user']="";
            $total['arrival_currency']="";
            $total['rec_user']="";
            $total['exchange_rate']=0; 
            $total['pay_currency']="";
            $total['status']="";
            $total['add_time']="";
            $userWithdrawalList['data'][] = $total;
        }
        $rsps['data']=$userWithdrawalList;
        $pays = PaySetModel::where(["isdel" => 0])->order("id desc")->select();
        $channel_list = [];
        foreach ($pays as $item) {
            $channel_list[] = ['label' => "{$item['pay_sys_sign']}-{$item['pay_name']}-{$item['remarks']}", 'value' => $item['id']];
        }
        $channel_list[] = ['label' => lang('线下审核'), 'value' => 0];
        $rsps['channel'] = $channel_list;
        $tags = [
            ['label' => '一般用户', 'value' => -1],
            ['label' => '测试用户', 'value' => -2],
//            ['label' => '三方登录用户', 'value' => -3],
        ];
        $rsps['tags'] = array_merge($tags, Utils::getOptions('tag'));
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    /**
     * 导出数据
     * @param null $where
     * @param null $whereStr
     * @return \think\response\Json
     */
    public function exportList($where = null, $whereStr = null)
    {
        $params = Request::param();
        $params['where'] = $where;
        $params['whereStr'] = $whereStr;
        $params['positionField'] = 'a.id';
        $params['positionOrder'] = 'desc';
        $params['muid']=$this->uid;
        $stateArr = [1=>lang('待审核'),2=>lang('未通过'),3=>lang('已通过'),4=>lang('手动入款')];
        $rlt = ReportExport::export(new rptParams('会员提现数据',
            [
                new rptCol('uid', '用户UID'),
                new rptCol('add_time','申请时间',rptCol::transType_datetime,['timezone'=>null,'format'=>'Y-m-d H:i:s']),
                new rptCol('lebal','用户标签',"app\common\service\ReportDownloadService::getLebal"),
                'w_price:提现金额',
                'arrival_price:到账金额',
                'w_service:手续费',
                'w_remarks:备注',
                new rptCol('bank_name','提现信息',"app\common\service\ReportDownloadService::getWithdrawalInfo"),
                new rptCol('status','状态',rptCol::transType_kvtrans,$stateArr),
                new rptCol('channel','数据来源','app\common\service\ReportDownloadService::getChannelName'),
                'operator:操作人',
                'pay_type:代付渠道',
                'order_sn:订单号',
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['status', 'uid'],//导出表名称使用的字段信息
            'a.id', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        return UserWithdrawalModel::alias("a")
            ->leftjoin("dq_user_bank c","a.bank_id=c.id")
            ->where($param['where'])->where($param['whereStr'])
            ->field("a.*,c.bank_number,c.bank_open,c.bank_hm,c.bank_name," .'(select username from dq_user where id=a.uid limit 1) as username');
    }
}
