<?php

namespace app\common\service;

use app\api\factory\ReceiveCodeFactory;
use app\api\factory\TaskFactory;
use app\api\redis\ApiRedis;
use app\common\FastGame\Base\Utils;
use app\common\model\GameBetsModel;
use app\common\model\GameBetsTimeModel;
use app\common\redis\redis;
use think\facade\Cache;
use think\facade\Db;

class BetService
{
    /**
     * 更具时区获取表名
     * @param $time_zone +0 +8 到 -8
     * @return array|string[]
     */
    public static function getThirdStatsTable($time_zone = '')
    {
        $ApiRedis=new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $data_zone = [ 'default_zone'=>'+0' ];
        if(!empty($sys['COUNT_DATA_TIMEZONE_LIST'])){
            $data_zone = self::_formatZone($sys['COUNT_DATA_TIMEZONE_LIST']);
        }

        $time_zone = !empty($time_zone) ? $time_zone : $data_zone['default_zone'];
        $zone_suffix = isset($data_zone['zone_arr'][$time_zone]) ? '_' . $data_zone['zone_arr'][$time_zone] : '';
        return [
            'default_zone' => $time_zone,
            'stats_global' => "dq_third_stats_global{$zone_suffix}",
            '_stats_global' => "third_stats_global{$zone_suffix}",
            'stats_users' => "dq_third_stats_users{$zone_suffix}",
            '_stats_users' => "third_stats_users{$zone_suffix}",
        ];
    }

    /**
     * 生成gb_id时间缓存记录 30分钟任务或稍长一点时间也可
     * @return void
     */
    public static function cacheBetIdByTime()
    {
        $step = 5 * 60;
        $cst = redis::limitCache('globalcache', 'cacheBetIdByTime', null, 3600);
        if ($cst) return;
        redis::limitCache('globalcache', 'cacheBetIdByTime', time(), 3600);
        //找到最后一条记录
        $rec = GameBetsTimeModel::order('ts', 'desc')->limit(1)->find();
        if (!$rec) {
            $gb = GameBetsModel::order('gb_id', 'asc')->limit(1)->find();
            if (!$gb) return;
            $ts = floor($gb->gb_intime / $step) * $step;
            if ($ts == $gb->gb_intime) {//如果第一条记录刚好在统计时间点上，则统计时间往前推一段
                $ts -= $step;
            }
            $rec = new GameBetsTimeModel([
                'ts' => $ts,
                'gb_intime_mingbid' => 0,
                'gb_intime_maxgbid' => 0,
                'gb_bettime_mingbid' => 0,
                'gb_bettime_maxgbid' => 0,
                'gb_overtime_mingbid' => 0,
                'gb_overtime_maxgbid' => 0
            ]);
        }
        while ($rec) {
            $rec = self::cacheBetIdByTime_createRecord($rec);
        }
        //生成新的记录直到记录结束或达到限制时间
        redis::limitCache('globalcache', 'cacheBetIdByTime', time(), -1);
    }

