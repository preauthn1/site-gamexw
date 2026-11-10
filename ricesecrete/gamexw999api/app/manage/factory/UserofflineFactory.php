<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\service\MemberService;
use app\common\Utils;
use think\facade\Request;
use app\common\lib\Respons;
use app\common\model\{UserModel, UserOfflineModel, PaySetModel, UserReportsModel};
use app\common\lib\Util;
use think\facade\Db;

class UserofflineFactory
{

    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->uid = $uid;
    }

    public function leaderboard()
    {
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where[] = ['a.arrival_price', '>', 0];
        $where[] = ['a.status', '>', 2];
        $time_type = 'a.add_time';
        $model = new UserOfflineModel();
        if (isset($param['username']) && $param['username'] != '') {
            $where[] = ["b.username|b.mail|b.phone", "=", $param['username']];
        }

        if (isset($param['time_type']) && $param['time_type'] != '') {
            if ($param['time_type'] == 1) {
                $time_type = 'a.add_time';
            } else {
                $time_type = 'a.update_time';
            }
        } else {
            $time_type = 'a.add_time';
        }
        if (isset($param['date']) && $param['date'] != '') {
            $date = explode(' - ', $param['date']);
            $where[] = [$time_type, ">=", Utils::getQueryDateTime($date[0])];
            $where[] = [$time_type, "<=", Utils::getQueryDateTime($date[1], true)];
        }

        $where1 = '';
        if (isset($param['min_price']) && !empty($param['min_price'])) {
            $where1 .= 'sum(arrival_price) >= ' . $param['min_price'];
        }

        if (isset($param['max_price']) && !empty($param['max_price'])) {
            $and = empty($param['min_price']) ? '' : ' and ';
            $where1 .= $and . 'sum(arrival_price) <= ' . $param['max_price'];
        }

        $model = $model->alias("a")
            ->join("dq_user b", "a.uid = b.id")
            ->where($where)
            ->field('b.id,b.username,b.mail,b.phone,b.add_time,b.login_time,b.lebal,sum(a.arrival_price) as sum_arrival_price')
            ->order("sum_arrival_price desc")
            ->group('uid')
            ->having($where1);

        if (isset($param['exl'])) {
            $list = $model->select();
            $data = [];
            foreach ($list as $v) {
                $v = Utils::hideUserInfo($v);
                $v['lebal_name'] = $v['lebal'] == 1 ? '普通用户' : '测试用户';
                $data[] = [$v['username'], $v['lebal_name'], $v['sum_arrival_price']];
            }
            $header = [
                lang('用户名'), lang('用户标签'), lang('充值总金额')
            ];
            Respons::run(1, '', '', '', '', new UserOfflineModel(), lang('充值排行榜数据导出'));
            Excel($header, $data, lang('充值排行榜数据') . date('Ymd', time()));
        } else {
            $rsps['data'] = $model->paginate($limit, false, ['query' => $param]);
            foreach ($rsps['data'] as &$v) {
                $v = Utils::hideUserInfo($v);
            }
        }

        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    public function list($where = null, $pay_channel = [])
    {

        $channel = Util::getOpenChannel();
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        if (is_null($where)) {
            $where = [];
        }
        $whereStr = '1=1';
        $time_type = 'a.add_time';
        if (isset($param['set_content']) && $param['set_content'] != '') {
            $where[] = ["a.remarks", "like", "%" . $param['set_content'] . "%"];
        }
        if (isset($param['username']) && !empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field, '=', $param['username']]])->value("id");
            $where[] = ["a.uid", "=", $uids];
        }
        if (isset($param['order_id']) && $param['order_id'] != '') {
            $where[] = ["a.id", "=", $param['order_id']];
        }
        if (isset($param['order_sn']) && $param['order_sn'] != '') {
            $where[] = ["a.order_sn", "=", $param['order_sn']];
        }
        if (isset($param['three_order_sn']) && $param['three_order_sn'] != '') {
            $where[] = ["a.othr_number", "=", $param['three_order_sn']];
        }
        if (isset($param['min_price']) && $param['min_price'] != '') {
            $where[] = ['apply_price', ">=", $param['min_price']];
        }
        if (isset($param['max_price']) && $param['max_price'] != '') {
            $where[] = ['apply_price', "<=", $param['max_price']];
        }

        //增加代理线搜索条件
        if (!empty($param['agent_name'])) {
            $agent_id = UserModel::where("username|mail|phone", $param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree', 'like', '%/' . $agent_id . '/%'];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= ' and a.uid in (' . $sql . ')';
        }
        //增加上级代理线搜索条件
        if (!empty($param['top_agent_name'])) {
            $top_agent_id = UserModel::where("username|mail|phone", $param['top_agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['rec_id', '=', $top_agent_id];
            $sql = UserModel::where($agent_wh)->field('id')->buildSql();
            $whereStr .= ' and a.uid in (' . $sql . ')';
        }

        if (!empty($param['level_id'])) {
            $sql = UserModel::where("level_id", $param['level_id'])->field('id')->buildSql();
            $whereStr .= ' and a.uid in (' . $sql . ')';
        }

        if (isset($param['status']) && $param['status'] != '') {
            $where[] = ["a.status", "=", $param['status']];
        }

        if (isset($param['bankcrad']) && $param['bankcrad'] != '') {
            $where[] = ["a.bankcrad", "like", "%" . $param['bankcrad'] . "%"];
        }

        if (isset($param['channel']) && $param['channel'] != '') {
            $where[] = ["a.channel", "=", $param['channel']];
        }
        if (isset($param['uid']) && $param['uid'] > 0) {
            $where[] = ['a.uid', '=', $param['uid']];
        }
        if(isset($param['pay_type']) && $param['pay_type'] !== ''){
            if(isset($pay_channel) && count($pay_channel) > 0){
                $pay_set_or_sk = $pay_channel[$param['pay_type']];
                $set_or_sk_id = $pay_set_or_sk['id'];
                if($pay_set_or_sk['p_type'] == 'online'){
                    //线上渠道的时候
                    $wh_field = 'a.pay_id';
                }else{
                    //线下渠道的时候
                    $wh_field = 'a.bank_sk_id';
                }
                $where[] = [$wh_field, '=', $set_or_sk_id];

            }else{
                $where[] = ['a.pay_id','=',$param['pay_type']];
                if( $param['pay_type'] == 0 ){
                    $where[] = ['a.type','=',$param['pay_type']];
                }
            }

        }
        if (isset($param['recharge_type']) && $param['recharge_type'] > 0) {
            if ($param['recharge_type'] == 1) {
                $where[] = ['a.first', '=', 1];
            } else {
                $where[] = ['a.first', '<>', 1];
            }
        }
        if (isset($param['time_type']) && $param['time_type'] != '') {
            if ($param['time_type'] == 1) {
                $time_type = 'a.add_time';
            } else {
                $time_type = 'a.update_time';
            }
        } else {
            $time_type = 'a.add_time';
        }
        if (isset($param['register_time']) && $param['register_time'] !== '') {
            $date = explode(' - ', $param['register_time']);
            $where[] = ['b.add_time', ">=", Utils::getQueryDateTime($date[0])];
            $where[] = ['b.add_time', "<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['date']) && $param['date'] != '') {
            $date = explode(' - ', $param['date']);
            $where[] = [$time_type, ">=", Utils::getQueryDateTime($date[0])];
            $where[] = [$time_type, "<=", Utils::getQueryDateTime($date[1], true)];
        }
        if (isset($param['lebals']) && $param['lebals'] != '') {
            if ($param['lebals'] < 0) {
                $where[] = ["a.lebal", "=", abs($param['lebals'])];
            } else {
                $tag_id = $param['lebals'];
                $where[] = ["a.uid", "IN", function ($query) use ($tag_id) {
                    return $query->name('user_tag')->where('tag_id', $tag_id)->field('uid');
                }];
            }
        }
        if (isset($param['order']) && $param['order'] != '') {
            $order = 'a.' . $param['field'] . ' ' . $param['order'];
        } else {
            $order = 'a.id desc';
        }

        $rsps['data'] = UserOfflineModel::alias("a")
            ->join('user b', 'b.id=a.uid')
            ->where($where)->where($whereStr)
            ->order($order)
            ->field("a.*,b.username,b.mail,b.phone,b.rec_id,b.tree,b.add_time as register_time,b.reg_ip")
            ->paginate($limit, false, ['query' => $param]);

        $pay_name = PaySetModel::column('pay_name', 'id');
        $pay_name_remarks = PaySetModel::column('remarks', 'id');
        foreach ($rsps['data'] as $key => &$val) {
            $val['channel_name'] = isset($channel[$val['channel']]) ? lang($channel[$val['channel']]) : "";
            $val['add_time'] = date("Y-m-d H:i:s", $val['add_time']);
            $val['register_time'] = date("Y-m-d H:i:s", $val['register_time']);
            if ($val['update_time']) {
                $val['update_time'] = date("Y-m-d H:i:s", $val['update_time']);
            } else {
                $val['update_time'] = '';
            }
            if ($val['pay_id'] > 0) {
                if ($val['channel'] === 20) {
                    $val['pay_name'] = Db::name('agent')->where(['id' => $val['pay_id']])->value('username');
                } else {
                    $val['pay_name'] = $pay_name[$val['pay_id']] ?? $val['pay_id'];
                    $val['pay_name_remark'] = $pay_name_remarks[$val['pay_id']] ?? '';
                    if ($val['pay_name_remark']) {
                        $val['pay_name'] .= "[{$val['pay_name_remark']}]";
                    }
                }
            }
            $val['rec_user'] = MemberService::getUsernameByUid($val['rec_id']);
            $val['rec_top_user'] = MemberService::getTopAgentByTree($val['tree']);
            $val['tags'] = MemberService::getTagByUid($val['uid']);
            $val = Utils::hideUserInfo($val);
        }
        //合计
        $userOfflineList=$rsps['data'];
        $userOfflineList = $userOfflineList->toArray();
        if( $userOfflineList&&$userOfflineList['data'] ){
            $total = [];
            foreach($userOfflineList['data'] as $v){
                foreach ($v AS $key => $val) {
                    if(!in_array($key,['apply_price','arrival_price'])){
                        $total[$key] = $val;
                        continue;
                    }
                    if( isset($total[$key]) && is_numeric($val)){
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
            $total['rec_user']="";
            $total['exchange_rate']=""; 
            $total['pay_currency']="";
            $total['status']="";
            $total['add_time']="";
            $total['update_time']="";
            $total['admin_name']="";
            $total['set_content']="";
            $total['channel_name']="";
            $total['not_auto_action']="";
            $userOfflineList['data'][] = $total;
        }
        $rsps['data']=$userOfflineList;
        $tags = [
            ['label' => '一般用户', 'value' => -1],
            ['label' => '测试用户', 'value' => -2],
//            ['label' => '三方登录用户', 'value' => -3],
        ];
        $rsps['tags'] = array_merge($tags, Utils::getOptions('tag'));
        $rsps['user_level'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $rsps['code'] = 1;
        //组合渠道select
        $channel_select = [];
        foreach ($channel as $k => $v) {
            $channel_select[] = ['label' => lang($v), 'value' => $k];
        }
        $rsps['channel_select'] = $channel_select;
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
        $params['positionField'] = 'a.assign_time';
        $params['positionOrder'] = 'desc';
        $params['muid'] = $this->uid;
        $stateArr = [-2 => lang('已取消'), -1 => lang('延迟未付款'), 1 => lang('待审核'), 2 => lang('未通过'), 3 => lang('已通过'), 4 => lang('手动入款')];
        $rlt = ReportExport::export(new rptParams('会员充值数据',
            [
                new rptCol('uid', '用户UID'),
                'order_sn:订单号',
                'rec_code:邀请码',
                new rptCol('channel', '数据来源', 'app\common\service\ReportDownloadService::getChannelName'),
                new rptCol('add_time', '申请时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
                new rptCol('register_time', '注册时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
                new rptCol('lebal', '用户标签', "app\common\service\ReportDownloadService::getLebal"),
                new rptCol('tree', '总代', 'app\common\service\ReportDownloadService::getTopAgentByTree'),
                new rptCol('rec_id', '上级', 'app\common\service\ReportDownloadService::getUsernameByUid'),
                'apply_price:申请金额',
                'arrival_price:到账金额',
                new rptCol('pay_id', '支付渠道', 'app\common\service\ReportDownloadService::getPayChannel'),
                new rptCol('update_time', '支付时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
                new rptCol('status', '状态', rptCol::transType_kvtrans, $stateArr),
                'remarks:备注',
                new rptCol('bankname', '用户银行卡信息', 'app\common\service\ReportDownloadService::getBankInfo'),
                new rptCol('bank_sk_id', '收款银行信息', 'app\common\service\ReportDownloadService::getSysInfo'),
                'admin_name:操作人',
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['rec_id', 'uid'],//导出表名称使用的字段信息
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
        return UserOfflineModel::alias("a")
            ->join('user b', 'b.id=a.uid')
            ->where($param['where'])->where($param['whereStr'])
            ->field("a.*,b.username,b.mail,b.phone,b.rec_id,b.rec_code,b.tree,b.add_time as register_time,b.reg_ip");
    }
}

?>
