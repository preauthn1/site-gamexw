<?php
declare (strict_types = 1);

namespace app\command;
use app\common\model\GameBetsModel;
use app\common\model\UserModel;
use app\common\model\ArtificialBalanceModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserWithdrawalModel;
use app\common\model\AgentRakeBackModel;
use app\common\model\AgentSellLogModel;
use app\common\model\ActionApplyModel;
use app\common\model\LotteryRecordModel;
use app\common\service\ReportService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\api\redis\ApiRedis;
use think\facade\Queue;
use think\facade\Db;

class UpdateReport extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('updatereport')
            ->addArgument("type", Argument::REQUIRED, '')
            ->addArgument("start_date", Argument::OPTIONAL, '')
            ->addArgument("end_date", Argument::OPTIONAL, '')
            ->setDescription('update report status');
    }

    protected function execute(Input $input, Output $output)
    {
        $type = $input->getArgument('type');
        if($type == 1){
            $start_date = date("Y-m-d");
            $end_date = date("Y-m-d");
            $start_time = strtotime($start_date." 00:00:00");
            $end_time = strtotime($end_date." 23:59:59");
        }elseif($type == 2){
            $start_date = date("Y-m-d");
            $end_date = date("Y-m-d");
            $start_time = strtotime($start_date." 00:00:00") - 2*86400;
            $end_time = strtotime($end_date." 23:59:59") - 86400;
        }else{
            $start_date = $input->getArgument('start_date');
            if(empty($start_date)){
                $start_date = date("Y-m-d");
            }
            $end_date = $input->getArgument('end_date');
            if(empty($end_date)){
                $end_date = date("Y-m-d");
            }
            $start_time = strtotime($start_date." 00:00:00");
            $end_time = strtotime($end_date." 23:59:59");
        }
        for($i = $start_time;$i <= $end_time;$i+=86400){
            $date = date("Y-m-d", $i);
            try {
                $this->getData($date, $output);
            } catch (\Exception $e) {
                dump([$e->getMessage(), $e->getTraceAsString()]);
            }
        }
    }
    
    protected function getData($date, $output){
        $s_time = date("Y-m-d H:i:s");
        ReportService::updateReport($date);
        $output->writeln($date."===开始：{$s_time}===结束：".date("Y-m-d H:i:s"));
    }
}
