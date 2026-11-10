<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\model\ForecastGameModel;
use app\common\model\ForecastModel;
use app\common\model\GameInfosModel;
use app\common\service\AgentService;
use app\common\service\MemberService;
use app\common\service\SystemService;
use think\facade\Db;

class ForecastFactory{

    public function index(){
        $data = [];
        $apiRedis=new ApiRedis();
        // 最高盈利金额订单
        $orderKey = 'forecast_order_list';
        if($apiRedis->has($orderKey)){
            $orderList = json_decode($apiRedis->get($orderKey), true);
        }else{
            $min_game_win_price = env('min_game_win_price', 500);
            $condition = [];
            $condition[] = ['gb_winprice', '>=', $min_game_win_price];
            $condition[] = ['gb_gid', '<>', 0];
            $gameBets = Db::table('dq_game_bets')->where($condition)
                ->limit(10)->order('gb_id desc')
                ->field('gb_winprice,gb_gid')
                ->select();
            $orderList = [];
            foreach ($gameBets as $k => $v){
                $orderList[$k]['game_name'] = GameInfosModel::where('gi_gid', '=', $v['gb_gid'])->value('gi_name');
                $orderList[$k]['price'] = $v['gb_winprice'];
            }
            $apiRedis->set($orderKey, json_encode($orderList), 60 * 5);
        }
        $data['order_list'] = $orderList;

        $params = request()->param();
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $condition = [];
        $condition[] = ['lang', '=', $lang];
        $forecastDetail = ForecastModel::where($condition)->field('id,banner_url,introduce,invite_code')->find();
        if ($forecastDetail){
            $forecastDetail->invite_url = MemberService::createShareUrl($forecastDetail->invite_code);
        }
        $data['forecast'] = $forecastDetail;
        //平台名称
        $platformKey = 'forecast_platform_list';
        if($apiRedis->has($platformKey)) {
            $platformList = json_decode($apiRedis->get($platformKey), true);
        }else{
            $platformList = ForecastGameModel::alias('fg')
                ->join('third_configs tc', 'tc.tc_id=fg.tcid')
                ->field('tc.tc_name as name,tc.tc_id as id,icon')
                ->group('tc.tc_id')
                ->order('fg.sort desc')
                ->select();
            $apiRedis->set($platformKey, json_encode($platformList), 60 * 4);
        }
        $data['platform_list'] = $platformList;

        $condition = [];
        if( isset($params['platform_id']) && $params['platform_id'] !== '' ){
            $condition[] = ['tcid', '=', $params['platform_id']];
        }
        if( isset($forecastDetail->id) ){
            $condition[] = ['forecast_id', '=', $forecastDetail->id];
        }
        $page = $params['page'] ?? 1;
        $limit = $page['limit'] ?? 10;
        //获取游戏列表
        $forecastGameList = ForecastGameModel::alias('fg')
            ->join('game_infos gi', 'gi.gi_gid=fg.gid')
            ->join('games g', 'g.g_id=fg.gid')
            ->where($condition)->page($page, $limit)
            ->field('fg.time,fg.update_time,gi.gi_name,gi_headimg,g.g_code,g.g_tag')
            ->order('fg.sort desc')
            ->select();
        $gameList = [];
        foreach ($forecastGameList as $k => $v){
            $gameList[$k]['game_name'] = $v['gi_name'];
            $gameList[$k]['g_code'] = $v['g_code'];
            $gameList[$k]['g_tag'] = $v['g_tag'];
            $gameList[$k]['game_img'] = $v['gi_headimg'];
            $gameList[$k]['percent'] = $this->countPercent($v['time'], $v['update_time']);
            $gameList[$k]['time'] = $v['time'];
            $gameList[$k]['init_time'] = strtotime($v['update_time']);
        }
        $data['game_list'] = $gameList;
        //获取默认币种
        $currency = Util::i18n_currency_no_amount();
        $data['currency'] = $currency;
        //获取客服链接1
        $agentWebsiteConfig = AgentService::getAgentWebSiteConfig();
        if( $agentWebsiteConfig ){
            $data['service_url'] = $agentWebsiteConfig['mobile_service_url'];
            $data['app_download_url'] = $agentWebsiteConfig['android_download_url'];
            $data['ios_download_url'] = $agentWebsiteConfig['ios_download_url'];
        }else{
            $data['service_url'] = SystemService::getSiteDataByUid(0)['mobile_service_url'];
            $data['app_download_url'] = SystemService::getSiteDataByUid(0)['android_download_url'];
            $data['ios_download_url'] = SystemService::getSiteDataByUid(0)['ios_download_url'];
        }
        //获取活动列表
        $actionKey = 'forecast_action_list';
        if($apiRedis->has($actionKey)) {
            $actionList = json_decode($apiRedis->get($actionKey), true);
        }else{
            $actionList = (new ActionFactory())->list_run()['data'];
            $apiRedis->set($actionKey, json_encode($actionList), 60 * 6);
        }
        $data['action_list'] = $actionList;
        return ['code' => 1, 'data' => $data];
    }

    //计算百分比
    public function countPercent($time, $updateTime){
        if( $time == 0 ){
            return 0;
        }
        $timePoor = time() - strtotime($updateTime);
        $timePoor = max($timePoor, 1);
        $timeSeconds = $time * 60;
        $percent = round(($timePoor % $timeSeconds / $timeSeconds) * 100, 2);
        $percent = max($percent, 0.01);
        return min($percent, 99.99);
    }

}