<?php
namespace app\command;

use app\api\redis\ApiRedis;
use app\common\FastGame\Third_kl;
use app\model\Games;
use app\model\ThirdConfigs;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use app\common\model\GameBetsHotModel;
use app\common\game\GameRequest;

//任务查询克拉拉彩注单结果
//type: kerala、matka、pick3
class SelectKlBetResult extends Command{

    protected function configure()
    {

        // 指令配置
        $this->setName('selectklbetresult')
            ->addArgument("type", Argument::OPTIONAL, '类型：type: kerala、matka、pick3')
            ->setDescription('oneapi game list');
    }

    protected function execute(Input $input, Output $output){
        $apiRedis = new ApiRedis();
        $cacheKey = 'kl_bet_result';
        if( $apiRedis->has($cacheKey) ){
            $this->output->writeln('任务执行中');
            return false;
        }
        $apiRedis->set($cacheKey, 1, 600);
        $this->handleBetResult();
        $apiRedis->delete($cacheKey);
    }

    //处理未结算注单
    private function handleBetResult(){
        $platformDetail = ThirdConfigs::where('tc_code', '=', 'kl')
            ->where('tc_status', '=', 1)->find();
        if( !$platformDetail ){
            $this->output->writeln('平台未开启');
            return false;
        }
        $this->output->writeln('开始处理未结算注单');
        $hotBetLimit = env('hot_bet_limit', 2000);
        $condition = [];
        $condition[] = ['gb_winlose', '=', 1];
        $condition[] = ['gb_tcid', '=', $platformDetail['tc_id']];
        $hotBetsList = GameBetsHotModel::where($condition)
            ->where('gb_overtime=gb_bettime') //下注时间等于结算时间的为处理订单，不用再处理
            ->limit($hotBetLimit)
            ->order('gb_id asc')
            ->select();
        foreach ($hotBetsList as $hotBet){
            switch ($hotBet['gb_cusfield']){
                case Third_kl::KERALA:
                    self::betResult($hotBet, $platformDetail, Third_kl::KERALA);
                    break;
                case Third_kl::PICK3:
                    self::betResult($hotBet, $platformDetail, Third_kl::PICK3);
                    break;
                case Third_kl::MATKA:
                    self::betResult($hotBet, $platformDetail, Third_kl::MATKA);
                    break;
                default:
                    //原来的模式，循环查询三个
                    self::betResult($hotBet, $platformDetail, Third_kl::KERALA);
                    self::betResult($hotBet, $platformDetail, Third_kl::PICK3);
                    self::betResult($hotBet, $platformDetail, Third_kl::MATKA);
                    break;
            }
        }
        $this->output->writeln('未结算注单处理完成');
    }

    //处理结果
    private function betResult($hotBet, $platformDetail, $type = Third_kl::PICK3){
        $this->output->writeln("开始{$type}===注单号：{$hotBet['gb_sn']}");
        $thirdResult = (new Third_kl())->_request($hotBet, $platformDetail, $type);
        if( !$thirdResult ){
            $this->output->writeln('请求失败/无数据不是这个类型的注单');
            return false;
        }
        $this->successHandle($hotBet, $thirdResult, $type);
        $this->output->writeln("{$type}彩注单处理完成");
        return true;
    }

    //成功处理
    private function successHandle($hotBet, $thirdResult, $type = ''){
        if( !$thirdResult ){
            $this->output->writeln('三方查询该注单不存在或者不属于这个类型');
            return false;
        }
        //只处理未中奖的
        //狀態0:未開獎1:未中獎2:未領取3:已領取
        if( in_array($thirdResult['Status'], [0, 3]) ){
            $this->output->writeln('未开奖/已领取');
            return false;
        }elseif ( $thirdResult['Status'] == 2 ){
            $hotBet->save([
                'gb_overtime' => time(),
                'gb_cusfield' => $type,
                'gb_betinfo' => json_encode($thirdResult)
            ]);
            $this->output->writeln('未领取下次不再处理');
            return false;
        }
        $hotBet->save([
            'gb_winlose' => 2,
            'gb_cusfield' => $type,
            'gb_overtime' => time(),
            'gb_otherwin' => $hotBet['gb_winprice'] - $hotBet['gb_price'],
            'gb_validwater' => $hotBet['gb_winprice'] > $hotBet['gb_price'] ?  $hotBet['gb_price'] : $hotBet['gb_price'] - $hotBet['gb_winprice'],
            'gb_betinfo' => json_encode($thirdResult)
        ]);
        $this->output->writeln('结束===注单号：'.$hotBet['gb_sn']);
    }

}