<?php

declare (strict_types = 1);

namespace app\command;

use app\api\redis\ApiRedis;
use app\common\FastGame\Third_oneapi;
use app\common\game\GameRequest;
use app\common\model\GameInfosModel;
use app\common\model\GamesModel;
use app\model\GameApis;
use app\model\ThirdConfigs;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Db;

class OneApiGameList extends Command{

    protected function configure()
    {
        // 指令配置
        $this->setName('oneapi')
            ->addArgument("type", Argument::REQUIRED, '类型：1供应商列表, 游戏列表')
            ->setDescription('oneapi game list');
    }


    protected function execute(Input $input, Output $output){

        $type = $input->getArgument('type'); // 1 提现 2 充值
        if( $type == 1 ){
            //获取供应商列表
            $this->platformList();
        }else{
            //获取游戏列表
            $this->getGameList();
        }


    }

    //获取供应商列表
    public function platformList(){
        //获取一个已经打开的平台信息
        $platformDetail = ThirdConfigs::where('tc_set_code', '=', 'ONEAPI')
            ->where('tc_status', '=', 1)->find();
        if( !$platformDetail ){
            echo '无平台';
            return false;
        }
        $data = $platformDetail->toArray();
        //获取对应api列表
        $apiList = GameApis::where('ga_tcid', '=', $data['tc_id'])->select()->toArray();
        unset($data['tc_id']);
        //获取供应商列表
        $url = $data['tc_api_urlpre'].'/game/vendors';
        $thirdOneapi = new Third_oneapi();
        $params = [
            'traceId' => $thirdOneapi->generateUUID(5, 20),
            'displayLanguage' => $data['tc_langmap'],
            'currency' => $data['tc_currency'],
        ];
        $header = [
            'Content-type: application/json',
            'X-API-Key: ' . $data['tc_api_prikey'],
            'X-Signature: ' . $thirdOneapi->createSignature($data['tc_api_signkey'], $params),
            // 'Content-Length:' => strlen($params),
        ];
        // 增加HTTP代理请求
        $isProxy = env('IS_REQUIRE_GAME_PROXY', '');
        $proxy = '';
        if ($isProxy) {
            $proxy = env('GAME_PROXY_URL', '');
        }
        $res = json_decode(GameRequest::curl_post($url, json_encode($params), $header, $proxy), true);
        if( isset($res['data']) ) {
            foreach ($res['data'] as $v){
                //判断是否存在
                $tcCode = $v['code'].'ONEAPI';
                if( ThirdConfigs::where('tc_code', '=', $tcCode)->value('tc_id') ){
                    continue;
                }
                //重新组合平台信息和api信息
                $platform = $data;
                $platform['tc_name'] = $v['name'];
                $platform['tc_code'] = $tcCode;
                $platformId = (new ThirdConfigs())->insertGetId($platform);
                $_apiList = $apiList;
                foreach ($_apiList as &$_v){
                    $_v['ga_tcid'] = $platformId;
                    unset($_v['ga_id']);
                }
                (new GameApis())->saveAll($_apiList);
            }
        }
        echo 'success';
    }

    private function getGameList(){
        $limit = 500;
        //获取oneapi集合平台
        $platformList = ThirdConfigs::where('tc_set_code', '=', 'ONEAPI')->select();
        $thirdOneapi = new Third_oneapi();
        foreach ($platformList as $v){
            $page = 1;
            $gameCount = 0;
            $tagExists = [];
            do{
                //获取指定游戏游戏列表
                $url = $v['tc_api_urlpre'].'/game/list';
                $thirdCode = str_replace('ONEAPI', '', $v['tc_code']);
                $params = [
                    'traceId' => $thirdOneapi->generateUUID(),
                    'vendorCode' => $thirdCode,
                    'pageNo' => $page,
                    'pageSize' => $limit
                ];
                $header = [
                    'Content-type: application/json',
                    'X-API-Key: ' . $v['tc_api_prikey'],
                    'X-Signature: ' . $thirdOneapi->createSignature($v['tc_api_signkey'], $params),
                    // 'Content-Length:' => strlen($params),
                ];
                // 增加HTTP代理请求
                $isProxy = env('IS_REQUIRE_GAME_PROXY', '');
                $proxy = '';
                if ($isProxy) {
                    $proxy = env('GAME_PROXY_URL', '');
                }
                $res = json_decode(GameRequest::curl_post($url, json_encode($params), $header, $proxy), true);
                $tcStatus = $v['tc_status'];
                if( isset($res['data']['games']) ){
                    $gameList = $res['data']['games'];
                    if( $gameList ){
                        foreach ($gameList as $_v){
                            $tagExists[] = $_v[0];
                            //判断是否存在
                            if( GamesModel::where('g_code', '=', $_v[0])->value('g_id') ){
                                continue;
                            }
                            //写入游戏表
                            $game = [];
                            $game['g_tcid'] = $v['tc_id'];
                            $game['g_web_tcid'] = $v['tc_id'];
                            $game['g_code'] = $game['g_tag'] = $_v[0];
                            $game['g_icon'] = $_v[3];
                            $game['g_keyword'] = $_v[1];
                            $game['g_type'] = 25;
                            $gid = (new GamesModel())->insertGetId($game);
                            //写入游戏信息表
                            $gameInfo = [];
                            $gameInfo['gi_gid'] = $gid;
                            $gameInfo['gi_lang'] = 'CN';
                            $gameInfo['gi_name'] = $_v[1];
                            $gameInfo['gi_headimg'] = $_v[3];
                            (new GameInfosModel())->insert($gameInfo);
                            Db::name('game_configs')->insertGetId(["gc_gid" => $gid]);
                        }
                    }else{
                        //没有游戏则关闭该游戏平台
                        $tcStatus = 0;
                    }
                    $gameCount = count($gameList);
                    if( $gameCount >= $limit ){
                        $page++;
                    }
                }else{
                    //没有游戏则关闭该游戏平台
                    $tcStatus = 0;
                }
                if( $v['tc_status'] != $tcStatus ){
                    ThirdConfigs::where('tc_id', '=', $v['tc_id'])->update(['tc_status' => $tcStatus]);
                }
                sleep(3);
            }while( $gameCount >= $limit );
            //不存在的游戏代码对应的游戏关闭
            $_condition = [];
            $_condition[] = ['g_tcid', '=', $v['tc_id']];
            $_condition[] = ['g_code', 'not in', $tagExists];
            GamesModel::where($_condition)->update(['g_state' => 0, 'g_lock' => 1, 'g_show' => 0]);
        }
        \app\common\FastGame\Base\Game::buildGamesInfoCache(true);
        echo 'success';
    }
}