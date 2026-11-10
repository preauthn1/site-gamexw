<?php
declare (strict_types = 1);
namespace app\command;

use app\common\FastGame\Base\Game;
use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Params;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Utils;
use app\common\FastGame\Third_ogs;
use app\common\lib\ActiveReport;
use app\common\model\GameWaterRatesModel;
use app\common\model\ThirdCostLogsModel;
use app\common\service\BetService;
use app\model\GameApis;
use app\manage\controller\GiveFreeGameBet;
use app\model\GameConfigs;
use app\model\Games;
use app\common\lib\ReportExport;
use app\common\lib\Util;
use app\common\model\ExportReportsModel;
use app\common\model\PaySetModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\payment\Entry;
use app\common\service\MemberService;
use app\manage\factory\GameBetsFactory;
use app\model\ThirdConfigs;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;
use app\api\redis\ApiRedis;
use think\facade\Queue;
use think\facade\Log;

class Test extends Command {

    protected function configure() {
        $this->setName('test')->setDescription('专用测试命令，用于开发调试');
    }

    protected function execute(Input $input, Output $output) {
        //WAZDAN test
        $cfg=Games::getConfigs(null,46);
        Params::$lang="EN";
        $otherkvs=[
            "platform" =>0,
            "back_url"=>'https://testt50.rsdemo.co'
        ];
        //$gamelist=Third::run($cfg,"getgamelist",$otherkvs);
        //print_r($gamelist);
        $url=Game::getGameUrl(6734,1,true,$otherkvs);
        //$this->updateOneApiDetail();
        //获取要赠送游戏的模板表单字段（根据结果生成界面）
        /*$rlt=GiveFreeGameBet::getFreeeBetFormTemplateTest(5134);
        print_r($rlt);*/
        //获取赠送游戏列表
        //print_r(GiveFreeGameBet::liststest());
        //获取游戏地址
        /*$other['platform'] =  0;
        $other['back_url'] =  '';
        $other['depositMoney'] =  0;
        print_r(Game::getGameUrl(5134,1,true,$other));
        die();*/
        //赠送免费游戏 PP
        /*print_r(Game::freeBetsToUser(1,5134,[
            "freebetRounds"=>10,
            "price1"=>1,
            "freebetStarttime"=>time(),
            "freebetEndtime"=>time()+3600*24*15,
            "freebetPriceCnt"=>15,
            "betrate"=>15
        ],1));*/
//赠送免费游戏 WINFAST
        /*print_r(Game::freeBetsToUser(1,8770,[
            "freebetRounds"=>200,
            "freebetStarttime"=>time(),
            "freebetEndtime"=>time()+3600*24*15,
            "freebetPriceCnt"=>2000,
            "betrate"=>1
        ],1));*/
         die();
        // try {
        //     //设置一个游戏的流水比率
        //     GameWaterRatesModel::setGameWaterRates(23, 353, 1, 0.9, 0);
        //     GameWaterRatesModel::setGameWaterRates(23, 353, 2, 0.8, 0);
        //     GameWaterRatesModel::setGameWaterRates(23, 1346, 1, 0.9, 0);
        //     GameWaterRatesModel::setGameWaterRates(23, 1346, 2, 0.8, 0);
        //     GameWaterRatesModel::setGameWaterRates(23, 1347, 1, 0.9, 0);
        //     GameWaterRatesModel::setGameWaterRates(23, 1347, 2, 0.8, 0);
        //     GameWaterRatesModel::setGameWaterRates(23, 1525, 1, 0.93, 0);
        //     GameWaterRatesModel::setGameWaterRates(23, 1525, 2, 0.83, 0);
        //     GameWaterRatesModel::setGameWaterRates(23, 1349, 1, -1, 0);
        //     GameWaterRatesModel::setGameWaterRates(23, 1349, 2, -1, 0);
        //     print_r(GameWaterRatesModel::game_water_rates_list(null, 23));
        //     print_r(GameWaterRatesModel::get_game_water_rates(23));
        //     GameWaterRatesModel::setGameWaterRates(23, 1525, 2, -1, 0);
        //     print_r(GameWaterRatesModel::game_water_rates_list(null, 23));
        //     print_r(GameWaterRatesModel::get_game_water_rates(23));

        //     GameWaterRatesModel::setGameWaterRates(24, 475, 1, 0.95, 0);
        //     GameWaterRatesModel::setGameWaterRates(24, 475, 2, 0.85, 0);
        //     GameWaterRatesModel::setGameWaterRates(24, 1345, 1, 0.95, 0);
        //     GameWaterRatesModel::setGameWaterRates(24, 1345, 2, 0.85, 0);
        //     GameWaterRatesModel::setGameWaterRates(24, 1362, 1, 0.92, 0);
        //     GameWaterRatesModel::setGameWaterRates(24, 1362, 2, 0.82, 0);
        //     GameWaterRatesModel::setGameWaterRates(24, 1363, 1, 0.91, 0);
        //     GameWaterRatesModel::setGameWaterRates(24, 1363, 2, 0.81, 0);
        //     print_r(GameWaterRatesModel::game_water_rates_list(null, 24));
        //     print_r(GameWaterRatesModel::get_game_water_rates(24));
        //     //设置一个分类的流水比率
        //     GameWaterRatesModel::setGameWaterRates(23, false, 1, 0.99, 0.9);
        //     print_r(GameWaterRatesModel::game_water_rates_list(null, 23));
        //     print_r(GameWaterRatesModel::get_game_water_rates(23));
        //     GameWaterRatesModel::setGameWaterRates(23, false, 2, -1, 0.83);
        //     print_r(GameWaterRatesModel::game_water_rates_list(null, 23));
        //     print_r(GameWaterRatesModel::get_game_water_rates(23));
        //     GameWaterRatesModel::setGameWaterRates(24, false, 1, 0.88, 0.95);
        //     print_r(GameWaterRatesModel::game_water_rates_list(null, 24));
        //     print_r(GameWaterRatesModel::get_game_water_rates(24));
        //     GameWaterRatesModel::setGameWaterRates(24, false, 2, 0.88, -1);
        //     print_r(GameWaterRatesModel::game_water_rates_list(null, 24));
        //     print_r(GameWaterRatesModel::get_game_water_rates(24));
        //     GameWaterRatesModel::setGameWaterRates(24, false, 1, -1, -1);
        //     print_r(GameWaterRatesModel::game_water_rates_list(null, 24));
        //     print_r(GameWaterRatesModel::get_game_water_rates(24));

        // //获取流水比率列表
        // print_r(GameWaterRatesModel::game_water_rates_list());
        // print_r(GameWaterRatesModel::game_water_rates_list(null,23));
        // print_r(GameWaterRatesModel::game_water_rates_list(null,23,1347));
        // print_r(GameWaterRatesModel::game_water_rates_list(null,null,null,1,0.88));
        //$this->updateOneApiDetail();
        /*for($i=0;$i<100;$i++) {
            ActiveReport::runtask();
            sleep(1);
        }*/

        /*$gid = "2956";
        $gcfg=Games::getConfigs($gid);
        var_dump($gcfg);*/
        exit();
        //ActiveReport::runtask();
        /*for($i=0;$i<500;$i++) {
            ActiveReport::runtask();
        }*/
        //print_r(ActiveReport::getActiveDatas('d',20231001,20231101));*/
        /*BetService::cacheBetIdByTime();
        Utils::L('test',BetService::getGbidByTime(1706634900,1708370100,'gb_intime'));
        Utils::L('test',BetService::getGbidByTime(1006104199,1708870100,'gb_bettime'));
        Utils::L('test',BetService::getGbidByTime(1706634900,1908870099,'gb_overtime'));*/
        //Utils::L('test',ExportReportsModel::getlist());
       //ReportExport::testExportList();
        //UserBxModel::recoverBxitem(52);
//       echo MemberService::getRechargeByUid(98);
        //UserBxModel::bxPorcByRegister(68,65,1705957336);
        //UserBxModel::bxPorcByRecharge(65,200,1,1705957336);
        //print_r(UserBxModel::getDaycntByUid(65,20240122));
        //var_dump(UserBxModel::addBx(2,1,5));
        //var_dump(UserBxModel::pay(3,65));
        //var_dump(UserBxModel::getlist());
        //print_r(UserBxModel::getLogs(20240122,65));
        //UserBxModel::taskPayBx();

    }

    //修改ONEAPI的detail的API数据
    protected function updateOneApiDetail(){
        //查询ONEAPI的平台数据
        $thirdList = ThirdConfigs::where('tc_set_code','=', 'ONEAPI')->field('tc_id')->select();
        foreach ($thirdList as $v){
            $condition = [];
            $condition[] = ['ga_tcid', '=', $v['tc_id']];
            $condition[] = ['ga_route', '<>', '/v2/transaction/detail'];
            $condition[] = ['ga_func', '=', 'GETBETDETAIL'];
            GameApis::where($condition)->update([
                'ga_route' => '/v2/transaction/detail',
                'ga_hparam' => '{"Content-Type":"application/json","X-API-Key":"{tcapikey}","X-Signature":"{signature}"}',
                'ga_pparam' => '{"traceId":"{traceId}","betId":"{betId}","fromTime":"{fromTime}","toTime":"{toTime}","displayLanguage":"zh"}',
                'ga_keyfield' => '{"rightflag":{"key":"status","value":"SC_OK"},"tcgamedetailurl":{"key":"data.detailUrl"}}',
                'ga_urlpre' => ''
            ]);
        }
        echo '更新成功';
    }

}
