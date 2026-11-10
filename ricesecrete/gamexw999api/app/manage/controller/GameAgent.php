<?php
namespace app\manage\controller;
use app\common\FastGame\Base\JsTaskWater;
use app\common\lib\Respons;
use app\common\model\GameTypeModel;
use app\common\model\UserAdminLogModel;
use app\common\model\UserAgentModel;
use app\common\model\UserModel;
use app\common\redis\CommomRedis;
use app\manage\factory\GameAgentFactory;
use Carbon\Carbon;
use think\facade\Session;
use think\facade\View;
use think\Request;
use think\facade\Config;
use think\facade\Db;
class GameAgent
{	
    protected $middleware = ['ManageCheckLogin',"Language"];
    
    public function report(\think\Request $request){
        $rsps = (new GameAgentFactory($request->token->uid))->report();
        return $rsps;
    }
    
    public function detail_out(Request $request){
        $param = $request->param();
        $sff_m = $param['sff_m'];
        $sff_w = $param['sff_w'];
        $sff_uid = $param['sff_uid'];
        $sff_utype = $param['sff_utype'];
        if(empty($sff_uid) || empty($sff_utype) || empty($sff_m) || empty($sff_w)){
            return Respons::run(0,[],lang('非法访问'));
        }

        $page=$param['page'];
        $limit=$param['limit'];

        $start_d = intval($sff_m."01");
        $end_d = intval($sff_m."31");

        if($sff_utype == 1){ // 外部代理
            $sql = userLevelSql($sff_uid);
        } else {
            $sql = unLevelSql($sff_uid, 1);
        }
        $where = [];
        $whereStr = "a.sffl_uid in (".$sql.")";
        $where[] = ["a.sffl_dt", "=", $sff_w];
//        $where[] = ["a.sffl_dt", "<=", $end_d];
//        $where[] = ["a.sffl_dw", "=", $sff_w];
        if(isset($param['username']) && !empty($param['username'])){
            $w = [];
            if (is_numeric($param['username'])) {
                $w['id|phone'] = $param['username'];
            } else {
                $w['username|mail|phone'] = $param['username'];
            }
            $uid = UserModel::where($w)->value("id");
            $where[] = ["a.sffl_uid", "=", $uid];
        }
        $afield = [
            "sffl_bet"
        ];
        if (isset($param['filter_zreo']) && $param['filter_zreo'] == 1) {
            $where[] = [implode("|", $afield),">", 0];
        }
        $rates=env('FYFY_BILLING_LEVEL_OUTSIDE_RATE','0');
        $rates=explode(',', $rates);
        $sff_user = UserModel::where('id', $sff_uid)->field('id,username,mail,phone,rec_level')->find();
        $rsps['count'] = Db::name('third_stats_fyfylog')->alias('a')->where($where)->where($whereStr)->count();
        $lists = Db::name('third_stats_fyfylog')->alias('a')
            ->join("user b", "a.sffl_uid = b.id")
            ->where($where)->where($whereStr)
            ->limit((($page-1)*$limit),$limit)
            ->field('a.*,b.username,b.mail,b.phone,b.rec_level')
            ->order("sffl_dt desc")
            ->select();
        if (!$lists->isEmpty()) {
            $lists->each(function($item) use($sff_user, $rates) {
                $item['user_level'] = $item['rec_level'] - $sff_user['rec_level'];
                $cur_rate = isset($rates[$item['user_level']-1]) ? ($rates[$item['user_level']-1]) : 0;
                $item['cur_rate'] = ($cur_rate * 100) . '%';
                $item['cur_amount'] = sprintf4f($item['sffl_bet'] * $cur_rate);
                return $item;
            });
        }
        $rsps['data'] = $lists;
        $rsps['code']=1;
        $rsps['sff_user'] = $sff_user;
        return Respons::run($rsps);
    }

