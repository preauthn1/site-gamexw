<?php

namespace app\common\service;

use app\api\redis\ApiRedis;
use app\common\FastGame\Base\Redis;
use app\common\FastGame\Base\Third;
use app\common\model\GameBetsModel;
use app\common\model\GamesModel;
use app\common\redis\CommomRedis;
use app\model\Games;
use think\facade\Db;
use think\facade\Log;

/**
 * 游戏相关
 */
class GameService
{
    /**
     * 根据UID获取用户游戏相关数据统计结果
     * @param $uid
     * @param $key
     */
    public static function getUserStatByUid($uid, $key = null) {
        return self::statGameBetByGlobal($uid, null, null, $key);
    }

    /**
     * 统计用户游戏数据
     * @param $uid
     * @param $start_time
     * @param $end_time
     * @param $key
     */
    public static function statGameBet($uid, $start_time = null, $end_time = null, $key = null) {
        return self::statGameBetByGlobal($uid, $start_time, $end_time, $key);
    }



    /**
     * 统计用户游戏数据
     * @param $uid
     * @param $start_time
     * @param $end_time
     * @param $key
     */
    public static function statGameBetByGlobal($uid, $start_time = null, $end_time = null, $key = null) {
        $where = [];
        if (is_numeric($uid)) {
            $where[] = ['su_uid', '=', $uid];
        } else if (!is_null($uid)){
            $where[] = ['su_uid', 'IN', $uid];
        }
        if (!is_null($start_time)) {
            $where[] = ["su_dt", ">=", date("Ymd", $start_time)];
        }
        if (!is_null($end_time)) {
            $where[] = ["su_dt", "<=", date("Ymd", $end_time)];
        }
        $field = "count(distinct su_uid) as tz_number, sum(su_betnum) as tz_num, sum(su_bet) as tz_price,".
            "SUM(su_win) as tz_win, ".
            "SUM(su_draw) as bet_draw, ".
            "SUM(su_valid_bet) as bet_valid_amount, ".
            "SUM(su_syswin*-1) as total_win_lost"; // 平台盈亏 * -1 = 用户输赢
        $table = \app\common\service\BetService::getThirdStatsTable();
//        $table['_stats_global'];
        $bets = Db::name($table['_stats_global'])->field($field)->where($where)->find();
        if (empty(array_filter($bets))) {
            $bets = [
                'tz_number' => 0, // 投注人数
                'tz_num' => 0, // 投注订单数
                'tz_price' => 0, // 投注总额
                'tz_win' => 0, // 派彩金额
                'bet_draw' => 0, // 和局金额
                'bet_valid_amount' => 0, // 有效投注金额
                'total_win_lost' => 0,
            ];
        }
        return is_string($key) && isset($bets[$key]) ? $bets[$key] : $bets;
    }


    /**
     * 20240408 作废--
     * 根据时间字段和开始结束时间获取最大和最小注单ID，用于提供查询效率
     * @param $ds 开始时间
     * @param $de 结束时间
     * @param $timefield 时间字段
     * @param $cutex 分表后缀
     * @return array
     */
    public static function getBetsIdRange($ds,$de,$timefield='gb_bettime',$cutex='', $where1 = '', $where2 = ''){
        //处理条件为数组情况
        $where1 = self::whereArrChangeStr($where1);
        $where2 = self::whereArrChangeStr($where2);
        $commonRedis = new CommomRedis();
        $key = 'get_bets_id_min_max_key_'.md5(json_encode([
                $ds, $de, $timefield, $cutex, $where1, $where2
            ]));
        if( $commonRedis->has($key) ){
            return json_decode($commonRedis->get($key), true);
        }
        $sql="select min(gb_id) as min_id,max(gb_id) as max_id  from dq_game_bets{$cutex} where {$timefield} BETWEEN {$ds} and {$de}";
        if( $where1 ){
            $sql .= " and ".$where1;
        }
        if( $where2 ){
            $sql .= " and ".$where2;
        }
        $rlt=Db::query($sql);
        $min_id=0;
        $max_id=0;
        if ($rlt && count($rlt)==1){
            $min_id=$rlt[0]['min_id']??$min_id;
            $max_id=$rlt[0]['max_id']??$max_id;
        }
        $result = ["max"=>$max_id,"min"=>$min_id];
        //缓存5天前的数据
        if( $min_id && $max_id && (time() - $de) > 86400 * 5  ){
            $commonRedis->set($key, json_encode($result), 60 * 86400);
        }
        return $result;
    }

    //讲条件转换成字符串
    public static function whereArrChangeStr($where = null){

        if( is_array($where) ){
            $where1 = '';
            foreach ($where as $v){
                if( isset($v[2]) ){
                    $where1 .= " {$v[0]} {$v[1]} {$v[2]} AND";
                }else{
                    $where1 .= " {$v[0]} = {$v[1]} AND";
                }
            }
            //最后去除AND
            $where = substr($where1, 0, -4);
        }
        return $where;
    }

    public static function getLotteryInfo($gid) {
        $data = Redis::hget("rs:lottery_curr", $gid);
        if (empty($data)) {
            return null;
        } else {
            return json_decode($data, true);
        }
    }

    //获取/设置平台的中奖手续费
    public static function getOrSetPlatformFee($platform_id, $fee = 0) {
        if( $fee < 0 || $fee > 100 ){
            return false;
        }
        $key = "platform_fee:{$platform_id}";
        $apiRedis = new ApiRedis();
        if(!$fee) {
            $fee = $apiRedis->get($key);
            //没有则从数据库取
            if(!$fee) {
                $fee = Db::name("third_configs")->where("tc_id", $platform_id)->value("tc_winning_fee")?:0;
            }
        }
        //重新继续延长或设置缓存时间
        $apiRedis->set($key, $fee, 86400 * 2);
        return floatval($fee);
    }

    /**
     * 获取游戏试玩链接
     * @param $gid
     * @param int $platform 0手机，1pc
     * @return false|string[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function getGameTryUrl($gid, $platform = 0) {
        $gameTryUrl = '';
        //获取游戏及平台数据
        $gameDetail = GamesModel::alias('g')
            ->join('third_configs tc', 'tc.tc_id = g.g_tcid')
            ->field('g.*,tc.tc_id,tc.tc_name,tc.tc_code,tc.tc_token')
            ->where('g.g_id', '=', $gid)
            ->find();
        if( !$gameDetail ){
            return $gameTryUrl;
        }
        $tcCode = strtoupper($gameDetail['tc_code']);
        switch ($tcCode){
            case 'WINFAST':
                //winfast为链接后拼接游戏三方代码
                $baseUrl = 'https://vip.gametech88.com/gamelauncher/play/GAMETECH55';
                if( $gameDetail['tc_token'] ){
                    $baseUrl = $gameDetail['tc_token'];
                }
                $gameTryUrl = $baseUrl . '/' . $gameDetail['g_code'];
                break;
            default:
                $cfgs=Games::getConfigs($gameDetail['g_id'], $gameDetail['tc_id']);
                $otherKvs = ['platform' => $platform];
                $rlt = Third::run($cfgs, 'GETTRYURL', $otherKvs);
                if( $rlt && isset($rlt['tctrygameurl']) && $rlt['tctrygameurl'] ){
                    $gameTryUrl = $rlt['tctrygameurl'];
                }
                break;
        }
        return $gameTryUrl;
    }

}