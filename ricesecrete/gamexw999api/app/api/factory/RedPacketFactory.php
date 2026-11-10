<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\TokenCheck;
use app\common\lib\Util;
use app\common\service\WalletService;
use app\common\model\{IntegralMxModel,
    RedPacketLogModel,
    RedPacketModel,
    RedPacketTimeModel,
    UserModel,
    UserMsgModel,
    UserOfflineModel
};
use app\common\service\MemberService;
use think\facade\Lang;
use think\facade\Request;

class RedPacketFactory
{

    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
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

    public function list_run()
    {
        $where = [];
        $where[] = ['status', '=', 1];
//        $where[] = ['start_time', '<', time()];#活动未开始的需要显示
        $where[] = ['end_time', '>', time()];
        $list_temp = RedPacketModel::where($where)->order('sort asc')->select()->toArray();
        $now_day_str = lang("当日可参与");//当天可以参与数据
        $now_day = [];//当天可以参与数据 ['rid'=>1,'time'=>'08:20-08:40','expire'=>2]
        $next_day_str = "";
        $next_day = [];//下一场可以参与的列表 ['rid'=>1,'time'=>'08:20-08:40','expire'=>2]
        $now_data = [];
        $day_section = [];
        $week_section = [];
        $day_end_time = 0;
        foreach ($list_temp as $k => $v) {
            $joinNum = RedPacketLogModel::where(['rid' => $v['id'], 'uid' => $this->uid])->count();
            //如果可参与次数已达上线，不显示
            if( $v['total_user_limit'] > 0 && $joinNum >= $v['total_user_limit'] ){
                unset($list_temp[$k]);
                continue;
            }

            if (intval($v['model']) == 1) {
                $now_temp = $this->_getValidityTime($v['id'], 0);
                foreach ($now_temp as $value) {
                    if ($value['expire'] != 1) {
                        $now_day[] = $value;
                    }
                }
            } else if (intval($v['model']) == 2) {
                $week = date('w') == 0 ? 7 : date('w');
                $now_temp = $this->_getValidityTime($v['id'], $week);
                foreach ($now_temp as $value) {
                    if ($value['expire'] != 1) {
                        $now_day[] = $value;
                    }
                }
            }
        }
        $list_temp = array_values($list_temp);
        $day_start_time = "";
        foreach ($list_temp as $k => $v) {
            if ($v['end_time'] > (strtotime(date("Y-m-d")) + (3600 * 24))) {
                if (intval($v['model']) == 1) {
                    $day_start_time = $v['start_time'];
                    $day_end_time = $v['end_time'];
                    $now_temp = $this->_getValidityTime($v['id'], 0);
                    foreach ($now_temp as $value) {
                        $next_day[] = $value;
                    }
                } else if (intval($v['model']) == 2) {
                    $week = date('w') == 0 ? 7 : date('w');
                    $now_temp_arr = [];
                    for ($i = 1; $i <= 7; $i++) {
                        $now_temp_arr[$i] = $this->_getValidityTime($v['id'], $i);
                        if (!empty($now_temp_arr[$i])) {
                            $week_section[] = $i;
                        }
                    }
                    $is_temp = false;
                    for ($i = 1; $i <= 7; $i++) {
                        if (($week + $i) <= 7) {
                            if (!empty($now_temp_arr[$week + $i])) {
                                foreach ($now_temp_arr[$week + $i] as $value) {
                                    $next_day[] = $value;
                                }
                                $is_temp = true;
                                break;
                            }
                        }
                    }
                    if ($is_temp == false) {
                        for ($i = 1; $i <= 7; $i++) {
                            if (!empty($now_temp_arr[$i])) {
                                foreach ($now_temp_arr[$i] as $value) {
                                    $next_day[] = $value;
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }

        $next_day_str = Util::i18n_date2str($day_start_time)."-" . Util::i18n_date2str($day_end_time);
//        if(!empty($day_start_time)&&!empty($day_end_time)){
//            for ($i = $day_start_time; $i <= $day_end_time; $i += (3600 * 24)) {
//                $day_section[] = intval(date("d", $i));
//            }
//        }
//        $next_day_str = "";
//        if (count($day_section) >= 2) {
//            $next_day_str = $day_section[0] . '-' . $day_section[count($day_section) - 1] . lang("号");
////            $next_day_str = lang('{:start_day}-{:end_day}号', ['start_day' => $day_section[0], 'end_day' => $day_section[count($day_section) - 1]]);
//        }
        if (count($week_section) > 0) {
            $weeks = [
                1 => lang('周一'),
                2 => lang('周二'),
                3 => lang('周三'),
                4 => lang('周四'),
                5 => lang('周五'),
                6 => lang('周六'),
                7 => lang('周日'),
            ];
            $temp_str = "";
            foreach ($week_section as $val) {
                $temp_str .= $weeks[$val] . "、";
            }
            if (!empty($temp_str)) {
                $temp_str = mb_substr($temp_str, 0, -1);
            }
            $next_day_str .= "," . lang('每周:') . $temp_str;
        }else{
            $next_day_str .= "," . lang('每日');
        }
        usort($now_day, function ($a, $b) {
            return $a['sort'] - $b['sort'];
        });
        usort($next_day, function ($a, $b) {
            return $a['sort'] - $b['sort'];
        });
        $now_data = $this->_getValidityLately($now_day);
        if(!empty($now_data)){
            $where = [];
            $where[] = ['id', '=', $now_data['rid']];
            $red_temp = RedPacketModel::where($where)->find();
            $now_data['award_limit'] = $red_temp['award_limit'];//奖池金额
            $now_data['max_award'] = $red_temp['max_award'];//最高可得奖励
            $now_data['time_surplus'] = $now_data['sort']-time();//剩余开始时间
            //判断当前用户领取次数 本次可以领取的金额
            $redPacket = RedPacketModel::where(['id' => $now_data['rid']])->find();
            $data_judge = $this->_judgeReceiveNum($redPacket, $this->uid);
            if(empty($this->uid)){
                $now_data['join'] = -1;//-1 未登录
                $now_data['join_msg'] = "未登录";//-1 未登录
            }else{
                $now_data['join'] = $data_judge['code'];//1 可参与 0不可参与
                $now_data['join_msg'] = $data_judge['msg'];//1 可参与 0不可参与
            }
            $now_data['now_time'] = date("Y-m-d H:i:s");
        }
        //获取默认币种
        $currency = Util::i18n_currency_no_amount();
        $data = [
            'now_data' => $now_data,
            'now_day' => $now_day,
            'now_day_str' => $now_day_str,
            'next_day_str' => $next_day_str,
            'next_day' => $next_day,
            'currency' => $currency,
        ];
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //领取红包
    public function receive_run($isLang = '', $isLock = true)
    {
        $param = Request::param();
        //获取领取码对应数据
        if (isset($param['rid']) && $param['rid'] != '') {
            $condition = [];
            $condition[] = ['id', '=', $param['rid']];
            if ($isLang) {
                $lang = Lang::getLangSet();
                $condition[] = ['lang', 'in', [$lang, '']];
            }
            $redPacket = RedPacketModel::where($condition)->find();
            if (!$redPacket) {
                ajax_return('活动结束，领取失败');
            }
            //锁
            $ApiRedis = new ApiRedis();
            if (!$ApiRedis->lock($this->uid, 1) && $isLock) {
                ajax_return("请勿频繁访问", 0);
            }
            //验证实名认证
            if( !MemberService::judgeUserIsCanReceiveDiscount($this->uid) ){
                ajax_return('请先实名后再参与');
            }
            //获取用户数据
            $userDetail = UserModel::uinfo($this->uid);

            //双钱包判断当前用户是否可以重复领取优惠
            if( !WalletService::judgeUserIsRePeatReceive(0, $userDetail) ){
                ajax_return('你的账户还存在彩金，请使用完后再来领取');
            }

            //判断用户等级
            if ($redPacket->level_id > 0 && $redPacket->level_id != $userDetail->level_id) {
                ajax_return('您当前的VIP等级不足，无法领取');
            }
            //判断流水倍数
            if ($redPacket->receive_savings > 0 && $redPacket->receive_savings > MemberService::getUserFinishWater($this->uid)) {
                ajax_return('您当前的流水不足，无法领取');
            }
            //判断流水倍数
            if ($redPacket->savings > 0 && ($redPacket->savings * $redPacket->receive_savings) > MemberService::getUserFinishWater($this->uid)) {
                ajax_return('您当前的流水倍数不足，无法领取');
            }
            //判断标签
            if ($redPacket->tag_id > 0) {
                $userTags = MemberService::getUserTagId($this->uid);
                if (!in_array($redPacket->tag_id, $userTags)) {
                    ajax_return('当前账号标签不满足领取条件');
                }
            }
            //判断代理线
            if ($redPacket->agent_id > 0) {
                $tree = explode("/", $userDetail->tree);
                if (!isset($tree[1]) || $tree[1] != $redPacket->agent_id) {
                    ajax_return('当前账号代理线条件不满足领取条件');
                }
            }
            //判断是否完成
            if (!$this->_judgeConditionIsFinish($this->uid, $redPacket)) {
                ajax_return('未满足存款条件');
            }
            //判断当前用户领取次数 本次可以领取的金额
            $data_judge = $this->_judgeReceiveNum($redPacket, $this->uid);
            if ($data_judge['code'] != 1) {
                ajax_return($data_judge['msg']);
            }
            $award_value = $data_judge['award_value'];
            if ($award_value <= 0) {
                ajax_return('红包雨活动已领取');
            }
            $joinNum = RedPacketLogModel::where(['rid' => $redPacket->id, 'uid' => $this->uid])->count();
            //如果可参与次数已达上线，不显示
            if( $redPacket->total_user_limit > 0 && $joinNum >= $redPacket->total_user_limit ){
                ajax_return('红包雨活动已领取');
            }

            $ip = Util::get_ip();
            $ipLimitKey = 'red_packet_ip_limit_' . $ip;
            $ipLimit = $ApiRedis->get($ipLimitKey) ?: 0;
            //判断当前IP是否领取上限
            if( $redPacket->ip_limit > 0 && $ipLimit >= $redPacket->ip_limit ){
                ajax_return("当前IP领取次数已达上限", 0);
            }
            //获取时间设置明天凌晨到现在秒数，多减五秒防止请求时间过长
            $ApiRedis->set($ipLimitKey, $ipLimit+1, strtotime('tomorrow') - time() - 5);


            $param = Request::param();
            $redPacketLogModel = new RedPacketLogModel();
            $award_type = 1;//1余额  2积分
            $in_data = [
                'rid' => $param['rid'],
                'tid' => $data_judge['tid'],
                'validity_time' => $data_judge['time'],
                'uid' => $this->uid,
                'award_value' => $award_value,
                'award_type' => $award_type,
                'day' => date("Y-m-d"),
                'week' => date("Y-W"),
                'month' => date("Y-m"),
                'create_time' => time(),
                'update_time' => time(),
            ];
            $gid = $redPacketLogModel->insertGetId($in_data);
            if ($gid) {
                $return_data = $this->_sendReward($this->uid, $award_value, $award_type, $redPacket['savings'], $redPacket['id']);
                if (empty($return_data)) {
                    ajax_return("红包领取失败，请联系客服", 0);
                }
                $rsps['data'] = $award_value;
                $rsps['msg'] = '领取成功';
                $rsps['code'] = 1;
                return $rsps;
            } else {
                ajax_return('领取失败');
            }
        }
        ajax_return('活动不存在');
    }

    /**
     * 发放奖励
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
        $title = lang('红包雨活动奖励');
        $currency = Util::i18n_currency_no_amount();
        $return_data = [];
        switch (intval($award_type)) {
            case 1:
                //余额奖励
                UserModel::cz_balance($uid, $award_value, "+", true, "红包雨活动奖励", 20, $savings, 1, $recordId, true, 101);
                $msgContent = '红包雨活动奖励[{:price}], 红包雨活动奖励已到账,奖励金额:{:price} {:currency}';
                $msgData = ['price' => $award_value, 'currency' => $currency];
                UserMsgModel::add_msg($uid, $title, $msgContent, 25, $recordId, $msgData, $lang);
                $return_data = [
                    'award_value' => $award_value,
                    'award_type' => $award_type,
                ];
                break;
            case 2:
                //积分奖励
                IntegralMxModel::cz_mx($uid, 1, $award_value, '红包雨活动奖励');
                $msgContent = '红包雨活动奖励[{:price}], 红包雨活动奖励已到账,奖励积分: {:integral}';
                $msgData = ['integral' => $award_value];
                UserMsgModel::add_msg($uid, $title, $msgContent, 25, $recordId, $msgData, $lang);
                $return_data = [
                    'award_value' => $award_value,
                    'award_type' => $award_type,
                ];
                break;
        }
        return $return_data;
    }

    //判断是否满足条件
    private function _judgeConditionIsFinish($uid, $redPacket)
    {
        //获取起止时间
        $startTime = $redPacket['start_time'];
        $endTime = $redPacket['end_time'];
        $isFinish = 0;
        if ($redPacket->condition == 0) {
            $isFinish = 1;
        }
        //根据条件判断是否满足参与条件
        switch ($redPacket->condition) {
            case 1:
                //首充金额
                #指定时间内的首充不需要指定first=1,第一笔交易即为首充
                $condition = [];
                $condition[] = ['add_time', 'between', [$startTime, $endTime]];
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['status', 'in', '3,4'];
                $arrivalPrice = UserOfflineModel::where($condition)->order('add_time asc')->value('arrival_price');
                if ($arrivalPrice >= $redPacket->condition_value) {
                    $isFinish = 1;
                }
                break;
            case 2:
                $startTime = strtotime(date("Y-m-d"));
                $endTime = time();
                //存款金额
                $condition = [];
                $condition[] = ['add_time', 'between', [$startTime, $endTime]];
                $condition[] = ['uid', '=', $uid];
                $condition[] = ['arrival_price', '>=', $redPacket->condition_value];
                $condition[] = ['status', 'in', '3,4'];
                $arrivalPrice = UserOfflineModel::where($condition)->order('add_time desc')->sum('arrival_price');
                if ($arrivalPrice >= $redPacket->condition_value) {
                    $isFinish = 1;
                }
                break;
        }
        return $isFinish;
    }

    /**
     * 领取奖金总额 总领取次数 领取次数
     * 返还奖励金额
     * @param $redPacket
     * @param $uid
     */
    private function _judgeReceiveNum($redPacket, $uid)
    {
        $condition = [];
        if ($redPacket['model'] == 1) {
            //每日
            $day = date("Y-m-d");
            $condition[] = ['day', '=', $day];
            $now_temp = $this->_getValidityTime($redPacket['id'], 0);
        }
        if ($redPacket['model'] == 2) {
            //每周
            $week = date("Y-W");
            $condition[] = ['week', '=', $week];
            $now_temp = $this->_getValidityTime($redPacket['id'], date("w"));
        }
        if(empty($now_temp)){
            return [
                'code' => -2,
                'msg' => '活动结束'
            ];
        }
        $temp_time = [];
        foreach ($now_temp as $value){
            if($value['expire']==2){
                $temp_time = $value;
            }
        }
        if(empty($temp_time)){
            return [
                'code' => -2,
                'msg' => '活动结束'
            ];
        }
        $condition[] = ['rid', '=', $redPacket['id']];
        $condition[] = ['tid', '=', $temp_time['tid']];
        $condition[] = ['validity_time', '=', $temp_time['time']];
        $people_limit = RedPacketLogModel::where($condition)->count();
        if ($people_limit >= $redPacket['people_limit']) {
            return [
                'code' => 0,
                'msg' => '活动结束，人数已满'
            ];
        }
        $award_limit = RedPacketLogModel::where($condition)->sum('award_value');
        if ($award_limit >= $redPacket['award_limit']) {
            return [
                'code' => 0,
                'msg' => '活动结束，金额已满'
            ];
        }
        $condition[] = ['uid', '=', $uid];
        $user_limit = RedPacketLogModel::where($condition)->count();
        if ($user_limit >= $redPacket['user_limit']) {
            return [
                'code' => 0,
                'msg' => '您已领取完'
            ];
        }
        //剩余池金额
        $award_surplus = $redPacket['award_limit'] - $award_limit;
        //1范围  2固定金额  3输赢阶梯随机
        $award_str = $redPacket['award'];
        $award_value = 0;
        $min = 0;//最小值
        $max = 0;//最大值
        switch ($redPacket['award_type']) {
            case 1:
                $award_arr = explode('-', $award_str);
                if (count($award_arr) == 2) {
                    if ($award_arr[0] > $award_arr[1]) {
                        $award_value = $this->_randFloat($award_arr[1], $award_arr[0]);
                        $min = $award_arr[1];
                        $max = $award_arr[0];
                    } else {
                        $award_value = $this->_randFloat($award_arr[0], $award_arr[1]);
                        $min = $award_arr[0];
                        $max = $award_arr[1];
                    }
                } else {
                    $award_value = floatval($award_str);
                    $min = $award_value;
                    $max = $award_value;
                }
                break;
            case 2:
                if (is_numeric($award_str)) {
                    $award_value = $award_str;
                } else {
                    $award_value = floatval($award_str);
                }
                $min = $award_value;
                $max = $award_value;
                break;
            case 3:
                $loss_win = MemberService::getUserLossWin($uid);
                $award_data = $this->_getAwardLadder($loss_win, $award_str);
                $min = $award_data['min'];
                $max = $award_data['max'];
                $award_value = $award_data['award_value'];
                break;
        }
        if ($award_surplus < $min) {
            return [
                'code' => 0,
                'msg' => '红包已经领完'
            ];
        }
        if ($award_surplus < $max) {
            //如果本次随机值大于剩余池金额，则本次随机值等于剩余池金额
            $award_value = $award_surplus;
        }
        return [
            'code' => 1,
            'award_value' => floatval(sprintf('%.2f', $award_value)),
            'tid'=>$temp_time['tid'],
            'time'=>$temp_time['time'],
            'msg'=>'可参与'
        ];
    }

    //获取随机数小数
    private function _randFloat($min, $max)
    {
        $rand = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return floatval(sprintf('%.2f', $rand));
    }

    /**
     * 获取阶梯奖励 奖励区间
     * @param $loss_win
     * @param $award_str
     * @return array
     */
    private function _getAwardLadder($loss_win, $award_str)
    {
        $award_josn = json_decode($award_str, true);
        $award_value = 0;
        $min = 0;
        $max = 0;
        foreach ($award_josn as $value) {
            if ($value['min'] <= $loss_win && $value['max'] >= $loss_win) {
                if ($value['type'] == 1) {
                    //固定奖励
                    $award_value_arr = explode('-', $value['award']);
                    if (count($award_value_arr) == 2) {
                        if ($award_value_arr[0] > $award_value_arr[1]) {
                            $award_value = $this->_randFloat($award_value_arr[1], $award_value_arr[0]);
                            $min = $award_value_arr[1];
                            $max = $award_value_arr[0];
                        } else {
                            $award_value = $this->_randFloat($award_value_arr[0], $award_value_arr[1]);
                            $min = $award_value_arr[0];
                            $max = $award_value_arr[1];
                        }
                    } else {
                        $award_value = floatval($value['award']);
                        $min = $award_value;
                        $max = $award_value;
                    }
                }
                if ($value['type'] == 2) {
                    //百分百奖励
                    $award_value_arr = explode('-', $value['award']);
                    if (count($award_value_arr) == 2) {
                        if ($award_value_arr[0] > $award_value_arr[1]) {
                            $award_ratio = $this->_randFloat($award_value_arr[1], $award_value_arr[0]);
                            $min = ($award_value_arr[1] / 100) * $loss_win;
                            $max = ($award_value_arr[0] / 100) * $loss_win;
                        } else {
                            $award_ratio = $this->_randFloat($award_value_arr[0], $award_value_arr[1]);
                            $min = ($award_value_arr[0] / 100) * $loss_win;
                            $max = ($award_value_arr[1] / 100) * $loss_win;
                        }
                    } else {
                        $award_ratio = floatval($value['award']);
                        $min = ($award_ratio / 100) * $loss_win;
                        $max = ($award_ratio / 100) * $loss_win;
                    }
                    $award_value = ($award_ratio / 100) * $loss_win;
                }
            }
        }
        return [
            'min' => $min,
            'max' => $max,
            'award_value' => $award_value
        ];
    }

    /**
     * 获取活动时间
     * @param $id
     * @param $week
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function _getValidityTime($id, $week = 0)
    {
        $red_packet_time = RedPacketTimeModel::where(['rid' => $id, 'week' => $week, 'status' => 1])->find();
        if (empty($red_packet_time)) {
            return [];
        }
        if (empty($red_packet_time['validity_time'])) {
            return [];
        }
        $validity_time = json_decode($red_packet_time['validity_time'], true);
        return $this->_validityTimeSort($validity_time, $id,$red_packet_time['id']);
    }

    /**
     * 获取活动时间
     * @param $validity_arr
     * @param $rid
     * @param $tid
     * @return array
     */
    private function _validityTimeSort($validity_arr, $rid,$tid)
    {
        $time = date("H:i");
        $validity_time_arr = [];
        foreach ($validity_arr as $key => $value) {
            $expire = $this->_validity_time_rep($value, $time);
            $sec_time_arr = explode('-', $value);
            $sec_start = strtotime($sec_time_arr[0]);
            $sec_end = strtotime($sec_time_arr[1]);
            $validity_time_arr[] = [
                'time' => $value,
                'expire' => $expire,
                'rid' => $rid,
                'tid' => $tid,
                'sort' => $sec_start,
                'end_time' => $sec_end,

            ];
        }
        return $validity_time_arr;
    }

    /**
     * 判断时间区间是否包含在内 或者小于
     * @param $sec_time
     * @param $time
     * @return bool
     */
    private function _validity_time_rep($sec_time, $time)
    {
        $sec_time_arr = explode('-', $sec_time);
        $sec_start = strtotime($sec_time_arr[0]);
        $sec_end = strtotime($sec_time_arr[1]);
        $sec1_start = strtotime($time);
        $is_has = 0;
        if ($sec_end < $sec1_start) {
            //时间区间大于时间
            $is_has = 1;
        }
        if ($sec_start <= $sec1_start && $sec_end >= $sec1_start) {
            //开始时间在时间区间内容区间内
            $is_has = 2;
        }
        return $is_has;
    }

    /**
     * 获取最近的一场活动
     * @param $day_time
     */
    private function _getValidityLately($day_time)
    {
        $day_time_arr = [];
        $time = date("H:i");
        $min = time();
        foreach ($day_time as $value) {
            if($value['expire']==2){
                $day_time_arr = $value;
                break;
            }
            $min_temp = $this->_validity_time_start_time($value['time'], $time);
            if ($min_temp >= 0) {
                if ($min_temp < $min) {
                    $min = $min_temp;
                    $day_time_arr = $value;
                }
            }
        }
        return $day_time_arr;
    }

    /**
     * 判断时间区间是否包含在内 或者小于
     * @param $sec_time
     * @param $time
     * @return bool
     */
    private function _validity_time_start_time($sec_time, $time)
    {
        $sec_time_arr = explode('-', $sec_time);
        $sec_start = strtotime($sec_time_arr[0]);
        $sec1_start = strtotime($time);
        $diff = $sec_start - $sec1_start;
        if ($diff > 0) {
            return $diff;
        }
        return -1;
    }
}

?>