    /**
     * 内部代理详情
     * @param Request $request
     */
    public function detail(Request $request){
        $param = $request->param();
        $sff_m = $param['sff_m'];
        $sff_w = $param['sff_w'];
        $sff_uid = $param['sff_uid'];
        $sff_utype = $param['sff_utype'];
        if(empty($sff_uid) || empty($sff_utype) || empty($sff_m) || empty($sff_w)){
            return Respons::run(0,[],lang('非法访问'));
        }

        $page=$param['page'];
        $limit=$param['limit'];
        $year = substr($sff_w, 0, 4);
        $week = substr($sff_w, 4);
        $start_d = date("Ymd", strtotime("{$year}-W{$week}-1"));
        $end_d = date("Ymd", strtotime("{$year}-W{$week}-7"));

        if($sff_utype == 1){
            $sql = userLevelSql($sff_uid, 1, 1, 0, 0);
        } else {
            $sql = unLevelSql($sff_uid, 1);
        }
        $where = [];
        $whereStr = "a.sffl_uid in (".$sql.") AND a.sffl_uid != {$sff_uid}";
//        $where[] = ["a.sffl_dt", "=", $sff_w];
        $where[] = ["a.sffl_dt", ">=", $start_d];
        $where[] = ["a.sffl_dt", "<=", $end_d];
//        $where[] = ["a.sffl_dw", "=", $sff_w];
        if(isset($param['username']) && !empty($param['username'])){
            $w = [];
            if (is_numeric($param['username'])) {
                $w['id|phone'] = $param['username'];
            } else {
                $w['username|mail|phone'] = $param['username'];
            }
            $uid = UserModel::where($w)->value("id");
            $where[] = ["a.sffl_uid", "=", $uid];
        }

        $afield = [
            "sffl_bet", "sffl_betwin", "sffl_betloss", "sffl_ks",
            "sffl_payin", "sffl_payinfee", "sffl_payout", "sffl_payoutfee", "sffl_yh", "sffl_back"
        ];
        if (isset($param['filter_zreo']) && $param['filter_zreo'] == 1) {
            $where[] = [implode("|", $afield),">", 0];
        }
        $rsps = [];
        $rsps['count'] = Db::name('third_stats_fyfylog')->alias('a')->where($where)->where($whereStr)->count();
        $lists = Db::name('third_stats_fyfylog')->alias('a')
//            ->join("user b", "a.sffl_uid = b.id")
            ->where($where)->where($whereStr)
            ->limit((($page-1)*$limit),$limit)
//            ->field('a.*,b.username,b.mail,b.phone')
            ->order("sffl_bet DESC")
            ->select();

        if($lists){
            $lists = $lists->toArray();
        }
        $my_rate = UserAgentModel::where("uid", $sff_uid)->value('rate');
        $users = [];
        $son_rates = [];
        foreach ($lists AS $key => $val) {
            if (!isset($users[$val['sffl_uid']])) {
                $users[$val['sffl_uid']] = UserModel::where('id', $val['sffl_uid'])->field('id,username,mail,phone')->find();
            }
            $lists[$key]['username'] = $users[$val['sffl_uid']]['username'] ?? '';
            $lists[$key]['mail'] = $users[$val['sffl_uid']]['mail'] ?? '';
            $lists[$key]['phone'] = $users[$val['sffl_uid']]['phone'] ?? '';
            $lists[$key]['my_rate'] = $my_rate;
            if (!isset($son_rates[$val['sffl_uid']])) {
                $son_rates[$val['sffl_uid']] = floatval(UserAgentModel::where("uid", $val['sffl_uid'])->value('rate'));
            }
            $son_rate = $son_rates[$val['sffl_uid']];
            $lists[$key]['rate'] = $son_rate;
            $rate = ($my_rate - $son_rate);
            foreach ($afield as $k) {
                $lists[$key][$k] = floatval($val[$k]);
            }
            $lists[$key]['fy_amount'] = sprintf4f($val['sffl_ks'] * $rate);
        }
        $rsps['data'] = $lists;
        $rsps['str'] = $whereStr;
        $rsps['code']=1;
        return Respons::run($rsps);
    }

