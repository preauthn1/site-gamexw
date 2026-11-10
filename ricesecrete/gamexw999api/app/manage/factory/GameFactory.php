<?php

namespace app\manage\factory;

use app\common\FastGame\Base\Game;
use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\model\GameInfosModel;
use app\common\model\GamesModel;
use app\common\model\GameTypeModel;
use app\common\model\NationModel;
use app\common\model\UserModel;
use app\common\redis\CommomRedis;
use app\common\service\BetService;
use app\common\Utils;
use app\model\Games;
use app\model\UserThirds;
use think\facade\Db;
use think\facade\Request;

class GameFactory
{
    protected $middleware = ['ManageCheckLogin', "Language"];

    private $uid;//用户id
    public $tag = 'game-report-v1';

    //验证
    public function __construct($uid = 0)
    {
        $this->uid = $uid;
    }

    public function user_report()
    {
        $param = Request::param();
        $month = Db::name('third_stats_fyfy')->order("sff_m desc")->group("sff_m")->column("sff_m");
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where = [];
        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
            $where[] = ["a.su_dt", ">=", date("Ymd", strtotime($time[0]))];
            $where[] = ["a.su_dt", "<=", date("Ymd", strtotime($time[1]))];
        }

        if (!empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|phone|rec_code' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field, '=', $param['username']]])->value("id");
            $where[] = ["a.su_uid", "in", $uids];
        }

        if (!empty($param['agent_name'])) {
            $where_field = is_numeric($param['agent_name']) ? 'id|phone|rec_code' : 'username|mail|phone';
            $agent_tree = UserModel::where($where_field, $param['agent_name'])->field("tree, rec_level")->find();
            if (empty($agent_tree)) {
                $result['code'] = 0;
                $result['msg'] = '代理账户信息有误';
                return Respons::run($result);
            }
//            $where[] = ['a.tree', 'like', "{$agent_tree['tree']}%"];
            $where_s = [];
            if (!empty($param['rec_level'])) {
                $rec_level = $agent_tree['rec_level'] + intval($param['rec_level']);
                $where_s[] = ['a.tree', 'like', "{$agent_tree['tree']}%"];
                $where_s[] = ['a.rec_level', '=', "{$rec_level}"];
            } else {
                $where_s[] = ['a.tree', 'like', "{$agent_tree['tree']}%"];
            }
            $ids = UserModel::alias('a')->where($where_s)->group('id')->column("id");
            $where[] = ['a.su_uid', 'in', $ids];
        }
        if (!empty($param['thirds']) && $param['thirds'] <> "") {
            $where[] = ["a.g_tcid", "=", $param['thirds']];
        }

        $result = [];
        $tables = BetService::getThirdStatsTable();
        $result['total'] = Db::name($tables['_stats_users'])->alias('a')->where($where)->count();
        if (isset($param['export']) && $param['export'] == 1) {
            return $this->exportList($where, "");
        } else {
            if (isset($param['order']) && $param['order'] != '') {
                $order = '' . $param['field'] . ' ' . ($param['order'] == 'DESC' ? 'DESC' : 'ASC');
            } else {
                $order = 'su_dt desc';
            }
            $lists = Db::name($tables['_stats_users'])->alias('a')->where($where)
                ->join('user b', 'b.id=a.su_uid')
                ->limit((($page - 1) * $limit), $limit)
                ->field('a.*,b.username,b.mail,b.phone')
                ->order($order)
                ->select();
        }
        if ($lists) {
            $lists = $lists->toArray();
        }

