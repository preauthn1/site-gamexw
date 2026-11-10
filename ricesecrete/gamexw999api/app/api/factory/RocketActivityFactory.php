<?php

namespace app\api\factory;

use app\common\lib\ReportExport;
use app\common\lib\Respons;
use app\common\lib\rptCol;
use app\common\lib\rptParams;
use app\common\lib\TokenCheck;
use app\common\lib\Util;
use app\common\model\IntegralMxModel;
use app\common\model\RocketActivityBoxLogModel;
use app\common\model\RocketActivityFuelLogModel;
use app\common\model\RocketActivityLevelModel;
use app\common\model\RocketActivityModel;
use app\common\model\RocketActivityRecordModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\model\UserOfflineModel;
use app\common\service\AgentService;
use app\common\service\BetService;
use app\common\service\MemberService;
use app\common\service\WalletService;
use think\facade\Db;
use think\facade\Request;

/**
 * 火箭活动数据工厂
 * Class RocketActivityFactory
 * @package app\api\factory
 */
class RocketActivityFactory
{
    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->uid = $uid;
        if (empty($this->uid)) {
            $header = request()->header();
            try {
                $token = TokenCheck::run($header);
                if ($token) {
                    $this->uid = $token->uid;
                }
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * 参与活动--如果已经参与返回活动基本数据
     * @return array
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function join_run()
    {
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $ac_condition[] = ['status', '=', 1];
        $ac_condition[] = ['start_time', '<', time()];
        $ac_condition[] = ['end_time', '>', time()];
        $ac_condition[] = ['lang', '=', $lang];
        $detail = RocketActivityModel::where($ac_condition)->find();
        if (empty($detail)) {
            ajax_return("活动关闭", 0);
        }
        $activity_level_max = RocketActivityLevelModel::where(['rid' => $detail['id']])->order('level DESC')->find();
        if (empty($this->uid)) {
            $activity_level = RocketActivityLevelModel::where(['rid' => $detail['id']])->order('level ASC')->select();
            if (empty($activity_level)) {
                ajax_return("活动数据有误，请联系客服", 0);
            }
            $activity_level = json_decode(json_encode($activity_level), true);
            $activity_level = array_reverse($activity_level);
            foreach ($activity_level as &$value) {
                //是否已经领取 0已领取  1未领取
                $value['is_get'] = 0;
            }
            $return_data = [
                'rid' => $detail['id'],
                'uid' => 0,
                'now_mileage' => 0,
                'max_mileage' => $activity_level_max['sec_mileage_max'],
                'now_level' => $activity_level[0]['level'],
                'now_get_level' => $activity_level[0]['level'],
                'all_use_fuel' => 0,//总消耗的燃料
                'all_use_money' => 0,//总消耗的（下注）实际金额
                'all_remain_fuel' => 0,//总剩余燃料
                'all_remain_money' => 0,//剩余（下注）实际金额
                'transf_ratio' => 0,//当前转化比例
                'activity_level' => $activity_level,
            ];
            $rsps['data'] = $return_data;
            $rsps['code'] = 1;
            return $rsps;
        }
        $rid = $this->_validate($this->uid, $detail);
        $rec_condition = [
            'rid' => $rid,
            'uid' => $this->uid
        ];
        //获取参与记录 不用语言会
        $record = RocketActivityRecordModel::where($rec_condition)->find();
        $lev_condition = [
            'rid' => $rid
        ];
        if (empty($record)) {
            $level = RocketActivityLevelModel::where($lev_condition)->order('level ASC')->find();
            $record = [
                'rid' => $rid,
                'uid' => $this->uid,
                'now_mileage' => 0,
                'now_level' => $level['level'],
                'now_get_level' => $level['level'],
                'create_time' => time(),
                'update_time' => time(),
            ];
            $res = RocketActivityRecordModel::insertGetId($record);
            if (empty($res)) {
                ajax_return("参与失败，请联系客服", 0);
            }
        } else {
            $lev_condition = [
                'rid' => $rid,
                'level' => $record['now_level']
            ];
            $level = RocketActivityLevelModel::where($lev_condition)->find();
        }
        if (empty($level)) {
            ajax_return("活动数据有误，请联系客服", 0);
        }
        $fuel_sum = $this->_getFuelSum($this->uid, $level['transf_ratio']);
        $level_map = [];
        $level_map[] = ['rid', '=', $detail['id']];
        $activity_level = RocketActivityLevelModel::where($level_map)->order('level DESC')->select();
        foreach ($activity_level as &$value) {
            //是否已经领取 0已领取  1未领取
            $value['is_get'] = $this->_isGetBox($rid, $this->uid, $value['level'], $record['now_mileage']);
        }
        $return_data = [
            'rid' => $rid,
            'uid' => $this->uid,
            'now_mileage' => $record['now_mileage'],
            'max_mileage' => $activity_level_max['sec_mileage_max'],
            'now_level' => empty($record['now_mileage']) ? 0 : $record['now_level'],
            'now_get_level' => empty($record['now_mileage']) ? 0 : $record['now_get_level'],
            'all_use_fuel' => $fuel_sum['all_use_fuel'],//总消耗的燃料
            'all_use_money' => $fuel_sum['all_use_money'],//总消耗的（下注）实际金额
            'all_remain_fuel' => $fuel_sum['all_remain_fuel'],//总剩余燃料
            'all_remain_money' => $fuel_sum['all_remain_money'],//剩余（下注）实际金额
            'transf_ratio' => $level['transf_ratio'],//当前转化比例
            'activity_level' => $activity_level,
        ];
        $rsps['data'] = $return_data;
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 加注n次燃料
     * @return array
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function inject_run()
    {
        $num = $this->param['num'];
        $rid = $this->param['rid'];
        if (empty($rid)) {
            ajax_return("活动数据有误，请联系客服", 0);
        }
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $ac_condition[] = ['id', '=', $rid];
        $ac_condition[] = ['status', '=', 1];
        $ac_condition[] = ['start_time', '<', time()];
        $ac_condition[] = ['end_time', '>', time()];
        $ac_condition[] = ['lang', '=', $lang];
        $detail = RocketActivityModel::where($ac_condition)->find();
        if (empty($detail)) {
            ajax_return("活动关闭", 0);
        }
        $rec_condition = [
            'rid' => $rid,
            'uid' => $this->uid
        ];
        //获取参与记录
        $record = RocketActivityRecordModel::where($rec_condition)->find();
        if (empty($record)) {
            ajax_return("活动数据有误，请联系客服", 0);
        }

        //获取区间参数
        $lev_condition = [
            'level' => $record['now_level']
        ];
        $level = RocketActivityLevelModel::where($lev_condition)->find();
        if (empty($level)) {
            ajax_return("活动数据有误，请联系客服", 0);
        }
        $lev_condition = [
            'rid' => $rid,
        ];
        $level_max = RocketActivityLevelModel::where($lev_condition)->order('level DESC')->find();
        if ($record['now_mileage'] >= $level_max['sec_mileage_max']) {
            ajax_return("本次活动完成", 0);
        }
        $data_temp = $this->_useFuelMil($record, $level, $num, $level_max);
        $level_map = [];
        $level_map[] = ['rid', '=', $detail['id']];
        $activity_level = RocketActivityLevelModel::where($level_map)->order('level DESC')->select();
        foreach ($activity_level as &$value) {
            //是否已经领取 0已领取  1未领取
            $value['is_get'] = $this->_isGetBox($rid, $this->uid, $value['level'], $data_temp['all_mileage']);
        }
        $data_temp['activity_level'] = $activity_level;
        $rsps['data'] = $data_temp;
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 获取活动介绍
     * @return array
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function info_run()
    {
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $ac_condition[] = ['status', '=', 1];
        $ac_condition[] = ['start_time', '<', time()];
        $ac_condition[] = ['end_time', '>', time()];
        $ac_condition[] = ['lang', '=', $lang];
        $detail = RocketActivityModel::where($ac_condition)->find();
        if (empty($detail)) {
            ajax_return("活动关闭", 0);
        }
        $rsps['data'] = $detail['introduce'];
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 开启宝箱
     * @return array
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function open_box_run()
    {
        $rid = isset($this->param['rid']) ? $this->param['rid'] : 0;
        $level = isset($this->param['level']) ? $this->param['level'] : 1;
        if (empty($rid)) {
            ajax_return("活动数据有误，请联系客服", 0);
        }
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $ac_condition[] = ['id', '=', $rid];
        $ac_condition[] = ['status', '=', 1];
        $ac_condition[] = ['start_time', '<', time()];
        $ac_condition[] = ['end_time', '>', time()];
        $ac_condition[] = ['lang', '=', $lang];
        $detail = RocketActivityModel::where($ac_condition)->find();
        if (empty($detail)) {
            ajax_return("活动关闭", 0);
        }
        $rec_condition = [
            'rid' => $rid,
            'uid' => $this->uid
        ];
        //获取参与记录
        $record = RocketActivityRecordModel::where($rec_condition)->find();
        if (empty($record)) {
            ajax_return("活动数据有误，请联系客服", 0);
        }
        //验证实名认证
        if( !MemberService::judgeUserIsCanReceiveDiscount($this->uid) ){
            ajax_return('请先实名后再参与');
        }

        //双钱包判断当前用户是否可以重复领取优惠
        if( !WalletService::judgeUserIsRePeatReceive($this->uid) ){
            ajax_return('你的账户还存在彩金，请使用完后再来领取');
        }
        $lev_condition[] = ['rid', '=', $rid];
        $lev_condition[] = ['level', '=', $level];
        $level = RocketActivityLevelModel::where($lev_condition)->order('level ASC')->find();
        if (empty($level)) {
            ajax_return("宝箱领取失败，请联系客服", 0);
        }
        if (strpos($level['award_value'], '-') > 0) {
            $mil_arr = explode('-', $level['award_value']);
            if (isset($mil_arr[0]) && isset($mil_arr[1])) {
                $level['award_value'] = round(rand($mil_arr[0], $mil_arr[1]), 2);
            } else {
                $level['award_value'] = round(floatval($level['award_value']), 2);
            }
        } else {
            $level['award_value'] = round(floatval($level['award_value']), 2);
        }
        $in_box_log = [
            'rid' => $rid,
            'uid' => $this->uid,
            'type' => 1,
            'level' => $level['level'],
            'savings' => $detail['savings'],
            'award_value' => $level['award_value'],
            'award_type' => $level['award_type'],
            'create_time' => time(),
            'update_time' => time()
        ];
        $map = [
            'rid' => $rid,
            'uid' => $this->uid,
            'type' => 1,
            'level' => $level['level'],
        ];
        $ret = RocketActivityBoxLogModel::where($map)->find();
        if (!empty($ret)) {
            ajax_return("宝箱已领取，请勿重复领取", 0);
        }
        Db::startTrans();
        $ret_id = RocketActivityBoxLogModel::insertGetId($in_box_log);
        if (!empty($ret_id)) {
            $return_data = $this->_sendReward($this->uid, $level['award_value'], $level['award_type'], $detail['savings'], $ret_id);
            if (empty($return_data)) {
                ajax_return("宝箱领取失败，请联系客服", 0);
            }
            $map = [
                'rid' => $rid,
                'uid' => $this->uid
            ];
            RocketActivityRecordModel::where($map)->update(['now_get_level' => $level['level']]);
            Db::commit();
            $return_data['now_level'] = $record['now_level'];
            $return_data['now_get_level'] = $level['level'];
            $data['data'] = $return_data;
            $data['code'] = 1;
            return $data;
        }
        Db::rollback();
        ajax_return("宝箱领取失败，请联系客服", 0);
    }

    /**
     * 获取燃料消耗记录
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function get_fuel_list_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $ac_condition[] = ['lang', '=', $lang];
        $detail = RocketActivityModel::where($ac_condition)->find();
        $offset = ($pagenow - 1) * $limit;
        $where = [];
        $where[] = ["uid", "=", $this->uid];
        if (!empty($detail)) {
            $where[] = ["rid", "=", $detail['id']];
        }
        $data = RocketActivityFuelLogModel::where($where)->order('id DESC')->limit($offset, $limit)->select();
        $data = json_decode(json_encode($data), true);
        //时间显示变成 2024-03-21 格式
        foreach ($data as &$value) {
            $value['create_time'] = date('Y-m-d', strtotime($value['create_time']));
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 获取宝箱记录
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function get_box_list_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $ac_condition[] = ['lang', '=', $lang];
        $detail = RocketActivityModel::where($ac_condition)->find();
        $offset = ($pagenow - 1) * $limit;
        $where = [];
        $where[] = ["uid", "=", $this->uid];
        if (!empty($detail)) {
            $where[] = ["rid", "=", $detail['id']];
        }
        $data = RocketActivityBoxLogModel::where($where)->order('id DESC')->limit($offset, $limit)->select();
        $data = json_decode(json_encode($data), true);
        foreach ($data as &$value) {
            $value['create_time'] = date('Y-m-d', strtotime($value['create_time']));
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    /**
     * 打开宝箱发放奖励
     * @param $uid
     * @param $award_value
     * @param int $award_type
     * @param int $savings
     * @param int $recordId
     * @return array
     * @throws \Exception
     */
    private function _sendReward($uid, $award_value, $award_type = 1, $savings = 0, $recordId = 0)
    {
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        //1余额  2积分
        $title = lang('开启火箭活动宝箱');
        $currency = Util::i18n_currency_no_amount();
        $return_data = [];
        switch (intval($award_type)) {
            case 1:
                //余额奖励
                UserModel::cz_balance($uid, $award_value, "+", true, "火箭活动宝箱奖励", 19, $savings, 1, $recordId, true, 101);
                $msgContent = $title . '. ' . lang('火箭活动奖励已到账,奖励金额') . "{:price} {:currency}";
                $msgData = ['price' => $award_value, 'currency' => $currency];
                UserMsgModel::add_msg($uid, $title, $msgContent, 24, $recordId, $msgData, $lang);
                $return_data = [
                    'award_value' => $award_value,
                    'award_type' => $award_type,
                ];
                break;
            case 2:
                //积分奖励
                IntegralMxModel::cz_mx($uid, 1, $award_value, '火箭活动宝箱奖励');
                $msgContent = $title . '. ' . lang('火箭活动奖励已到账,奖励积分') . "{:integral}";
                $msgData = ['integral' => $award_value];
                UserMsgModel::add_msg($uid, $title, $msgContent, 24, $recordId, $msgData, $lang);
                $return_data = [
                    'award_value' => $award_value,
                    'award_type' => $award_type,
                ];
                break;
        }
        return $return_data;
    }

    /**
     * 加注燃料
     * @param $record
     * @param $level
     * @param $num
     * @param $max_mileage
     * @return array
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function _useFuelMil($record, $level, $num, $max_level)
    {

        $rid = $record['rid'];
        $uid = $record['uid'];
        if (strpos($level['use_fuel_mil'], '-') > 0) {
            $mil_arr = explode('-', $level['use_fuel_mil']);
            if (isset($mil_arr[0]) && isset($mil_arr[1])) {
                $level['use_fuel_mil'] = round(rand($mil_arr[0], $mil_arr[1]), 2);
            } else {
                $level['use_fuel_mil'] = round(floatval($level['use_fuel_mil']), 2);
            }
        } else {
            $level['use_fuel_mil'] = round(floatval($level['use_fuel_mil']), 2);
        }
        if (strpos($level['use_fuel_num'], '-') > 0) {
            $mil_arr = explode('-', $level['use_fuel_num']);
            if (isset($mil_arr[0]) && isset($mil_arr[1])) {
                $level['use_fuel_num'] = round(rand($mil_arr[0], $mil_arr[1]), 2);
            } else {
                $level['use_fuel_num'] = round(floatval($level['use_fuel_num']), 2);
            }
        } else {
            $level['use_fuel_num'] = round(floatval($level['use_fuel_num']), 2);
        }
        //本次增加的里程
        $add_mileage = round(floatval($level['use_fuel_mil'] * $num), 2);
        //消耗总燃料
        $use_fuel = round(floatval($level['use_fuel_num'] * $num), 2);
        //兑换下注前的消耗金额
        $use_money = round(floatval($use_fuel / $level['transf_ratio']), 2);
        //下注金额与燃料转化比例
        $transf_ratio = $level['transf_ratio'];

        $fuel_sum = $this->_getFuelSum($record['uid'], $transf_ratio);
        if ($fuel_sum['all_remain_fuel'] < $use_fuel) {
            ajax_return("燃料不足", 0);
        }
        //最新里程数
        $new_now_mileage = round(floatval($record['now_mileage'] + $add_mileage), 2);
        $now_level = $this->_getMilLevel($rid, $new_now_mileage, $record['now_level']);
        if ($new_now_mileage > $max_level['sec_mileage_max']) {
            $new_now_mileage = $max_level['sec_mileage_max'];
            $now_level = $max_level['level'];
        }
        $up_record = [
            'now_mileage' => $new_now_mileage,
            'now_level' => $now_level,
            'update_time' => time()
        ];
        $condition = [
            'rid' => $rid,
            'uid' => $uid
        ];
        Db::startTrans();
        $ret = RocketActivityRecordModel::where($condition)->update($up_record);
        if ($ret !== false) {
            //添加燃料消耗记录
            $in_fuel_log = [
                'rid' => $rid,
                'uid' => $uid,
                'num' => $num,
                'mileage' => $add_mileage,
                'all_mileage' => $new_now_mileage,
                'use_fuel' => $use_fuel,
                'use_money' => $use_money,
                'transf_ratio' => $transf_ratio,
                'create_time' => time(),
                'update_time' => time()
            ];
            $id = RocketActivityFuelLogModel::insertGetId($in_fuel_log);
            if (empty($id)) {
                Db::rollback();
                ajax_return("加注燃料失败，请联系客服", 0);
            } else {
                Db::commit();
                return [
                    'add_mileage' => $add_mileage,
                    'all_mileage' => $new_now_mileage,
                    'use_fuel' => $use_fuel,
                    'now_level' => empty($new_now_mileage) ? 0 : $now_level,
                    'now_get_level' => $record['now_get_level'],
                ];
            }
        }
        Db::rollback();
        ajax_return("加注燃料失败，请联系客服", 0);
    }

    /**
     * 根据里程获取等级
     * @param $rid
     * @param $mileage
     * @param $level
     * @return false|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function _getMilLevel($rid, $mileage, $level)
    {
        $condition = [];
        $condition[] = ['rid', '=', $rid];
        $condition[] = ['sec_mileage_max', '<=', $mileage];
        $level_data = RocketActivityLevelModel::where($condition)->order('level DESC')->find();
        if (!empty($level_data)) {
            return $level_data['level'];
        } else {
            return $level;
        }
    }

    //验证
    private function _validate($uid, $detail)
    {

        if (!empty($detail['condition'])) {
            $map = [
                'uid' => $uid,
                'status' => 3,
                'first' => $detail['condition']
            ];
            $ret = UserOfflineModel::where($map)->order('id DESC')->find();
            if (empty($ret)) {
                ajax_return("不满足活动条件，请联系客服", 0);
            }
        }
        // 判断是否禁止参与活动
        if (MemberService::tagBan($uid, 'is_activity')) {
            ajax_return("禁止参与活动，请联系客服", 0);
        }
        //标签验证
        if (isset($detail->tag_id) && $detail->tag_id > 0) {
            $user_tags = MemberService::getUserTagId($uid);
            if (!in_array($detail->tag_id, $user_tags)) {
                ajax_return("用户标签条件不符合");
            }
        }
        //限制代理活动
        if (isset($detail->agent_id) && $detail->agent_id > 0) {
            $ret = RocketActivityModel::alias('a')->where('id', $detail->id)->where(AgentService::getAgentWhere('agent_id', true, 'a'))->find();
            if (empty($ret)) {
                ajax_return("未达到活动要求,无法参与", 0);
            }
        }
        return $detail->id;
    }

    //判断是否可以领取宝箱 1可领取 0不可领取
    private function _isGetBox($rid, $uid, $level, $now_mileage)
    {
        $map = [
            'rid' => $rid,
            'uid' => $uid,
            'type' => 1,
            'level' => $level,
        ];
        $ret = RocketActivityBoxLogModel::where($map)->find();
        if (empty($ret)) {
            $map_temp = [
                'rid' => $rid,
                'level' => $level,
            ];
            $level_temp = RocketActivityLevelModel::where($map_temp)->find();
            if ($level_temp['sec_mileage_max'] <= $now_mileage) {
                return 1;
            }
            return 0;
        }
        return 0;
    }

    /**
     * 获取用户的燃料数据
     * @param $uid  用户id
     * @param int $transf_ratio 燃料兑换比例
     * @return array
     */
    private function _getFuelSum($uid, $transf_ratio = 1)
    {
        //下注流水金额
        $tables = BetService::getThirdStatsTable();
//        $tables['_stats_users']
        $su_bet = Db::name($tables['_stats_users'])->where([['su_uid', '=', $uid]])->sum('su_bet'); // 流水
        //实际消耗的下注金额
        $field = "SUM(mileage) as all_mileage,SUM(use_fuel) as all_use_fuel,SUM(use_money) as all_use_money";
        $sum_data = Db::name("rocket_activity_fuel_log")->field($field)->where('uid', $uid)->find();
        $all_remain_money = round(floatval($su_bet - $sum_data['all_use_money']), 2);
        return [
            'all_mileage' => $sum_data['all_mileage'] ? $sum_data['all_mileage'] : 0,//总里程数
            'all_use_fuel' => $sum_data['all_use_fuel'] ? $sum_data['all_use_fuel'] : 0,//总使用燃料
            'all_use_money' => $sum_data['all_use_money'] ? $sum_data['all_use_money'] : 0,//总使用的实际金额
            'all_remain_fuel' => round(floatval($all_remain_money * $transf_ratio), 2),//总剩余燃料
            'all_remain_money' => $all_remain_money,//总剩余燃料兑换前的金额
        ];
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
        $rlt = ReportExport::export(new rptParams('火箭活动记录',
            [
                new rptCol('id', 'ID'),
                'rid:活动ID',
                'uid:UID',
                'now_mileage:当前总里程',
                'now_level:当前高度区间',
                'now_get_level:当前领取的宝箱区间',
                new rptCol('create_time', '参与时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s']),
                new rptCol('update_time', '最后参与时间', rptCol::transType_datetime, ['timezone' => null, 'format' => 'Y-m-d H:i:s'])
            ],
            __CLASS__, 'exportListMethod',
            $params,
            ['rid', 'uid'],//导出表名称使用的字段信息
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
        $RocketActivityRecordModel = new RocketActivityRecordModel();
        return $RocketActivityRecordModel->alias("a")->where($param['where'])->where($param['whereStr'])
            ->field('a.id, a.rid, a.uid,a.now_mileage, a.now_level,a.now_get_level,a.create_time, a.update_time');
    }
}