    public function detail_subout(Request $request){
        $param = $request->param();
        $sff_m = $param['sff_m'];
        $sff_w = $param['sff_w'];
        $sff_uid = $param['sff_uid'];
        $page = $param['page'];
        $limit = $param['limit'];
        if(empty($sff_uid) || empty($sff_m) || empty($sff_w)){
            return Respons::run(0,[],lang('非法访问'));
        }
        $where = [];
        $whereStr = "";
        $where[] = ["a.sfil_proxyid", "=", $sff_uid];
        $where[] = ["a.sfil_dw", "=", $sff_w];
        if(isset($param['username']) && !empty($param['username'])){
            $w = [];
            if (is_numeric($param['username'])) {
                $w['id|phone'] = $param['username'];
            } else {
                $w['username|mail|phone'] = $param['username'];
            }
            $uid = UserModel::where($w)->value("id");
            $where[] = ["a.sfil_downproxyid", "=", $uid];
        }

        $afield = [
            "sfil_basefy"
        ];
        if (isset($param['filter_zreo']) && $param['filter_zreo'] == 1) {
            $where[] = [implode("|", $afield),">", 0];
        }
        $rsps = [];
        $rsps['count'] = Db::name('third_stats_fyfyinslog')->alias('a')->where($where)->count();
        $lists = Db::name('third_stats_fyfyinslog')->alias('a')
            ->where($where)->where($whereStr)
            ->limit((($page-1)*$limit),$limit)
            ->order("sfil_basefy DESC")
            ->select();

        if($lists){
            $lists = $lists->toArray();
        }
        $users = [];
        foreach ($lists AS $key => $val) {
            if (!isset($users[$val['sfil_downproxyid']])) {
                $users[$val['sfil_downproxyid']] = UserModel::where('id', $val['sfil_downproxyid'])->field('id,username,mail,phone')->find();
            }
            $lists[$key]['username'] = $users[$val['sfil_downproxyid']]['username'] ?? '';
            $lists[$key]['mail'] = $users[$val['sfil_downproxyid']]['mail'] ?? '';
            $lists[$key]['phone'] = $users[$val['sfil_downproxyid']]['phone'] ?? '';
            foreach ($afield as $k) {
                $lists[$key][$k] = floatval($val[$k]);
            }
            if (strlen($val['sfil_dw']) == 6) {
                // 获取指定年份的第 N 周的第一天和最后一天
                $year = substr($val['sfil_dw'], 0, 4);
                $week2 = substr($val['sfil_dw'], 4);
                $startDate = Carbon::parse("{$year}-W{$week2}-1")->startOfDay()->format("Y-m-d");
                $endDate = Carbon::parse("{$year}-W{$week2}-7")->endOfDay()->format("d");
                $lists[$key]['sfil_dw_text'] = "{$startDate}～{$endDate}";
            }
            $lists[$key]['sfil_baserate'] = $val['sfil_downproxyid'] > 0 ? ($val['sfil_baserate'] * 100) . "%" : '-';
            $lists[$key]['sfil_realrate'] = ($val['sfil_realrate'] * 100) . "%";
        }
        $rsps['data'] = $lists;
        $rsps['str'] = $whereStr;
        $rsps['code']=1;
        return Respons::run($rsps);

    }

    public function detail_subout2(Request $request){
        $param = $request->param();
        $sff_m = $param['sff_m'];
        $sff_w = $param['sff_w'];
        $sff_uid = $param['sff_uid'];
        $page = $param['page'];
        $limit = $param['limit'];
        if(empty($sff_uid) || empty($sff_m) || empty($sff_w)){
            return Respons::run(0,[],lang('非法访问'));
        }
        $where = [];
        $whereStr = "";
        $where[] = ["a.sfilt_proxyid", "=", $sff_uid];
        $where[] = ["a.sfilt_cycle", "=", $sff_w];
        if(isset($param['username']) && !empty($param['username'])){
            $w = [];
            if (is_numeric($param['username'])) {
                $w['id|phone'] = $param['username'];
            } else {
                $w['username|mail|phone'] = $param['username'];
            }
            $uid = UserModel::where($w)->value("id");
            $where[] = ["a.sfilt_outproxyid", "=", $uid];
        }

        $afield = [
            "sfilt_fy"
        ];
        if (isset($param['filter_zreo']) && $param['filter_zreo'] == 1) {
            $where[] = [implode("|", $afield),">", 0];
        }
        $rsps = [];
        $rsps['count'] = Db::name('third_stats_fyfyinslog_outproxy')->alias('a')->where($where)->count();
        $lists = Db::name('third_stats_fyfyinslog_outproxy')->alias('a')
            ->where($where)->where($whereStr)
            ->limit((($page-1)*$limit),$limit)
            ->order("sfilt_fy DESC")
            ->select();

        if($lists){
            $lists = $lists->toArray();
        }
        $users = [];
        foreach ($lists AS $key => $val) {
            if (!isset($users[$val['sfilt_outproxyid']])) {
                $users[$val['sfilt_outproxyid']] = UserModel::where('id', $val['sfilt_outproxyid'])->field('id,username,mail,phone')->find();
            }
            $lists[$key]['username'] = $users[$val['sfilt_outproxyid']]['username'] ?? '';
            $lists[$key]['mail'] = $users[$val['sfilt_outproxyid']]['mail'] ?? '';
            $lists[$key]['phone'] = $users[$val['sfilt_outproxyid']]['phone'] ?? '';
            foreach ($afield as $k) {
                $lists[$key][$k] = floatval($val[$k]);
            }
            if (strlen($val['sfilt_cycle']) == 6) {
                // 获取指定年份的第 N 周的第一天和最后一天
                $year = substr($val['sfilt_cycle'], 0, 4);
                $week2 = substr($val['sfilt_cycle'], 4);
                $startDate = Carbon::parse("{$year}-W{$week2}-1")->startOfDay()->format("Y-m-d");
                $endDate = Carbon::parse("{$year}-W{$week2}-7")->endOfDay()->format("d");
                $lists[$key]['sfilt_cycle_text'] = "{$startDate}～{$endDate}";
            }
        }
        $rsps['data'] = $lists;
        $rsps['str'] = $whereStr;
        $rsps['code']=1;
        return Respons::run($rsps);

    }
    //拒绝
    public function refused($id){
        $param = request()->param();
        if ($id) {
            $CommomRedis= new CommomRedis();
            if (!$CommomRedis->lock('agent_fyfy_'.$id)) {
                return Respons::run(0,[],lang('请勿频繁访问'));
            }
            
            //先修改状态
            $where=[];
            $where[]=["sff_id","=",$id];
            $where[]=["sff_payed", "=", 1];
            $data= Db::name('third_stats_fyfy')->where($where)->find();
            //判断状态是否未处理
            if(!empty($data)){
                //先修改状态
                $res = Db::name('third_stats_fyfy')->update(['sff_payed' => 0, 'sff_id' => $id, 'sff_paytime' => time()]);
                UserAdminLogModel::add_log($data['sff_uid'],"代理返佣[".$id."]拒绝, 金额: {$data['sff_back']}",Session::get("adminInfo.name"));
                return Respons::run($res ? 1 : 0);
            }
        }
    }
    
