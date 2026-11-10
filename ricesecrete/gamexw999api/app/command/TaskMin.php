<?php
namespace app\command;
use app\api\factory\GameFactory;
use app\api\factory\SystemFactory;
use app\api\redis\ApiRedis;
use app\common\model\UserFreebetsUsedModel;
use app\common\model\GameWaterRatesModel;
use app\common\redis\redis;
use app\common\service\MemberService;
use app\common\Utils;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/** 综合分钟任务
 * Class JsZhudan
 * @package app\command
 * @author twt326
 * @date 2022-03-28
 */
class TaskMin extends Command
{
    private $logfile="taskMin";
    private static $usefiles=[
        'inUseStaticFiles.txt',
        'system.info.json',
        'ranking.index.json',
        'banner.index.json',
        'match.list.json'
    ];//当前有效静态文件列表，用于清除无效静态文件,非每分钟都会生成的静态文件直接写在这里
    
    protected function configure()
    {
        // 指令配置
        $this->setName('taskmin')
            ->setDescription('综合分钟任务');
    }

    protected function execute(Input $input, Output $output)
    {
        $cache = new ApiRedis();

        //执行生成 system.info静态页任务
        Utils::L('taskMin','生成system.info.json');
        try {
            $rlt = SystemFactory::info_static();
            Utils::writeToStaticFile('system.info.json', json_encode($rlt));
            //self::writeUseStaticFiles(false,'system.info.json');
        }catch (\Exception $ex){
            Utils::L('taskMinErr','生成system.info.json:'.$ex);
        }
        //执行生成 ranking.index静态页任务  十分钟
        try {
            $updateRankingtime = $cache->get('updateRankingTime');
            $updateRankingtime = empty($updateRankingtime)?0:$updateRankingtime;
            if($updateRankingtime == 0){
                $rlt = (new SystemFactory())->ranking_run();
                Utils::writeToStaticFile('ranking.index.json', json_encode($rlt));
                $cache->set('updateRankingTime', 1, 600);
            }
        }catch (\Exception $ex){
            Utils::L('taskMinErr','生成ranking.index.json:'.$ex->getMessage());
        }
        //执行生成 banner.index静态页任务  十分钟
        Utils::L('taskMin','生成banner.index');
        try {
            $updateBannerTime = $cache->get('updateBannerTime');
            $updateBannerTime = empty($updateBannerTime)?0:$updateBannerTime;
            if($updateBannerTime == 0){
                $rlt = (new SystemFactory())->banner_static();
                Utils::writeToStaticFile('banner.index.json', json_encode($rlt));
                $cache->set('updateBannerTime', 1, 600);
            }
        }catch (\Exception $ex){
            Utils::L('taskMinErr','生成banner.index.json:'.$ex);
        }

        // 缓存用户充值提现
        $updateRechargeWithdrawTime = $cache->get('updateRechargeWithdrawTimeV2');
        $withdrawalTime = $rechargeTime = 0;
        if( $updateRechargeWithdrawTime ){
            $withdrawalTime = $updateRechargeWithdrawTime[0];
            $rechargeTime = $updateRechargeWithdrawTime[1];
        }
        $time = MemberService::buildUserRechargeWithdraw($withdrawalTime, $rechargeTime);
        $cache->set('updateRechargeWithdrawTimeV2', $time);

        //游戏榜单
        //order3
        $params = [
            'page' => 1,
            'limit' => 20
        ];
        $rlt = (new GameFactory())->game_order3($params, true);
        Utils::writeToStaticFile('game.order3.json', json_encode($rlt));
        //order4
        $rlt = (new GameFactory())->game_order4([], true);
        Utils::writeToStaticFile('game.order4.json', json_encode($rlt));

        //免费赠送游戏更新核销信息
        UserFreebetsUsedModel::updateUsedinfoTask();

        //初始化游戏流水比例
        GameWaterRatesModel::initRates();
    }
}