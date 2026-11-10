<?php

namespace app\api\controller;
use app\common\lib\Util;
use Carbon\Carbon;
use think\facade\Db;
use think\Request;
use app\common\model\AdsChannelModel;
class Scaleo {

    public function index(Request $request) {
        $api_key = $request->param('api-key');
        if (empty($api_key)) {
            return $this->error("api-key invalid");
        }
        $channel = AdsChannelModel::where('ac_token', $api_key)->field('ac_id')->find();
        if (empty($channel)) {
            return $this->error("api-key invalid");
        }
        $ac_id = $channel['ac_id'];
        $date_start = $request->param('date_start');
        if (empty($date_start)) {
            return $this->error("date_start invalid");
        }
        $date_end = $request->param('date_end');
        if (empty($date_end)) {
            return $this->error("date_end invalid");
        }
        $type = $request->param('type/s');
        $page = $request->param('page/d', 1);
        $perpage = $request->param('perpage/d', 100);
        if ($perpage > 1000) {
            $perpage = 1000;
        }
        $offset = ($page - 1) * $perpage;
        // 转换时区后在本系统时间
        $s_time = Util::getTimeZoneConversion($date_start, 'UTC');
        $e_time = Util::getTimeZoneConversion($date_end, 'UTC');
        $s_date = Carbon::createFromTimestamp($s_time)->format("Y-m-d H:i:s");
        $e_date = Carbon::createFromTimestamp($e_time)->format("Y-m-d H:i:s");
//        $offset = $local_date->getTimezone()->getOffset(Carbon::now('UTC'));
//        dump("转换到本系统时间UTC(" . ($offset/3600). "):" . $local_date->format("Y-m-d H:i:s"));
//        $s_time = Util::localTimeToTimezone($local_date->format("Y-m-d H:i:s"), 'UTC');
//        $utc_date = Carbon::parse($s_time);
//        dump("本系统时间转换到UTC:" . $utc_date->format("Y-m-d H:i:s"));

        $data = Db::name('scaleo_report')->alias('sr')
            ->leftJoin('dq_user_extra ue', 'ue.uid=sr.uid')
            ->where('ue.ac_id', $ac_id)
            ->where('sr.hour', '>=', $s_date)
            ->where('sr.hour', '<=', $e_date)
            ->when($type, function($query) use($type) {
                $query->whereIn('sr.type', $type);
            })
            ->field('sr.*,ue.click_id')
            ->limit($offset, $perpage)
//            ->fetchSql()
            ->select();

        $events = [];
        foreach ($data as $datum) {
            $events[] = [
                // UTC 时间
                'hour' => Util::localTimeToTimezone($datum['hour'], 'UTC'),
                'type' => $datum['type'],
                'click_id' => $datum['click_id'],
                'player_id' => $datum['uid'],
//                'product' => $datum['game_type'],
                'count' => intval($datum['count']),
                'amount' => floatval($datum['amount'])
            ];
        }
        return $this->success([
            'data' => ['events' => $events]
        ]);
    }

    public function hour_report(Request $request) {
        $date_start = $request->param('date_start');
        if (empty($date_start)) {
            return $this->error("date_start invalid");
        }
        $date_end = $request->param('date_end');
        if (empty($date_end)) {
            return $this->error("date_end invalid");
        }
        $start_time = strtotime($date_start);
        $end_time = strtotime($date_end);
        if ($end_time - $start_time > 86400) {
            return $this->error("时间差不能大于24小时");
        }
        $scaleo_report = Db::name('scaleo_report')
            ->where('hour', '>=', $date_start)
            ->where('hour', '<=', $date_end)
            ->field('sum(amount) as total, type')
            ->group('type')
            ->select();
        $result = [
            'start' => $date_start,
            'end' => $date_end,
            'win' => 0, // 派彩
            'bet' => 0, // 投注
            'bon' => 0, // 优惠
            'ngr' => 0, // GGR = 玩家下注金额 - 玩家赢得的金额
            'ggr' => 0 // NGR (Net Gaming Revenue)：净博彩收入
        ];
        foreach ($scaleo_report as $val) {
            $result[$val['type']] = sprintf4f($val['total'], 2, false, true);
        }

        $result['ggr'] = sprintf4f($result['bet'] - $result['win'], 2, false, true);
        $result['ngr'] = sprintf4f($result['ggr'] - $result['bon'], 2, false, true);
        $result['wdr'] = Db::name('user_withdrawal')->whereIn('status', '3,4')
            ->where('update_time', '>=', $start_time)
            ->where('update_time', '<=', $end_time)
            ->sum('arrival_prices');

        $result['dep'] = Db::name('user_offline')->whereIn('status', '3,4')
            ->where('update_time', '>=', $start_time)
            ->where('update_time', '<=', $end_time)
            ->sum('arrival_price');

        $result['dep_user'] = Db::name('user_offline')->whereIn('status', '3,4')
            ->where('update_time', '>=', $start_time)
            ->where('update_time', '<=', $end_time)
            ->count('DISTINCT uid');

        $result['ftd'] = Db::name('user_offline')->whereIn('status', '3,4')
            ->where('first', 1)
            ->where('update_time', '>=', $start_time)
            ->where('update_time', '<=', $end_time)
            ->sum('arrival_price');

        $result['ftd_user'] = Db::name('user_offline')->whereIn('status', '3,4')
            ->where('first', 1)
            ->where('update_time', '>=', $start_time)
            ->where('update_time', '<=', $end_time)
            ->count('DISTINCT uid');

        $result['reg'] = Db::name('user')
            ->where('add_time', '>=', $start_time)
            ->where('add_time', '<=', $end_time)
            ->count('id');

        return $this->success([
            'data' => $result
        ]);
    }

    public function success(array $data = [], $msg = 'success'): \think\response\Json {
        return $this->result(200, $msg, $data);
    }

    public function error($msg = 'error', $data = []): \think\response\Json {
        return $this->result(0, $msg, $data);
    }

    public function result($code, $msg, $data = []): \think\response\Json {
        $data['code'] = $code;
        $data['message'] = $msg;
        return json($data);
    }
}