    //派送
    public function send($id){
        $param = request()->param();
        if ($id) {
            $CommomRedis= new CommomRedis();
            if (!$CommomRedis->lock('agent_fyfy_'.$id)) {
                return Respons::run(0,[],lang('请勿频繁访问'));
            }
            
            $where=[];
            $where[]=["sff_id","=",$id];
            $where[]=["sff_payed", "=", 1];
            $data = Db::name('third_stats_fyfy')->where($where)->find();
            //判断状态是否未处理
            if(!empty($data)){
                Db::startTrans();
                try {                    
                    //先修改状态
                    $upd['sff_payed'] = 2;
                    $upd['sff_id'] = $id;
                    $upd['sff_paytime'] = time();
                    $res = Db::name('third_stats_fyfy')->update($upd);
                    if($data['sff_fy'] > 0){
                        //修改余额
                        $UserModel = new UserModel();
                        $UserModel->cz_balance($data['sff_uid'], $data['sff_fy'],'+', true, '代理返佣', 6, 0, 1, $id, true, 101);
                    }
                    UserAdminLogModel::add_log($data['sff_uid'],"代理返佣[".$id."]派送, 金额: {$data['sff_back']}",Session::get("adminInfo.name"));
                    // 提交事务
                    Db::commit();
                    return Respons::run($res ? 1 : 0);
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return Respons::run(0,[],$e->getMessage());
                }
            }
        }
    }
    public function batch() {
        $type = request()->param('type/d');
        $id_str = request()->param('id/s');
        if (!in_array($type, [1, 2]) || empty($id_str)) { // 1拒绝 2通过
            return Respons::run(0,[],lang('参数有误'));
        }
        $CommomRedis= new CommomRedis();
        if (!$CommomRedis->lock('game_fyfy_batch')) {
            return Respons::run(0,[],lang('请勿频繁访问'));
        }
        $ids = explode(",", $id_str);
        foreach ($ids as $id) {
            if ($type == 1) {
                $this->refused($id);
            } else {
                $this->send($id);
            }
        }
        return Respons::run(1);
    }
    public function set(\think\Request $request){
        $rsps = (new GameAgentFactory($request->token->uid))->set();
        return $rsps;
    }
    
    //编辑
    public function set_add(Request $request){
        $param = $request->param();
        if (isset($param['add'])) {
            $info=$param['info'];
            $result = Db::name('third_fyfy_lvlconfig')->where('tfc_type', $info['tfc_type'])->where('tfc_amount', $info['tfc_amount'])->find();
            if ($result) {
                return Respons::run(0, [], '请勿添加重复配置');
            } else {
                $res = Db::name('third_fyfy_lvlconfig')->insert($info);
                return Respons::run($res?1:0);
            }
        }
    }

    //编辑
    public function set_edit(Request $request){
        $param = $request->param();
        if (isset($param['edit'])) {
            $info=$param['info'];
            $res = Db::name('third_fyfy_lvlconfig')->update($info);
            return Respons::run($res?1:0);
        }
        if (isset($param['find'])) {
            $res = Db::name('third_fyfy_lvlconfig')->where("tfc_id",$param['id'])->find();
            return Respons::run(1,$res);
        }
    }
}