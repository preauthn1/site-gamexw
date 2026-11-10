<?php

namespace app\manage\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\lib\Util;
use app\common\model\GameBetFeeModel;
use app\common\model\GameBetsHotModel;
use app\common\model\GameBetsModel;
use app\common\model\GameInfosModel;
use app\common\model\GamesModel;
use app\common\model\GameTypeModel;
use app\common\model\UserModel;
use app\common\service\BetService;
use app\common\Utils;
use app\model\Games;
use think\facade\Db;
use think\facade\Request;

class GameBetsFactory
{
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->uid = $uid;
    }

    public function lists($where = null)
    {
        $lock = userLock($this->uid, 'game_bets_list');
        if (!$lock) {
            return Respons::run(0, '', lang("正在查询结果中,请耐心等待"));
        }
        $param = Request::param();
        $game_third = Db::name('third_configs')->column("tc_name", "tc_id");
        $limit = $param['limit'] ?? 10;
        $page = $param['page'] ?? 1;
        $offset = ($page - 1) * $limit;
        $GameBetsModel = new GameBetsModel();
        //注单类型
        if (isset($param['game_order_type']) && $param['game_order_type'] == 1) {
            $GameBetsModel = new GameBetsHotModel();
        }

        if (is_null($where)) {
            $where = [];
        } else {
            switch ($where) {
                case 'bytree':
                    $user = UserModel::where('id', $this->uid)->find();
                    $where = [
                        ['a.gb_uid', "IN", function ($query) use ($user) {
                            $query->name('user')->where([['tree', 'like', $user['tree'] . '%']])->field('id');
                        }]
                    ];
                    break;
                default:
                    $where = [];
                    break;
            }
        }
        if (isset($param['date']) && $param['date'] != '') {
            $date = explode(' - ', $param['date']);
        } else {
            $date = [date('Y-m-d'), date('Y-m-d')];
        }
        $timezone = null;
        if (isset($param['time_zone']) && $param['time_zone'] != '') {
            $timezone = gettimezone($param['time_zone']);
            $s_time = Util::getTimeZoneConversion(Utils::getQueryDateTime($date[0]), $timezone);
            $e_time = Util::getTimeZoneConversion(Utils::getQueryDateTime($date[1], true), $timezone);
        } else {
            $s_time = Utils::getQueryDateTime($date[0]);
            $e_time = Utils::getQueryDateTime($date[1], true);
        }
        $where[] = ["a.gb_bettime", ">=", $s_time];
        $where[] = ["a.gb_bettime", "<=", $e_time];
        $whereStr = BetService::getGbidByTime($s_time, $e_time, 'gb_bettime', 'a')['sql'];
        if (isset($param['username']) && $param['username'] != '') {
            $where_field = is_numeric($param['username']) ? 'id|rec_code|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field, '=', $param['username']]])->value("id");
            $where[] = ["a.gb_uid", "=", $uids];
        }
        if (isset($param['game_sys_sign']) && $param['game_sys_sign'] != '') {
            $gids = GameInfosModel::where([['gi_name', 'like', "%{$param['game_sys_sign']}%"]])->column("gi_gid");
            if (empty($gids)) {
                $gid = Games::where([['g_id|g_code|g_tag', '=', $param['game_sys_sign']]])->value("g_id");
                $where[] = ["a.gb_gid", "=", $gid];
            } else {
                $where[] = ["a.gb_gid", "in", $gids];
            }
        }

        if (!empty($param['agent_name'])) {
            $agent_id = UserModel::where("rec_code|username|mail|phone", $param['agent_name'])->value("id") ?? 0;
            $agent_wh[] = ['tree', 'like', '%/' . $agent_id . '/%'];
            $agentuid = UserModel::where($agent_wh)->column('id');
            $where[] = ["a.gb_uid", "in", $agentuid];
        }
        if (isset($param['lebal']) && $param['lebal'] != '') {
            $sql = UserModel::where(['lebal' => $param['lebal']])->field('id')->buildSql();
            if (empty($whereStr)) {
                $whereStr = 'a.gb_uid in (' . $sql . ')';
            } else {
                $whereStr .= ' AND a.gb_uid in (' . $sql . ')';
            }
        }
        if (isset($param['game_type_id']) && $param['game_type_id'] !== '') {
            $sql1 = GamesModel::where(['g_type' => $param['game_type_id']])->field('id')->buildSql();
            $where[] = ['d.id', '=', $param['game_type_id']];
//            if(empty($whereStr)){
//                $whereStr = 'a.gb_gid in ('.$sql1 .')';
//            }else{
//                $whereStr .= ' AND a.gb_gid in ('.$sql1 .')';
//            }
        }
        if (!empty($param['tc_id'])) {
            $where[] = ["a.gb_tcid", "=", $param['tc_id']];
        }
        $order = 'a.gb_id desc';
        if (!empty($param['order_field']) && !empty($param['order_type'])) {
            $o_f = $param['order_field'];
            $o_t = $param['order_type'];
            $order = "a.{$o_f} {$o_t}";
        }

        if (isset($param['status']) && $param['status'] != '') {
            if ($param['status'] >= 2) {
                $where[] = ["a.gb_winlose", ">", 1];
            } else if ($param['status'] == 1) {
                $where[] = ["a.gb_winlose", "=", $param['status']];
                $GameBetsModel = new GameBetsHotModel();
                $where[] = ["a.gb_bettime", ">=", $s_time];
                $where[] = ["a.gb_bettime", "<=", $e_time];
                $whereStr = '';
            } else {
                $where[] = ["a.gb_winlose", "=", $param['status']];
            }
        }
//        $today_time = Carbon::today()->timestamp;
//        if(isset($param['min_amount']) && $param['min_amount']!='' && $s_time >= $today_time){
        //项目方需求去掉时间限制查询最大最小金额
        if (isset($param['min_amount']) && $param['min_amount'] != '') {
            $where[] = ["a.gb_price", ">=", $param['min_amount']];
        }
//        if(isset($param['max_amount']) && $param['max_amount']!='' && $s_time >= $today_time){
        if (isset($param['max_amount']) && $param['max_amount'] != '') {
            $where[] = ["a.gb_price", "<=", $param['max_amount']];
        }
//        if(isset($param['min_win_amount']) && $param['min_win_amount'] != '' && $s_time >= $today_time){
        if (isset($param['min_win_amount']) && $param['min_win_amount'] != '') {
            $where[] = ["a.gb_winprice", ">=", $param['min_win_amount']];
        }
//        if(isset($param['max_win_amount']) && $param['max_win_amount'] != '' && $s_time >= $today_time){
        if (isset($param['max_win_amount']) && $param['max_win_amount'] != '') {
            $where[] = ["a.gb_winprice", "<=", $param['max_win_amount']];
        }
        if (isset($param['gb_sn']) && $param['gb_sn'] != '') {
            $where[] = ["a.gb_sn", "=", $param['gb_sn']];
        }
        $result = [];
//        $result['total_amount'] = GameBetsModel::alias("a")->join('user b', 'b.id=a.gb_uid', 'LEFT')->where($where)->where($whereStr)->sum('a.gb_price');
//        $result['total_win_amount'] = GameBetsModel::alias("a")->join('user b', 'b.id=a.gb_uid', 'LEFT')->where($where)->where($whereStr)->sum('a.gb_winprice');
        $gb_winlose = [0 => '无效', 1 => '未结算', 2 => '已结算', 3 => '已结算', 4 => '已结算'];
        if (isset($param['export']) && $param['export'] == 1) {
            return $this->exportList($where, $whereStr);
        } else {
//            list('max' => $max_id, 'min' => $min_id) = GameService::getBetsIdRange($s_time, $e_time);
            $m = $GameBetsModel->alias("a")
                ->join('games c', 'c.g_id=a.gb_gid', 'LEFT')
                ->join('game_type d', 'd.id=c.g_type', 'LEFT')
                ->where($where)->where($whereStr);
            $result['total'] = $m->count('gb_id'); //投注笔数
            $result['total_bets_money'] = $m->sum('gb_price'); //投注金额
            $result['total_bets_gb_winprice'] = $m->sum('gb_winprice'); //派彩金额
            $result['total_bets_gb_otherwin'] = $m->sum('gb_otherwin'); //实际输赢
            $temp_all_data = $GameBetsModel->alias("a")
                ->join('games c', 'c.g_id=a.gb_gid', 'LEFT')
                ->join('game_type d', 'd.id=c.g_type', 'LEFT')
                ->group('a.gb_gid')
                ->field('a.gb_gid, sum(gb_otherwin) as gb_otherwin')
                ->where($where)->where($whereStr)->select();
            $otherwin_all = 0;
            foreach ($temp_all_data as $value) {
                $value['gb_otherwin'] = floatval($value['gb_otherwin']);
                if ($value['gb_otherwin'] < 0) {
                    $otherwin_all += $value['gb_otherwin'];
                }
            }
            $result['total_bets_gb_otherwin_all'] = round($otherwin_all, 2); //总输赢
            $result['total_bets_validwater'] = $m->sum('gb_validwater'); //有效流水
            $result['data'] = $GameBetsModel->alias("a")
                ->join('user b', 'b.id=a.gb_uid', 'LEFT')
                ->join('games c', 'c.g_id=a.gb_gid', 'LEFT')
                ->join('game_type d', 'd.id=c.g_type', 'LEFT')
                ->where($where)->where($whereStr)
                ->field('a.gb_id, a.gb_tcid, a.gb_gid, a.gb_uid, a.gb_price, a.gb_sn, a.gb_winlose, a.gb_bettime, a.gb_overtime,a.gb_intime, a.gb_winprice, a.gb_trans_no, a.gb_otherwin, a.gb_validwater,
                b.username,b.mail,b.phone,d.name as type_name,'
                    . '(select gi_name from dq_game_infos where gi_gid=a.gb_gid limit 1) as game_sys_sign')
                ->order("a.gb_id desc")
                ->filter(function ($data) use ($game_third, $timezone) {
                    $data['gb_bettime'] = Util::localTimeToTimezone($data['gb_bettime'], $timezone);
                    $data['gb_overtime'] = Util::localTimeToTimezone($data['gb_overtime'], $timezone);
                    $data['gb_intime'] = Util::localTimeToTimezone($data['gb_intime'], $timezone);
                    $data['gb_winprice'] = ($data['gb_winlose'] == 1 || $data['gb_winlose'] == 0) ? "0.00" : $data['gb_winprice'];
                    $data['game_third'] = $game_third[$data['gb_tcid']] ?? '';
                    if (app('http')->getName() == "manage") {
                        $data = Utils::hideUserInfo($data);
                    }
                })
                ->limit($offset, $limit)
                ->select();
        }
        $result['code'] = 1;
        $result['game_third'] = $game_third;
        $result['timezone'] = $timezone;
        $result['user_level'] = Utils::getOptions('user_level', ['id', 'level_name']);
        $result['game_type'] = Utils::getOptions('game_type');
        return Respons::run($result);
    }

    public function user_list()
    {
        $param = Request::param();
        $lock = userLock($param['uid'], 'game_bets_user_list');
        if (!$lock) {
            return Respons::run(0, '', lang("正在查询结果中,请耐心等待"));
        }
        $where = [];
        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
            if (date("Y-m-d", strtotime($time[0])) != date("Y-m-d", strtotime($time[1]))) {
                return Respons::run(0, '', lang("只支持查询同一天的时间范围"));
            }
        } else {
            $time = [date("Y-m-d") . " 00:00:00", date("Y-m-d") . " 23:59:59"];
        }

        $start_time = Utils::getQueryDateTime($time[0]);
        $end_time = Utils::getQueryDateTime($time[1], true);
        $where[] = ["gb_bettime", ">=", $start_time];
        $where[] = ["gb_bettime", "<=", $end_time];
        $where[] = ["gb_uid", "=", $param['uid']];
        if (isset($param['tc_id']) && $param['tc_id'] > 0) {
            $where[] = ["gb_tcid", "=", $param['tc_id']];
        }
        if (isset($param['gid']) && $param['gid'] != '') {
            if (!is_numeric($param['gid'])) {
                $param['gid'] = GameInfosModel::where("gi_name", "LIKE", "%{$param['gid']}%")->value('gi_gid');
            }
            $where[] = ["gb_gid", "=", $param['gid']];
        }
        if (isset($param['g_type']) && $param['g_type'] > 0) {
            $where[] = ["c.id", "=", $param['g_type']];
        }
        $groupbys = ["c.id", "a.gb_tcid", "a.gb_gid"];
        $stat_type = isset($param['stat_type']) && in_array($param['stat_type'], [0, 1, 2]) ? $param['stat_type'] : 0;

        if (isset($param['order']) && $param['order'] != '') {
            $order = '' . $param['field'] . ' ' . ($param['order'] == 'DESC' ? 'DESC' : 'ASC');
        } else {
            $order = 'profit desc';
        }
        $thirds = Db::name('third_configs')->column('tc_name', 'tc_id');
        $game_names = GameInfosModel::column('gi_name', 'gi_gid');
        $game_types = GameTypeModel::column('name', 'id as gt_id');
        $field = "a.gb_gid, a.gb_tcid, 
            SUM(a.gb_price) as bet_amount,
            SUM(a.gb_winprice) as bet_win_amount, 
            SUM(a.gb_validwater) as bet_valid_amount, 
            SUM(a.gb_otherwin*-1) as profit,
            c.name as type_name";
        $page = $param['page'];
        $size = $param['limit'];
        $offset = ($page - 1) * $size;
        $game_stat = Db::table('dq_game_bets')->alias("a")
            ->join('dq_games b', 'b.g_id=a.gb_gid', 'LEFT')
            ->join('dq_game_type c', 'b.g_type=c.id', 'LEFT')
            ->where('a.gb_tcid', 'not null')   //平台不能为空
            ->where($where)
            ->group($groupbys[$stat_type])->field($field)->limit($offset, $size)->order($order)->select();
        $total = Db::table('dq_game_bets')->alias("a")
            ->join('dq_games b', 'b.g_id=a.gb_gid', 'LEFT')
            ->join('dq_game_type c', 'b.g_type=c.id', 'LEFT')
            ->field($field)
            ->where($where)
            ->where('a.gb_tcid', 'not null')   //平台不能为空
            ->group($groupbys[$stat_type])->count();

        $game_stat_count = [];
        $game_stat_count['type_name'] = lang('合计');
        $game_stat_count['tc_name'] = $game_stat_count['type_name'];
        $game_stat_count['bet_amount'] = 0;
        $game_stat_count['bet_valid_amount'] = 0;
        $game_stat_count['bet_win_amount'] = 0;
        $game_stat_count['profit'] = 0;
        if (!$game_stat->isEmpty()) {
            $game_stat = $game_stat->each(function ($item) use ($game_names, $thirds, $game_types, $where, &$game_stat_count) {
                $item['game_name'] = $game_names[$item['gb_gid']] ?? $item['gb_gid'];
                $item['tc_name'] = $thirds[$item['gb_tcid']] ?? $item['gb_tcid'];
                $item['type_name'] = $game_types[$item['type_name']] ?? $item['type_name'];
                $game_stat_count['bet_amount'] += $item['bet_amount'];
                $game_stat_count['bet_valid_amount'] += $item['bet_valid_amount'];
                $game_stat_count['bet_win_amount'] += $item['bet_win_amount'];
                $game_stat_count['profit'] += $item['profit'];
                return $item;
            });
        }
        $game_stat_count['bet_amount'] = sprintf("%.2f", $game_stat_count['bet_amount']);
        $game_stat_count['bet_valid_amount'] = sprintf("%.2f", $game_stat_count['bet_valid_amount']);
        $game_stat_count['bet_win_amount'] = sprintf("%.2f", $game_stat_count['bet_win_amount']);
        $game_stat_count['profit'] = sprintf("%.2f", $game_stat_count['profit']);
        $game_stat[] = $game_stat_count;
        $result = [
            'code' => 1,
            'game_third' => Utils::getOptions('third_configs', ['tc_id', 'tc_name']),
            'game_type' => Utils::getOptions('game_type', ['id', 'name']),
            'data' => $game_stat,
            'total' => $total,
        ];
        return Respons::run($result);
    }

    public function exportList($where = null, $whereStr = null)
    {
        $params = Request::param();
        $params['where'] = $where;
        $params['whereStr'] = $whereStr;
        $params['positionField'] = 'a.gb_id';
        $params['positionOrder'] = 'asc';
        $params['muid'] = $this->uid;
        $rlt = ReportExport::export(new rptParams('会员游戏记录',
            [
                new rptCol('gb_id', 'ID'),
                new rptCol('gb_gid', '游戏名称', rptCol::transType_gamename),
                new rptCol('gb_tcid', '游戏平台', rptCol::transType_thirdname),
                'type_name:分类名称',
                'gb_uid:UID',
                'mail:用户邮箱',
                'username:用户名',
                'phone:手机号',
                'gb_sn:注单号',
                new rptCol('gb_bettime', '投注时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
                new rptCol('gb_overtime', '结算时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
                'gb_price:投注金額',
                'gb_validwater:有效投注',
                'gb_otherwin:输赢',
                new rptCol('gb_winlose', '狀態', rptCol::transType_kvtrans, [0 => '无效', 1 => '未结算', 2 => '已结算', 3 => '已结算', 4 => '已结算'])
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['username', 'agent_name', 'date', 'tcid'],
            'a.gb_id', 'asc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        $GameBetsModel = new GameBetsModel();
        return $GameBetsModel->alias("a")
            ->join('user b', 'b.id=a.gb_uid', 'LEFT')
            ->join('games c', 'c.g_id=a.gb_gid', 'LEFT')
            ->join('game_type d', 'd.id=c.g_type', 'LEFT')
            ->where($param['where'])->where($param['whereStr'])
            ->field('a.gb_id, a.gb_tcid, a.gb_gid, a.gb_uid, a.gb_price, a.gb_sn, a.gb_winlose, a.gb_bettime, a.gb_overtime, a.gb_winprice, a.gb_trans_no, a.gb_otherwin, a.gb_validwater,
                b.username,b.mail,b.phone,d.name as type_name,'
                . '(select gi_name from dq_game_infos where gi_gid=a.gb_gid limit 1) as game_sys_sign');

    }


    /**
     * 导出数据
     * @param null $where
     * @return \think\response\Json
     */
    public function betFeeExportList($where = null)
    {

        $params = Request::param();
        $params['where'] = $where;
        $params['positionField'] = 'f.id';
        $params['positionOrder'] = 'asc';
        $params['muid'] = request()->token->uid;
        $rlt = ReportExport::export(new rptParams('中奖手续费记录',
            [
                'uid:UID',
                'username:用户名',
                'mail:邮箱',
                'phone:手机号',
                'rec_code:邀请码',
                'tc_name:平台名称',
                'gi_name:游戏名称',
                'gbsn:注单号',
                'old_price:原派彩金额',
                'fee_ratio:平台手续费比例',
                'fee:平台手续费',
                'now_price:现派彩金额',
                'remarks:备注',
                'create_time:时间',
            ],
            __CLASS__, 'betFeeExportListMethod',
            $params,
            ['username', 'tc_id', 'g_name'],//导出表名称使用的字段信息
            $params['positionField'], $params['positionOrder']
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function betFeeExportListMethod($param)
    {
        return GameBetFeeModel::alias('f')
            ->join('dq_user u', "u.id=f.uid")
            ->join('dq_game_infos g', 'f.gid=gi_gid', 'left')
            ->join('dq_third_configs t', 'f.tcid=tc_id')
            ->where($param['where'])
            ->field('f.*,u.username,u.id as uid,u.mail,u.phone,u.rec_code,g.gi_name,t.tc_name');
    }
}