<?php
declare (strict_types = 1);

namespace app\command;
use app\common\model\UserLoginModel;
use app\common\model\UserModel;
use app\common\model\UserOfflineModel;
use app\common\model\GameBetsModel;
use app\common\service\BetService;
use app\common\Utils;
use think\facade\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use app\common\lib\Util;
use think\facade\Queue;
use think\facade\Db;

class Remain extends Command
{
    protected function configure()
    {
        // 统计留存任务
        $this->setName('remain')
            ->addArgument("type", Argument::REQUIRED, '')
            ->addArgument("start_date", Argument::OPTIONAL, '')
            ->addArgument("end_date", Argument::OPTIONAL, '')
            ->setDescription('public task');
    }

    protected function execute(Input $input, Output $output)
    {
        $type = $input->getArgument('type'); // 1 当天 2 时间范围
        $day = [1,2,3,4,5,6,7,15,30,60];
        try {
            if($type == 1){
                $start_date = date("Y-m-d", strtotime('-30 day'));
                $end_date = date("Y-m-d", strtotime('+1 day'));
            } elseif($type == 2){
                $start_date = $input->getArgument('start_date');
                if(empty($start_date)){
                    $start_date = date("Y-m-d");
                }
                $end_date = $input->getArgument('end_date');
                if(empty($end_date)){
                    $end_date = date("Y-m-d");
                }
            }
            $n_day_ago_start_time = strtotime($start_date." 00:00:00");
            $n_day_ago_end_time = strtotime($end_date." 23:59:59");
            for($i = $n_day_ago_start_time;$i <= $n_day_ago_end_time;$i+=86400){
                $date = date("Y-m-d", $i);
                foreach($day as $val){
//                        $this->remainStatGame($date, $val, $output);
//                        $this->remainStatLogin($date, $val, $output);
                    $this->remainStatRecharge($date, $val, $output);
                }
            }
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln($e->getTraceAsString());
        }
    }
    
    protected function remainStatGame($date, $daynum, $output){
        $s_time = date("Y-m-d H:i:s");
        // 统计当天开始 结束 时间戳
        $startTime = strtotime($date.' 00:00:00');
        $endTime = strtotime($date.' 23:59:59');
        // 获取 N 天前注册的用户 sql
        $n_day_ago_start_time = $startTime - ($daynum * 86400);
        $n_day_ago_end_time = $endTime - ($daynum * 86400);
//        $sql = UserModel::where([['add_time','between',[$n_day_ago_start_time,$n_day_ago_end_time]]])->field("id")->buildSql();
        
        // 获取 N 天前注册且充值过的用户(到账时间为准)

        $uids_arr = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$n_day_ago_start_time." and update_time<=".$n_day_ago_end_time." and first = 1 ")->column("uid");
        if(!empty($uids_arr)){
            $uids_sql = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$n_day_ago_start_time." and update_time<=".$n_day_ago_end_time." and first = 1")->field("uid")->buildSql();

            $betSql = BetService::getGbidByTime($startTime, $endTime)['sql'];
            //获取三方游戏用户 无效订单用户不计数
            $game_uids_arr = GameBetsModel::where("gb_winlose>0 and {$betSql} and gb_uid in(".$uids_sql.")")->column("gb_uid");