        foreach ($lists as &$value) {
            $value = Utils::hideUserInfo($value);
            $value['su_balance'] = UserModel::where(['id' => $value['su_uid']])->value('balance');
            $value['su_third_balance'] = Db::name("user_thirds")->where(['ut_uid' => $value['su_uid']])->sum("ut_balance");
        }
        //const total_su_betnum = ref(0)
        //const total_su_bet = ref(0)
        //const total_su_validwater = ref(0)
        //const total_su_otherwin = ref(0)
        $total_data = [
            'total_su_betnum' => Db::name($tables['_stats_users'])->alias('a')->where($where)->sum('su_betnum'),
            'total_su_bet' => Db::name($tables['_stats_users'])->alias('a')->where($where)->sum('su_bet'),
            'total_su_validwater' => Db::name($tables['_stats_users'])->alias('a')->where($where)->sum('su_validwater'),
            'total_su_otherwin' => Db::name($tables['_stats_users'])->alias('a')->where($where)->sum('su_otherwin'),
        ];
        $result['data'] = $lists;
        $result['total_data'] = $total_data;
        $result['code'] = 1;
        return Respons::run($result);
    }

    public function back()
    {
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where = [];
        if (isset($param['status']) && $param['status'] <> '') {
            $where[] = ["a.sb_payed", "=", $param['status']];
        }

        if (isset($param['date']) && $param['date'] != '') {
            $time = explode(" - ", $param['date']);
            $where[] = ["a.sb_dt", ">=", date("Ymd", strtotime($time[0]))];
            $where[] = ["a.sb_dt", "<=", date("Ymd", strtotime($time[1]))];
        }
        if (isset($param['date2']) && $param['date2'] != '') {
            $time = explode(" - ", $param['date2']);
            $where[] = ["a.sb_paytime", ">=", Utils::getQueryDateTime($time[0])];
            $where[] = ["a.sb_paytime", "<=", Utils::getQueryDateTime($time[1], true)];
        }

        if (!empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone' : 'username|mail|phone';
            $uids = UserModel::where([[$where_field, '=', $param['username']]])->value("id");
            $where[] = ["a.sb_uid", "=", $uids];
        }

        //派发类型（1有效派发，0所有）
        if (isset($param['delivery_tpye']) && $param['delivery_tpye'] == 1) {
            $where[] = ["a.sb_backmoney", ">", 0];
        }

        $rsps['count'] = Db::name('third_stats_back')->alias('a')->where($where)->count();

        if (isset($param['export']) && $param['export'] == 1) {
            return $this->exportListBack($where, '');
        } else {
            $lists = Db::name('third_stats_back')->alias('a')->where($where)
                ->join('user b', 'b.id=a.sb_uid')
                ->limit((($page - 1) * $limit), $limit)
                ->field('a.*,b.username,b.mail,b.phone')
                ->order("sb_id desc")
                ->select();
        }
        $game_type = GameTypeModel::column("name", "id");
        if ($lists) {
            $lists = $lists->toArray();
            $status_text = ['无效', '未派发', '已派发'];
            foreach ($lists as &$value) {
                $value['sb_paytime'] = empty($value['sb_paytime']) ? '-' : date("Y-m-d H:i:s", $value['sb_paytime']);
                $value['sb_statstime'] = empty($value['sb_statstime']) ? '-' : date("Y-m-d H:i:s", $value['sb_statstime']);
                $value['type_name'] = isset($game_type[$value['sb_gtid']]) ? $game_type[$value['sb_gtid']] : '';
                $value['sb_viplvl'] = "VIP" . ($value['sb_viplvl'] - 1);
                $value['sb_payed_text'] = $status_text[$value['sb_payed']] ?? $value['sb_payed'];
                $value = Utils::hideUserInfo($value);
            }
        }
        $rsps['data'] = $lists;
        $rsps['code'] = 1;
        //慢查询取消用户表关联
        //反水总额
        $rsps['total_water_num'] = Db::name('third_stats_back')->alias('a')->where($where)
//            ->join('user b', 'b.id=a.sb_uid')
            ->sum('sb_backmoney');
        return Respons::run($rsps);
    }

    public function report($whereStr = "", $param = [])
    {
        $lock = userLock($this->uid, 'game_report');
        if (!$lock) {
            return Respons::run(0, '', lang("正在查询结果中,请耐心等待"));
        }
        if (!$param) {
            $param = Request::param();
        }
        $info = $param;
        if (isset($info['ver'])) {
            $info['ver'] = '';
        }
        if (isset($info['date']) && $info['date'] != '') {
            $date = explode(' - ', $info['date']);
            if (empty($date) || count($date) != 2) {
                return Respons::run(0, '', lang("时间参数有误"));
            }
        } else {
            $date = [date("Y-m-d", strtotime('-3 day')), date('Y-m-d')];
        }

        // 某些渠道对账有可能是按跨日中午时间 2023-01-01 12:00:00 ~ 2023-01-02 11:59:59
        $start_time = Utils::getQueryDateTime($date[0]);
        $end_time = Utils::getQueryDateTime($date[1], true);
        $default_values = [
            'date' => '',
            'tz_number' => 0,
            'tz_num' => 0,
            'tz_price' => 0,
            'bet_valid_amount' => 0,
            'tz_win' => 0,
            'tz_ptsy' => 0,
        ];
        $whereStr1 = '';
        if (isset($info['game_name']) && $info['game_name'] != '') {
            $gids = GameInfosModel::where([['gi_name', 'like', "%{$info['game_name']}%"]])->column("gi_gid");
            $whereStr1 .= ' and su_gid in (' . implode(',', $gids) . ')';
        }

        if (isset($info['tc_id']) && $info['tc_id'] != '') {
            $whereStr1 .= ' and su_tcid = ' . $info['tc_id'];
        }

        if (isset($info['type']) && $info['type'] != '') {
            $type_id = GamesModel::where([['g_type', '=', $info['type']]])->column("g_id");
            $whereStr1 .= ' and su_gid in (' . implode(',', $type_id) . ')';
        }

        if (!empty($info['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone|rec_code' : 'username|mail|phone';
            $uid = UserModel::where([[$where_field, '=', $param['username']]])->value("id");
            if (empty($uid)) {
                return $this->error('请输入正确用户信息');
            }
            $whereStr1 .= ' and su_uid = ' . $uid;
        }
        if ($whereStr1) {
            $whereStr1 = substr($whereStr1, 5);
        }

        $CommomRedis = new CommomRedis();
        $betTime = [];
        $betTime[] = ['su_dt', 'between', [date("Ymd", $start_time), date("Ymd", $end_time)]];
        $info['time_zone'] = isset($info['time_zone']) ? $info['time_zone'] : '';
        $tables = BetService::getThirdStatsTable($info['time_zone']);
        $field = "su_dt,su_gid,SUM(su_bet) as tz_price,SUM(su_valid_bet) as bet_valid_amount,COUNT(su_uid) as tz_number,
                SUM(su_betnum) as tz_num,SUM(su_win) as tz_win,SUM(su_syswin) as tz_ptsy";
        if (isset($param['export']) && $param['export'] == 1) {
            if (isset($param['export']) && $param['export'] == 1) {
                return $this->exportListGame($whereStr1, $betTime, $tables['stats_global'], $field);
            }
        }
        $redisKey = "report_" . md5(json_encode($info));
        if ($CommomRedis->has($redisKey)) {
            $arr = $CommomRedis->get($redisKey);
        } else {
            if ($info['ver'] == 'bill') {
                $field_all = "su_dt,su_gid,su_bet as tz_price,su_valid_bet as bet_valid_amount,su_uid as tz_number,su_betnum as tz_num,su_win as tz_win,su_syswin as tz_ptsy";
                $list = Db::table($tables['stats_global'])->field($field_all)->where($whereStr1)->where($whereStr)->where($betTime)->order('su_dt DESC')->select();
                $temp_list = [];
                foreach ($list as $ke => $v) {
                    $key = $v['su_dt'] . "_" . $v['su_gid'];
                    if (isset($temp_list[$key])) {
                        $temp_list[$key]['tz_price'] += floatval($v['tz_price']);
                        $temp_list[$key]['bet_valid_amount'] += floatval($v['bet_valid_amount']);
                        $temp_list[$key]['tz_number'] += 1;
                        $temp_list[$key]['tz_num'] += $v['tz_num'];
                        $temp_list[$key]['tz_win'] += $v['tz_win'];
                        $temp_list[$key]['tz_ptsy'] += floatval($v['tz_ptsy']);
                    } else {
                        $v['tz_number'] = 1;
                        $temp_list[$key] = $v;
                    }
                }
                $list_temp = [];
                foreach ($temp_list as $ke => $va) {
                    if (floatval($va['tz_ptsy']) <= 0) {
                        $va['tz_ptsy'] = 0;
                    }
                    $va['tz_ptsy'] = round($va['tz_ptsy'], 2);
                    if (isset($list_temp[$va['su_dt']])) {
                        $list_temp[$va['su_dt']]['tz_price'] += $va['tz_price'];
                        $list_temp[$va['su_dt']]['bet_valid_amount'] += $va['bet_valid_amount'];
                        $list_temp[$va['su_dt']]['tz_number'] += $va['tz_number'];
                        $list_temp[$va['su_dt']]['tz_num'] += $va['tz_num'];
                        $list_temp[$va['su_dt']]['tz_win'] += $va['tz_win'];
                        $list_temp[$va['su_dt']]['tz_ptsy'] += $va['tz_ptsy'];
                    } else {
                        $list_temp[$va['su_dt']] = $va;
                    }
                }
                $list = [];
                foreach ($list_temp as $ke => $va) {
                    $va['tz_price'] = round($va['tz_price'], 2);
                    $va['bet_valid_amount'] = round($va['bet_valid_amount'], 2);
                    $va['tz_win'] = round($va['tz_win'], 2);
                    $va['tz_ptsy'] = round($va['tz_ptsy'], 2);
                    $list[] = $va;
                }
            } else {
                $list = Db::table($tables['stats_global'])->field($field)->where($whereStr1)->where($whereStr)->where($betTime)->group('su_dt')->order('su_dt DESC')->select();
            }
            $default_values['date'] = lang('总计');
            $total_values = $default_values;
            $arr = [];
            foreach ($list as $ke => $v) {
                $key = date("Y-m-d", strtotime($v['su_dt']));
                foreach ($v as $k => $va) {
                    if (isset($total_values[$k])) {
                        $total_values[$k] += floatval($v[$k]);
                        $total_values[$k] = sprintf4f($total_values[$k]);
                    }
                }
                $v['date'] = $key;
                $arr[$key] = $v;
            }
            $arr[lang('总计')] = $total_values;
            $CommomRedis->set($redisKey, $arr, 10);
        }
        $game_type = GameTypeModel::order("sort desc,id desc")->where(['state' => 1])->select();
        $thirds = Db::name('third_configs')->column("tc_name", "tc_id");
        $rsps['game_type'] = $game_type;
        $rsps['thirds'] = $thirds;
        $rsps['data'] = $arr;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    public function report_detail($whereStr = "", $param = [])
    {
        $lock = userLock($this->uid, 'game_detail_report');
        if (!$lock) {
            return Respons::run(0, '', lang("正在查询结果中,请耐心等待"));
        }
        $type = Request::param('type/d', 1);
        if (!$param) {
            $param = Request::param('query/a');
        }
        $date = $param['time'];
        // 某些渠道对账有可能是按跨日中午时间 2023-01-01 12:00:00 ~ 2023-01-02 11:59:59
        $start_time = Utils::getQueryDateTime($date);
        $end_time = Utils::getQueryDateTime($date, true);
        $tables = BetService::getThirdStatsTable(isset($param['time_zone']) ? $param['time_zone'] : '');
        $default_values = [
            'date' => '',
            'name' => '',
            'tz_num' => 0,
            'tz_price' => 0,
            'bet_valid_amount' => 0,
            'tz_win' => 0,
            'tz_ptsy' => 0,
        ];
        $where = [];
        if (isset($param['game_name']) && $param['game_name'] != '') {
            $gids = GameInfosModel::where([['gi_name', 'like', "%{$param['game_name']}%"]])->column("gi_gid");
            $where[] = ["su_gid", "in", $gids];
        }

        if (isset($param['tc_id']) && $param['tc_id'] != '') {
            $where[] = ["su_tcid", "=", $param['tc_id']];
        }

        if (isset($param['type']) && $param['type'] != '') {
            $type_id = GamesModel::where([['g_type', '=', $param['type']]])->column("g_id");
            $where[] = ["su_gid", "in", $type_id];
        }

        if (!empty($param['username'])) {
            $where_field = is_numeric($param['username']) ? 'id|username|mail|phone|rec_code' : 'username|mail|phone';
            $uid = UserModel::where([[$where_field, '=', $param['username']]])->value("id");
            if (empty($uid)) {
                return $this->error('请输入正确用户信息');
            }
            $where[] = ["su_uid", "=", $uid];
        }

        $field = "su_dt,SUM(su_bet) as tz_price,SUM(su_valid_bet) as bet_valid_amount,COUNT(su_uid) as tz_number,
                SUM(su_betnum) as tz_num,SUM(su_win) as tz_win,SUM(su_syswin) as tz_ptsy";
        $betTime = [];
        $betTime[] = ['su_dt', 'between', [date("Ymd", $start_time), date("Ymd", $end_time)]];
        $groups = ['su_gid', 'su_uid', 'su_tcid'];
        $group = $groups[$type] ?? 'su_gid';
        $groups_sql = [
            'su_gid' => ',(select gi_name from dq_game_infos where gi_gid=su_gid) as name ,(select tc_name from dq_third_configs where tc_id=su_tcid) as tc_name',
            'su_uid' => ',(select rec_code from dq_user where id=su_uid) as name',
            'su_tcid' => ',(select tc_name from dq_third_configs where tc_id=su_tcid) as name',
        ];
        $field .= $groups_sql[$group];
        if (isset($param['export']) && $param['export'] == 1) {
            return $this->exportListGameDetail($where, $betTime, $tables['stats_global'], $field, $group);
        }
        $CommomRedis = new CommomRedis();
        $redisKey = "report_detail_" . md5(json_encode($param) . $type);
        if ($CommomRedis->has($redisKey)) {
            $arr = $CommomRedis->get($redisKey);
        } else {
            $lists = Db::table($tables['stats_global'])->field($field)->where($where)->where($betTime)->group($group)->order('su_dt DESC')->select();
            if (empty($lists)) {
                $rsps['data'] = [];
                $rsps['total'] = 0;
                $rsps['code'] = 1;
                return Respons::run($rsps);
            }
            $default_values['date'] = lang('总计');
            $total_values = $default_values;
            $arr = [];
            foreach ($lists as $key => $v) {
                $key = date("Y-m-d", strtotime($v['su_dt']));
                $v['date'] = $key;
                foreach ($v as $k => $va) {
                    if (isset($total_values[$k]) && $k != 'name' && $k != 'date') {
                        $total_values[$k] += floatval($v[$k]);
                        $total_values[$k] = sprintf4f($total_values[$k]);
                    }
                }
                $arr[] = $v;
            }
            $arr[] = $total_values;
        }
        $rsps['data'] = $arr;
        $rsps['total'] = 0;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    public function lists()
    {
        $param = Request::param();
        $game_type = GameTypeModel::column("name", "id");
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where = [
            ['a.g_is_del', '=', 0]
        ];
        if (!empty($param['game_name'])) {
            $where[] = ["i.gi_name|a.g_tag|a.g_code", "like", "%" . $param['game_name'] . "%"];
        }

        if (!empty($param['game_type']) && $param['game_type'] <> "") {
            $where[] = ["a.g_type", "=", $param['game_type']];
        }

        if (!empty($param['thirds']) && $param['thirds'] <> "") {
            $where[] = ["a.g_tcid", "=", $param['thirds']];
        }

        if (isset($param['hot']) && $param['hot'] <> "") {
            $where[] = ["a.g_hot", "=", $param['hot']];
        }
        if (isset($param['new']) && $param['new'] <> "") {
            $where[] = ["a.g_is_new", "=", $param['new']];
        }
        if (isset($param['top']) && $param['top'] <> "") {
            $where[] = ["a.g_is_optimum", "=", $param['top']];
        }
        if (isset($param['is_self']) && $param['is_self'] <> "") {
            $where[] = ["a.g_is_self", "=", $param['is_self']];
        }

        if (isset($param['sports']) && $param['sports'] <> "") {
            $where[] = ["a.g_sports", "=", $param['sports']];
        }
        if (isset($param['slot']) && $param['slot'] <> "") {
            $where[] = ["a.g_slot", "=", $param['slot']];
        }

        if (!empty($param['id']) && $param['id'] <> "") {
            $where[] = ["a.g_id", "=", $param['id']];
        }
        if (isset($param['status']) && $param['status'] <> "") {
            $where[] = ["a.g_state", "=", $param['status']];
        }
        if (isset($param['show_status']) && $param['show_status'] !== "") {
            $where[] = ["a.g_show", "=", $param['show_status']];
        }
        if (isset($param['order']) && $param['order'] != '') {
            $order = 'a.' . $param['field'] . ' ' . $param['order'];
        } else {
            $order = 'a.g_id desc';
        }
//        $where[] = ['t.tc_status', '=', 1];
        if (isset($param['select_all_user']) && $param['select_all_user'] == 1) {
            //选择所有用户
            $arr = Db::name('games')->alias('a')->join('game_infos i', 'a.g_id = i.gi_gid')->join('third_configs t', 'a.g_tcid = t.tc_id')
                ->where($where)->column('gi_gid');
//            if( count($arr) > 2000 ){
//                return Respons::run(0, [], '选择的游戏数量过多，请缩小范围');
//            }
            return Respons::run(1, $arr, 'success');
        }

        $rsps['count'] = Db::name('games')->alias('a')->join('game_infos i', 'a.g_id = i.gi_gid')->join('third_configs t', 'a.g_tcid = t.tc_id')
            ->where($where)
            ->count();
        $name_web_list = Db::name('third_configs_web')->column('tc_name','tc_id');
        $lists = Db::name('games')->alias('a')->join('game_infos i', 'a.g_id = i.gi_gid')->join('third_configs t', 'a.g_tcid = t.tc_id')
            ->where($where)
            ->limit((($page - 1) * $limit), $limit)
            ->field('a.*,i.*,t.tc_name')
            ->order($order)
            ->select();
        if ($lists) {
            $lists = $lists->toArray();
            foreach ($lists as &$value) {
                $value['name_web'] = isset($name_web_list[$value['g_web_tcid']]) ? $name_web_list[$value['g_web_tcid']] : $value['g_web_tcid'];
                $value['type_name'] = isset($game_type[$value['g_type']]) ? $game_type[$value['g_type']] : $value['g_type'];
                $value['g_icon'] = toMedia($value['g_icon']);
                $value['gi_headimg'] = toMedia($value['gi_headimg']);
            }
        }
        $rsps['data'] = $lists;
        $rsps['code'] = 1;
        $thirds = Db::name('third_configs')->select();
        $rsps['thirds'] = $thirds;
        $rsps['thirds_web'] = Db::name('third_configs_web')->select();
        $rsps['game_type'] = $game_type;
        return Respons::run($rsps);
    }

    /**
     * 第三方列表
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function third()
    {
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where = [];

        if (isset($param['keyword']) && $param['keyword'] != "") {
            $where[] = ["tc_name|tc_code", "=", $param['keyword']];
        }
        if (isset($param['state']) && $param['state'] != '') {
            $where[] = ["tc_status", "=", ($param['state'] == 1 ? 1 : 0)];
        }

        $rsps['count'] = Db::name('third_configs')
            ->where($where)
            ->count();

        $lists = Db::name('third_configs')
            ->where($where)
            ->limit((($page - 1) * $limit), $limit)
            ->order("tc_status desc, tc_id DESC")
            ->select()->toArray();
        foreach ($lists as $k => $v) {
            $cfgs = Games::getConfigs(null, $v['tc_id']);
            $isHasRtp = 0;
            if (isset($cfgs['apis']['GETONEUSERRTP']) || isset($cfgs['apis']['SETUSERRTP'])) {
                $isHasRtp = 1;
            }
            $lists[$k]['is_has_rtp'] = $isHasRtp;
        }

        $rsps['data'] = $lists;
        $nation = NationModel::where(['state' => 1])->column('name');
        $rsps['code'] = 1;
        $rsps['nation'] = $nation;
        return Respons::run($rsps);
    }

    /**
     * 前端三方平台列表
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function third_web()
    {
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $where = [];

        if (isset($param['keyword']) && $param['keyword'] != "") {
            $where[] = ["tc_name", "=", $param['keyword']];
        }
        if (isset($param['state']) && $param['state'] != '') {
            $where[] = ["tc_status", "=", ($param['state'] == 1 ? 1 : 0)];
        }

        $rsps['count'] = Db::name('third_configs')
            ->where($where)
            ->count();

        $lists = Db::name('third_configs_web')
            ->where($where)
            ->limit((($page - 1) * $limit), $limit)
            ->order("tc_status desc, sort DESC")
            ->select()->toArray();
        $rsps['data'] = $lists;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    /**
     * 游戏报表导出数据
     * @param null $where
     * @param null $whereStr
     * @param string $table
     * @param string $field
     * @return \think\response\Json
     */
    public function exportListGame($where = null, $whereStr = null, $table = '', $field = '')
    {

        $params = Request::param();
        $params['where'] = $where;
        $params['whereStr'] = $whereStr;
        $params['field'] = $field;
        $params['table'] = $table;
        $params['positionField'] = 'a.su_dt';
        $params['positionOrder'] = 'desc';
        $params['muid'] = $this->uid;
        $rlt = ReportExport::export(new rptParams('游戏报表',
            [
                new rptCol('su_dt', '时间日期', 'app\common\service\ReportDownloadService::getDateStr'),
//                'su_dt:时间日期',
                'tz_price:投注总额',
                'bet_valid_amount:有效投注总额',
                'tz_num:投注笔数',
                'tz_win:派彩金额',
                new rptCol('tz_win', '平台盈亏', 'app\manage\factory\GameFactory::countPlatformWin'),
            ],
            __CLASS__, 'exportListGameList',
            $params,
            ['su_dt', 'tz_num'],//导出表名称使用的字段信息
            'a.su_dt', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    //计算平台盈亏
    public static function countPlatformWin($value, $row)
    {
        return round($row['tz_price'] - $row['tz_win'], 4);
    }

    /**
     * 获取行数据
     * @param $params
     * @return Db
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function exportListGameList($params)
    {
        return Db::table($params['table'])->alias('a')->where($params['where'])->where($params['whereStr'])
            ->field($params['field'])->group('su_dt');
    }

    /**
     * 游戏报表导出数据
     * @param null $where
     * @param null $whereStr
     * @param string $table
     * @param string $field
     * @param string $group
     * @return \think\response\Json
     */
    public function exportListGameDetail($where = null, $whereStr = null, $table = '', $field = '', $group = '')
    {

        $params = Request::param();
        $params['where'] = $where;
        $params['whereStr'] = $whereStr;
        $params['field'] = $field;
        $params['table'] = $table;
        $params['group'] = $group;
        $params['positionField'] = 'a.su_dt';
        $params['positionOrder'] = 'desc';
        $params['muid'] = $this->uid;
        $rlt = ReportExport::export(new rptParams('游戏报表详情',
            [
//                new rptCol(rptCol::BuildRowData, '时间','app/manage/factory/GameFactory::dateGetRowData'),
                new rptCol('su_dt', '时间', 'app\common\service\ReportDownloadService::getDateStr'),
                'name:名称/邀请码',
                'tz_price:投注总额',
                'bet_valid_amount:有效投注总额',
                'tz_num:投注笔数',
                'tz_win:派彩金额',
                'tz_ptsy:平台盈亏'
            ],
            __CLASS__, 'exportListGameListDetail',
            $params,
            ['su_dt', 'tz_num'],//导出表名称使用的字段信息
            'a.su_dt', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    /**
     * 自定义列转换方法演示（用于datarow模式下获取一行数据）
     * @param $value
     * @param $datarow
     * @param $transparams
     * @return null
     */
    public static function dateGetRowData($value, $datarow, $transparams)
    {
        //value固定是等于rptCol::BuildRowData的
        $key = date("Y-m-d", strtotime($datarow['su_dt']));
        //获取一行数据
        $datarow['date'] = $key;
        return null; //固定返回null

    }

    /**
     * 获取行数据
     * @param $params
     * @return Db
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function exportListGameListDetail($params)
    {
        return Db::table($params['table'])->alias('a')->where($params['where'])->where($params['whereStr'])
            ->field($params['field'])->group($params['group']);
//        $lists =  Db::table($params['table'])->alias('a')->where($params['where'])->where($params['whereStr'])
//            ->field($params['field'])->group($params['group'])->order('su_dt DESC')->select()->toArray();
//        if ($params['page']==0){
//            return count($lists);
//        }else{
//            return array_slice($lists,($params['page']-1)*$params['pagesize'],$params['pagesize']);
//        }
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
        $params['positionOrder'] = 'asc';
        $params['muid'] = $this->uid;
        $rlt = ReportExport::export(new rptParams('用户日报表',
            [
                new rptCol('su_uid', 'UID'),
                'mail:游戏用户',
                'phone:手机号',
                'su_dt:日期',
                'su_betnum:投注数量',
                'su_bet:投注金額',
                'su_losenum:输次数',
                'su_lose:输金额',
                'su_winnum:赢次数',
                'su_win:赢金额',
                'su_drawnum:平次数',
                'su_draw:平金额',
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['rec_id2', 'su_uid'],//导出表名称使用的字段信息
            'a.su_dt', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethod($param)
    {
        $tables = BetService::getThirdStatsTable();

        return Db::name($tables['_stats_users'])->alias('a')->where($param['where'])->where($param['whereStr'])
            ->join('user b', 'b.id=a.su_uid')
            ->field('a.*,b.username,b.mail,b.phone');
    }

    /**
     * 导出数据
     * @param null $where
     * @param null $whereStr
     * @return \think\response\Json
     */
    public function exportListBack($where = null, $whereStr = null)
    {

        $params = Request::param();
        $params['where'] = $where;
        $params['whereStr'] = $whereStr;
        $params['positionField'] = 'a.sb_id';
        $params['positionOrder'] = 'asc';
        $params['muid'] = $this->uid;
        $payedArr = [
            0 => '无效',
            1 => '未派发',
            2 => '已派发',
        ];
        $rlt = ReportExport::export(new rptParams('反水数据',
            [
                new rptCol('sb_uid', 'UID'),
                'mail:游戏用户',
                'phone:手机号',
                'sb_dt:日期',
                new rptCol('sb_viplvl', '当前VIP', 'app\common\service\ReportDownloadService::getVips'),
                new rptCol('sb_gtid', '游戏分类', 'app\common\service\ReportDownloadService::getGameType'),
                'sb_backbase:有效投注',
                'sb_backrate:返水比例',
                'sb_backmoney:应返金额',
                new rptCol('sb_statstime', '统计时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
                new rptCol('sb_payed', '返水状态', rptCol::transType_kvtrans, $payedArr),
                new rptCol('sb_paytime', '返水时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
            ],
            __CLASS__, 'exportListMethodBack',
            $params,
            ['sb_uid', 'sb_gtid'],//导出表名称使用的字段信息
            'a.sb_id', 'desc'
        ));
        if ($rlt) {
            return Respons::run(1);
        } else {
            return Respons::run(0, [], '创建导出任务失败');
        }
    }

    public static function exportListMethodBack($param)
    {
        return Db::name('third_stats_back')->alias('a')->where($param['where'])->where($param['whereStr'])
            ->join('user b', 'b.id=a.sb_uid')
            ->field('a.*,b.username,b.mail,b.phone');
    }

    public function getRtpList()
    {
        $param = Request::param();
        $page = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $condition = [];
        $condition[] = ['ut_tcid', '=', $param['tcid']];
        if (isset($param['username']) && $param['username'] !== '') {
            $condition[] = ['u.id|u.username|u.phone|u.mail', '=', $param['username']];
        }
        if (isset($param['rtp']) && $param['rtp'] !== '') {
            if (strpos($param['rtp'], '-')) {
                //判断是否范围筛选
                $rtpArr = explode('-', $param['rtp']);
                $condition[] = ['ut.ut_rtp', 'between', [$rtpArr[0], $rtpArr[1]]];
            } else {
                $condition[] = ['ut.ut_rtp', '=', $param['rtp']];
            }
        }

        if (isset($param['order']) && $param['order'] != '') {
            $order = 'ut.' . $param['field'] . ' ' . $param['order'];
        } else {
            $order = 'ut.ut_uid desc';
        }

        $lists = UserThirds::alias('ut')
            ->join('user u', 'u.id=ut.ut_uid')
            ->where($condition)
            ->field('u.id,u.username,u.phone,u.mail,u.login_ip_count,ut_rtp')
            ->page($page, $limit)
            ->order($order)
            ->select();

        foreach ($lists as &$v) {
            $rtp = $this->getRtpByTcidAndUid($param['tcid'], $v['id']);
            $v->ut_rtp = $rtp;
        }
        $total = UserThirds::alias('ut')
            ->join('user u', 'u.id=ut.ut_uid')
            ->where($condition)
            ->count();

        $rsps['total'] = $total;
        $rsps['data'] = $lists;
        $rsps['code'] = 1;
        return Respons::run($rsps);
    }

    //根据tcid和用户id获取rtp
    private function getRtpByTcidAndUid($tcid, $uid, $cache = true)
    {
        $CommomRedis = new CommomRedis();
        $rtp = 0;
        $cfgs = Games::getConfigs(null, $tcid);
        if (isset($cfgs['apis']['GETONEUSERRTP'])) {
            //判断缓存中是否存在
            $redisKey = 'get_one_user_rtp_' . $tcid . '_' . $uid;
            if ($CommomRedis->has($redisKey) && $cache) {
                $rtp = $CommomRedis->get($redisKey);
            } else {
                $result = Game::getOneUserRtp($tcid, $uid);
                if ($result !== false) {
                    $rtp = $result;
                    //将这个用户rtp存入表中
                    UserThirds::where('ut_uid', '=', $uid)
                        ->where('ut_tcid', '=', $tcid)
                        ->where('ut_rtp', '<>', $rtp)
                        ->update(['ut_rtp' => $rtp]);
                    //设置缓存
                    $CommomRedis = new CommomRedis();
                    $redisKey = 'get_one_user_rtp_' . $tcid . '_' . $uid;
                    $CommomRedis->set($redisKey, $rtp, 3600 * rand(24, 240));
                }
            }
        }
        return $rtp;
    }

    //RTP编辑
    public function rtpEdit()
    {
        $param = Request::param();
        $userThirdsModel = new UserThirds();
        if (isset($param['edit'])) {
            $info = $param['info'];
            $detail = UserThirds::where('ut_tcid', '=', $info['tcid'])->where('ut_uid', '=', $info['userIds'])->find();
            if (!$detail) {
                return Respons::run(0, [], '数据不存在');
            }
            $oldData = $detail->toArray();
            //修改钟的userIds只有一个用户
            $successNum = Game::setUserRtp($info['tcid'], $info['userIds'], $info['ut_rtp']);
            if ($successNum === false) {
                return Respons::run(0, [], '该平台不支持设置rtp');
            }
            if (!$successNum) {
                return Respons::run(0, [], '未成功修改，请联系技术支持！');
            }
            $detail->ut_rtp = $info['ut_rtp'];
            $res = $detail->save();
            //设置缓存
            $CommomRedis = new CommomRedis();
            $redisKey = 'get_one_user_rtp_' . $info['tcid'] . '_' . $info['userIds'];
            $CommomRedis->set($redisKey, $info['ut_rtp'], 3600 * rand(24, 240));
            return Respons::run($res ? 1 : 0, '', '', $oldData, $detail->toArray(), $userThirdsModel, lang('RTP修改'));
        }
        if (isset($param['find'])) {
            $condition = [];
            $condition[] = ['ut_tcid', '=', $param['tcid']];
            $condition[] = ['ut_uid', '=', $param['uid']];
            $res = $userThirdsModel->where($condition)->find();
            if ($res) {
                $res->ut_rtp = $this->getRtpByTcidAndUid($param['tcid'], $param['uid'], false);
            }
            return Respons::run(1, $res);
        }

    }

    //RTP添加
    public function rtpAdd()
    {
        $param = Request::param();
        $info = $param['info'];
        $cfgs = Games::getConfigs(null, $info['tcid']);
        if (!isset($cfgs['apis']['SETUSERRTP'])) {
            return Respons::run(0, [], '该平台不支持设置rtp');
        }
        if (!isset($info['userIds']) || !$info['userIds']) {
            return Respons::run(0, [], '请选择用户');
        }
        $userIdsArr = explode(',', $info['userIds']);
        if (count($userIdsArr) > 10) {
            return Respons::run(0, [], '最多可批量设置10个');
        }
        $successNum = Game::setUserRtp($info['tcid'], $info['userIds'], $info['ut_rtp']);
        if ($successNum === false) {
            return Respons::run(0, [], '该平台不支持设置rtp');
        }
        //清除用户对应获取rtp的缓存
        $CommomRedis = new CommomRedis();
        foreach ($userIdsArr as $uid) {
            $redisKey = 'get_one_user_rtp_' . $info['tcid'] . '_' . $uid;
            if ($successNum == count($userIdsArr)) {
                //成功数量等于设置数量则修改数据库并设置缓存
                UserThirds::where('ut_tcid', '=', $info['tcid'])->where('ut_uid', '=', $uid)->update([
                    'ut_rtp' => $info['ut_rtp']
                ]);
                $CommomRedis->set($redisKey, $info['ut_rtp'], 3600 * rand(24, 240));
            } else {
                $CommomRedis->delete($redisKey);
            }
        }
        return Respons::run(1, '', '操作成功', [], $info, new UserThirds(), lang('RTP添加'));
    }

}