    /**
     * @param GameBetsTimeModel $rec
     * @return GameBetsTimeModel|false
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private static function cacheBetIdByTime_createRecord($rec)
    {
        $step = 5 * 60;
        $limit = time() - 3600;//多少时间内的注不缓存，防止结算时间较长的注单ID被漏统计
        $ts = $rec->ts + $step;
        $tse = $ts;
        $tss = $rec->ts + 1;
        if ($ts >= $limit) return false;
        //TODO 下面去掉gb_id条件，因为gb_id不一定连续，hot表中未结算注单到了一定时间会写入主表，导致统计不准确
//        $con = '_maxgbid';
//        $incon = 'gb_intime' . $con;
//        $betcon = 'gb_bettime' . $con;
//        $overcon = 'gb_overtime' . $con;
        Utils::L('cacheBetIdByTime', [$rec->toArray(), $ts, $tss, $tse]);
//        $in = GameBetsModel::where('gb_id', '>', $rec->$incon)->whereBetween('gb_intime', [$tss, $tse])->field(Db::raw("max(gb_id) as maxid,min(gb_id) as minid"))->find();
        $in = GameBetsModel::whereBetween('gb_intime', [$tss, $tse])->field(Db::raw("max(gb_id) as maxid,min(gb_id) as minid"))->find();
        Utils::L('cacheBetIdByTime', [$in->toArray()]);
//        $bet = GameBetsModel::where('gb_id', '>', $rec->$betcon)->whereBetween('gb_bettime', [$tss, $tse])->field(Db::raw("max(gb_id) as maxid,min(gb_id) as minid"))->find();
        $bet = GameBetsModel::whereBetween('gb_bettime', [$tss, $tse])->field(Db::raw("max(gb_id) as maxid,min(gb_id) as minid"))->find();
        Utils::L('cacheBetIdByTime', [$bet->toArray()]);
//        $over = GameBetsModel::where('gb_id', '>', $rec->$overcon)->whereBetween('gb_overtime', [$tss, $tse])->field(Db::raw("max(gb_id) as maxid,min(gb_id) as minid"))->find();
        $over = GameBetsModel::whereBetween('gb_overtime', [$tss, $tse])->field(Db::raw("max(gb_id) as maxid,min(gb_id) as minid"))->find();
        Utils::L('cacheBetIdByTime', [$over->toArray()]);
        $m = new GameBetsTimeModel([
            'ts' => $ts,
            'gb_intime_mingbid' => ($in && $in->minid) ? $in->minid : $rec->gb_intime_mingbid,
            'gb_intime_maxgbid' => ($in && $in->maxid) ? $in->maxid : $rec->gb_intime_maxgbid,
            'gb_bettime_mingbid' => ($bet && $bet->minid) ? $bet->minid : $rec->gb_bettime_mingbid,
            'gb_bettime_maxgbid' => ($bet && $bet->maxid) ? $bet->maxid : $rec->gb_bettime_maxgbid,
            'gb_overtime_mingbid' => ($over && $over->minid) ? $over->minid : $rec->gb_overtime_mingbid,
            'gb_overtime_maxgbid' => ($over && $over->maxid) ? $over->maxid : $rec->gb_overtime_maxgbid
        ]);
        try {
            $flag = $m->gb_intime_mingbid == $rec->gb_intime_mingbid &&
                $m->gb_intime_maxgbid == $rec->gb_intime_maxgbid &&
                $m->gb_bettime_mingbid == $rec->gb_bettime_mingbid &&
                $m->gb_bettime_maxgbid == $rec->gb_bettime_maxgbid &&
                $m->gb_overtime_mingbid == $rec->gb_overtime_mingbid &&
                $m->gb_overtime_maxgbid == $rec->gb_overtime_maxgbid;
            if (!$flag) {//不记录重复数据
                $m->save();
            }
            return $m;
        } catch (\Exception $ex) {
            Utils::L('cacheBetIdByTime', [$ex->getMessage(), $ex->getTraceAsString()]);
            return false;
        }
    }

    /** 根据开始时间和结束时间获取gb_id范围和WHERE SQL
     * @param int|string $ts
     * @param int|string $te
     * @param string $field gb_intime gb_bettime gb_overtime
     * @param string $alias 表别名
     * @return int|null[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getGbidByTime($ts = null, $te = null, $field = "gb_bettime", $alias = '')
    {
        $rlt = [
            'sql' => null,
            'sid' => null,
            'eid' => null
        ];
        $ts && $ts = is_int($ts) ? $ts : strtotime($ts);
        $te && $te = is_int($te) ? $te : strtotime($te);
        if (!$ts && !$te) return $rlt;
        if ($ts !== null && $te !== null) {
            if ($ts > $te) {
                $tmp = $ts;
                $ts = $te;
                $te = $tmp;
            }
        }
        if ($ts !== null) {
            $id = GameBetsTimeModel::where('ts', '<=', $ts)->order('ts', 'desc')->limit(1)->select()->toArray();
            if (count($id) > 0 && !empty($id[0][$field . '_mingbid'])) {
                $rlt['sid'] = $id[0][$field . '_mingbid'];
            }
        }
        if ($te !== null) {
            $id = GameBetsTimeModel::where('ts', '>=', $te)->order('ts', 'asc')->limit(1)->select()->toArray();
            if (count($id) > 0 && !empty($id[0][$field . '_maxgbid'])) {
                $rlt['eid'] = $id[0][$field . '_maxgbid'];
            }
        }
        //处理表别名
        if ($alias) {
            $alias = $alias . '.';
        }
        if ($rlt['sid'] === null && $rlt['eid'] === null) {
            //没有结果返回时间sql
            if ($ts !== null && $te !== null) {
                //有开始和结束时间
                $rlt['sql'] = "{$alias}{$field} between {$ts} and {$te}";
            } elseif ($ts !== null) {
                //只有开始时间
                $rlt['sql'] = "{$alias}{$field}>={$ts}";
            } elseif ($te !== null) {
                //只有结束时间
                $rlt['sql'] = "{$alias}{$field}<={$te}";
            }
        } elseif ($rlt['sid'] !== null && $rlt['eid'] !== null) {
            $rlt['sql'] = "{$alias}gb_id between " . $rlt['sid'] . " and " . $rlt['eid'] . " and {$alias}{$field} between {$ts} and {$te}";
        }else {
            $sql = "1=1";
            if( $rlt['eid'] !== null ){
                $sql .= " and {$alias}gb_id<=" . $rlt['eid'];
            }
            if( $rlt['sid'] !== null ){
                $sql .= " and {$alias}gb_id>=" . $rlt['sid'];
            }
            if( $ts !== null ){
                $sql .= " and {$alias}{$field}>={$ts}";
            }
            if ( $te !== null ){
                $sql .= " and {$alias}{$field}<={$te}";
            }
            //去掉1=1 and
            $sql = str_replace('1=1 and', '', $sql);
            $rlt['sql'] = $sql;
        }
        return $rlt;
    }

    //跟根据注单发送任务
    //注单推送完成任务
    public static function betPush()
    {
        //处理APP登录
        $taskHandleKey = 'task_uid_handle_key';
        $stopNum = env('task_pre_handle_num', 5000);
        $successNum = 0;
        while (count(Cache::store('redis')->lrange($taskHandleKey, 0, -1)) > 0 && $successNum < $stopNum) {
            //取出UID数据
            $uid = Cache::store('redis')->lpop($taskHandleKey);
            TaskFinishNotifyService::handleNotify($uid);
            $successNum++;
        }
        $apiRedis = new ApiRedis();
        //锁，跑完后下次才能跑
        $lockKey = 'bet_push_task_lock';
        if ($apiRedis->has($lockKey)) {
            return true;
        }
        $apiRedis->set($lockKey, 1, 60 * 30);#半小时

        //执行条数
        $limit = env('bet_push_task_limit', 5000);
        //执行时间间隔
        $handleTime = env('task_handle_time', 60);
        //获取开始推送id
        $redisBetStartIdKey = 'bet_push_task_start_id';#开始id
        $redisBetNoUidKey = 'bet_push_task_no_uid';#没有结算id
        $time = time();
        $startBetId = $apiRedis->get($redisBetStartIdKey);
        if (!$startBetId) {
            //没有id的话开始时间取当前时间往前一分钟的
            $initTime = time() - $handleTime;
            $startBetId = BetService::getGbidByTime($initTime, null, 'gb_intime')['sid'] ?: 0;
//            $startBetId = GameBetsModel::where('gb_intime', '>=', $initTime)->order('gb_id asc')->max('gb_id')?:0;
        }
        $newStartBetId = $startBetId;
        //如果还没有开始id直接返回
        if (!$startBetId) {
            $apiRedis->delete($lockKey);
            return true;
        }
        //获取开始id到现在时间的用户id
        $condition = [];
        $condition[] = ['gb_id', '>=', $startBetId];
//        $condition[] = ['gb_intime', '<=', $time];
        $condition[] = ['gb_winlose', 'in', '2,3'];#只查询已结算的
        $timeEndSql = BetService::getGbidByTime(null, $time, 'gb_intime')['sql'];
        $betList = GameBetsModel::where($condition)->where($timeEndSql)->order('gb_id asc')->field('gb_id,gb_uid')->limit($limit)->select();
        $handleUidArr = [];
        foreach ($betList as $v) {
            if (in_array($v->gb_uid, $handleUidArr)) {
                continue;
            }
            //通知每个用户
            TaskFinishNotifyService::handleNotify($v->gb_uid);
            $newStartBetId = $v->gb_id;
            $handleUidArr[] = $v->gb_uid;
        }
        $noGbIdArr = [];
        //处理上一批未结算的注单用户id
        if ($apiRedis->has($redisBetNoUidKey)) {
            $noGbIdArr = json_decode($apiRedis->get($redisBetNoUidKey), true);
            $betNoList = GameBetsModel::where('gb_winlose', 'in', '2,3')
                ->where('gb_id', 'in', array_keys($noGbIdArr))
                ->order('gb_id asc')->field('gb_id,gb_uid')->limit($limit)->select();
            if ($betNoList) {
                foreach ($betNoList as $v) {
                    unset($noGbIdArr[$v->gb_id]);#删除这个用户对应执行任务
                    if (!in_array($v->gb_uid, $handleUidArr)) {
                        TaskFinishNotifyService::handleNotify($v->gb_uid);
                    }
                    $handleUidArr[] = $v->gb_uid;
                }
            } else {
                $noGbIdArr = [];
            }

            //判断过期了则下次不执行
            foreach ($noGbIdArr as $k => $v) {
                if ($v <= time()) {
                    unset($noGbIdArr[$k]);
                }
            }
        }

        //查询新的未结算的注单对应用户
        $condition = [];
        $condition[] = ['gb_id', '>=', $startBetId];
//        $condition[] = ['gb_intime', '<=', $time];
        $condition[] = ['gb_winlose', '=', '1'];#只查询已结算的
        $betNoGbIdList = GameBetsModel::where($condition)->where($timeEndSql)->order('gb_id asc')->limit($limit)->group('gb_uid')->column('gb_id');
        $noGbIdArr2 = [];
        foreach ($betNoGbIdList as $uid) {
            $noGbIdArr2[$uid] = time() + 60 * 60 * 5;#五个小时过期，到时就算未结算也不会继续推送了
        }
        $noGbIdArr = $noGbIdArr + $noGbIdArr2;
        if ($noGbIdArr) {
            $apiRedis->set($redisBetNoUidKey, json_encode($noGbIdArr), 2 * $handleTime);
        } else {
            $apiRedis->delete($redisBetNoUidKey);
        }
        //设置下次执行开始时间
        $apiRedis->set($redisBetStartIdKey, $newStartBetId, 2 * $handleTime);
        $apiRedis->delete($lockKey);#删除任务锁
        return true;
    }


    /**
     * 获取时区对应表后缀
     * @param $str
     * @return array
     */
    public static function _formatZone($str_arr)
    {
//        $str = "DEFAULT|-3:00,MINUS_ONE|-1:00,MINUS_TWO|-2:00,MINUS_THREE|-3:00,MINUS_FOUR|-4:00,MINUS_FIVE|-5:00,MINUS_SIX|-6:00,MINUS_SEVEN|-7:00,MINUS_EIGHT|-8:00,ZERO|+00:00,ONE|+1:00,TWO|+2:00,THREE|+3:00,FOUR|+4:00,FIVE|+5:00,SIX|+6:00,SEVEN|+7:00,EIGHT|+8:00";
//        $str = strtolower($str);
//        $str = str_replace(' ', '', $str);
//        $str_arr = explode(',',$str);
//        $default_arr_temp = explode('|',$str_arr[0]);
        $str_default = $str_arr[0];
        $str_default[0] = strtolower($str_default[0]);
        if($str_default[0]=='default'){
//            $default_zone = substr($str_default[1],0,strlen($str_default[1])-strpos($str_default[1],':')-1);
            $default_zone = $str_default[1];
            unset($str_arr[0]);
        }else{
            $default_zone = "+0";
        }
        $zone_arr = [];
        foreach ($str_arr as $value){
            $value[0] = strtolower($value[0]);
            $key = substr($value[1],0,stripos($value[1],':'));
            if($key=='+00'){
                $key = '+0';
            }
            $zone_arr[$key] = $value[0];
        }
        return ['default_zone'=>$default_zone,'zone_arr'=>$zone_arr];
    }
}