            // 三方游戏用户 并去重
            $uids_all = array_unique($game_uids_arr);
            //计算留存率 保留两位小数（四舍五入） 当天活跃用户数 / N 前注册且充值过的用户数
            $remain_rate = sprintf("%.4f", count($uids_all) / count($uids_arr)) * 100;
            $remain_uids = implode(",", $uids_all);
        } else {
            $remain_rate = 0;
            $remain_uids = "";
        }
        $this->save($date, $daynum, $remain_rate, $remain_uids, 0, 1);

        $output->writeln("统计：".$date.",".$daynum."天前[". date("Y-m-d", $n_day_ago_start_time) ."]的游戏留存率===开始：{$s_time}===结束：".date("Y-m-d H:i:s"));
    }


    protected function remainStatLogin($date, $daynum, $output){
        $s_time = date("Y-m-d H:i:s");
        // 统计当天开始 结束 时间戳
        $startTime = strtotime($date.' 00:00:00');
        $endTime = strtotime($date.' 23:59:59');
        // 获取 N 天前注册的用户 sql
        $n_day_ago_start_time = $startTime - ($daynum * 86400);
        $n_day_ago_end_time = $endTime - ($daynum * 86400);
//        $sql = UserModel::where([['add_time','between',[$n_day_ago_start_time,$n_day_ago_end_time]]])->field("id")->buildSql();

        // 获取 N 天前注册且充值过的用户(到账时间为准)

        $uids_arr = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$n_day_ago_start_time." and update_time<=".$n_day_ago_end_time." and first = 1 ")->column("uid");
        if(!empty($uids_arr)){
            $uids_sql = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$n_day_ago_start_time." and update_time<=".$n_day_ago_end_time." and first = 1")->field("uid")->buildSql();
            // 获取登陆的用户
            $uids_all = UserLoginModel::where("login_time>=".$startTime." and login_time<=".$endTime." and uid in(".$uids_sql.")")->distinct(true)->column("uid");
            //计算留存率 保留两位小数（四舍五入） 当天活跃用户数 / N 前注册且充值过的用户数
            $remain_rate = sprintf("%.4f", count($uids_all) / count($uids_arr)) * 100;
            $remain_uids = implode(",", $uids_all);
        } else {
            $remain_rate = 0;
            $remain_uids = "";
        }
        $this->save($date, $daynum, $remain_rate, $remain_uids, 0, 0);

        $output->writeln("统计：".$date.",".$daynum."天前[". date("Y-m-d", $n_day_ago_start_time) ."]的登陆留存率===开始：{$s_time}===结束：".date("Y-m-d H:i:s"));
    }

    protected function remainStatRecharge($date, $daynum, $output){
        $s_time = date("Y-m-d H:i:s");
        // 统计当天开始 结束 时间戳
        $startTime = strtotime($date.' 00:00:00');
        $endTime = strtotime($date.' 23:59:59');
        // 获取 N 天后还充值的用户 sql
        $n_day_after_start_time = $startTime + ($daynum * 86400);
        $n_day_after_end_time = $endTime + ($daynum * 86400);
        if ($endTime > time()) {
            return;
        }

        // 当天首充用户数和金额
        $recharge_user = count(array_unique(UserOfflineModel::where("(status=3 or status=4) and update_time>=".$startTime." and update_time<=".$endTime." and first = 1 ")->distinct(true)->column("uid")));
        $recharge_amount = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$startTime." and update_time<=".$endTime." and first = 1")->sum("arrival_price");
        // 获取当天首充且 N 天后还有充值的用户(到账时间为准)
        $uids_arr = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$startTime." and update_time<=".$endTime." and first = 1 ")->column("uid");
        if(!empty($uids_arr)){
            $uids_sql = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$startTime." and update_time<=".$endTime." and first = 1")->field("uid")->buildSql();
            // 获取当天首次充值用户总数
            $uids_all = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$n_day_after_start_time." and update_time<=".$n_day_after_end_time." and uid in(".$uids_sql.")")->distinct(true)->column("uid");
            $uids_all = array_unique($uids_all);
            $remain_people = count($uids_all);
            //计算留存率 保留两位小数（四舍五入） 当天活跃用户数 / N 前注册且充值过的用户数
            $remain_rate = sprintf("%.4f", $remain_people / count($uids_arr)) * 100;
            $remain_uids = implode(",", $uids_all);
            $remain_amount = UserOfflineModel::where("(status=3 or status=4) and update_time>=".$n_day_after_start_time." and update_time<=".$n_day_after_end_time." and uid in(".$uids_sql.")")->sum("arrival_price");
        } else {
            $remain_rate = 0;
            $remain_uids = "";
            $remain_amount = 0;
            $remain_people = 0;
        }
        $this->save($date, $daynum, $remain_rate, $remain_uids, $remain_amount, $recharge_amount, $recharge_user, 2, $remain_people);

        $output->writeln("统计：".$date.",".$daynum."天后[". date("Y-m-d", $n_day_after_start_time) ."]的充值留存率===开始：{$s_time}===结束：".date("Y-m-d H:i:s"));
    }

    private function save($date, $daynum, $remain_rate, $remain_uids, $remain_amount, $recharge_amount, $recharge_user, $type, $remain_people) {
        $data = [];
        $data['type'] = $type;
        $data['remain_stat'] = $date;
        $data['remain_rate'.$daynum] = $remain_rate;
        $data['remain_uids'.$daynum] = $remain_uids;
        $data['remain_amount'.$daynum] = $remain_amount;
        $data['remain_people'.$daynum] = $remain_people;
        $where = ['remain_stat' => $date, 'type' => $type];
        $report = Db::name('remain_report')->where($where)->find();
        if(empty($report)){
            $data['recharge_amount'] = $recharge_amount;
            $data['recharge_user'] = $recharge_user;
            Db::name('remain_report')->insert($data);
        }else{
            $ret = Db::name('remain_report')->where('id', $report['id'])->update($data);
        }
    }
}
