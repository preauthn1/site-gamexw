<?php

namespace app\command;

use app\common\FastGame\Base\Utils;
use app\common\model\UserModel;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Db;

class UserDetail extends Command
{
    protected function configure()
    {
        $this->setName('user:detail')
            ->addArgument("userid", Argument::REQUIRED, '用户ID')
            ->setDescription('Transfer items');
    }

    protected function execute(Input $input, Output $output)
    {
        $userid = $input->getArgument('userid');
        if ($userid == 'all') {
            $uids = UserModel::column('id');
        } else {
            $uids = [$userid];
        }
        foreach ($uids as $uid) {
            $this->detail($uid);
        }
    }

    private function detail($userid) {
        $sql[] = "SELECT uid,SUM(arrival_price) as amount, '充值' as 名目 FROM dq_user_offline WHERE `status` IN(3,4)  AND uid='{$userid}'";
        $sql[] = "SELECT uid,SUM(arrival_prices*-1) as amount, '提现' as 名目 FROM dq_user_withdrawal WHERE `status` IN(3,4)  AND uid='{$userid}'";
        $sql[] = "SELECT uid,SUM(price) as amount, '赠送' as 名目 FROM dq_artificial_balance WHERE a_type!=2  AND uid='{$userid}'";
        $sql[] = "SELECT uid,SUM(price*-1) as amount, '人工减少' as 名目 FROM dq_artificial_balance WHERE a_type=2  AND uid='{$userid}'";
        $sql[] = "SELECT uid,SUM(ck_hl_amount+gd_amount) as amount, '活动' as 名目 FROM dq_action_apply WHERE ps_status=1  AND uid='{$userid}'";
        $sql[] = "SELECT uid,SUM(price*-1) as amount, '用户抽奖消耗' as 名目 FROM dq_lottery_record WHERE prize_type=1  AND uid='{$userid}'";
        $sql[] = "SELECT uid,SUM(prize_price) as amount, '用户抽奖中奖' as 名目 FROM dq_lottery_record WHERE prize_type=1  AND uid='{$userid}'";
        $sql[] = "SELECT sff_uid as uid,SUM(sff_fy) as amount, '返佣' as 名目 FROM dq_third_stats_fyfy WHERE sff_payed = 2 AND sff_uid='{$userid}'";
        $sql[] = "SELECT sb_uid as uid,SUM(sb_backmoney) as amount, '返水' as 名目 FROM dq_third_stats_back WHERE sb_payed = 2 AND sb_uid='{$userid}'";
        $sql[] = "SELECT uid,SUM(money) as amount, '推广奖励' as 名目 FROM dq_agent_sell_log WHERE `status`=3  AND uid='{$userid}'";
        $sql[] = "SELECT uid,SUM(reward_amount_or_integral) as amount, '任务奖励' as 名目 FROM dq_task_record WHERE reward_type=0  AND uid='{$userid}'";
        $sql[] = "SELECT uid,SUM(reward_amount) as amount, '周月礼金' as 名目 FROM dq_user_level_reward WHERE status=1 AND uid='{$userid}'";
        $sql[] = "SELECT gb_uid as uid,SUM(gb_otherwin) as amount, '客损' as 名目 FROM dq_game_bets WHERE gb_winlose > 1 AND gb_uid='{$userid}'";
        $sql[] = "SELECT id as uid,sum(balance) as amount, '用户余额' as 名目 FROM dq_user where id='{$userid}'";
        $sql[] = "SELECT uid,SUM(IF(type=2,price*-1,price)) as amount, '明细余额' as 名目 FROM `dq_user_balance_mx` WHERE uid='{$userid}'";

        $str = "UID:{$userid}";
        $i = 0;
        $aaa = [];
        foreach ($sql as $s) {
            $a = Db::query($s);
            $a = $a[0];
            if (empty($a['amount'])) {
                $a['amount'] = 0;
                $a['uid'] = $userid;
            }
            $str .= ";{$a['名目']}:{$a['amount']}";
            $aaa[$a['名目']] = $a['amount'];
            $i++;
        }
        $balance1 = $aaa['用户余额'];
        $balance2 = $aaa['明细余额'];
        unset($aaa['用户余额']);
        unset($aaa['明细余额']);
        $result = sprintf4f(array_sum(array_values($aaa)));
        $str .= ";结果:{$result}";
        if ($result != $balance1 || $result != $balance2) {
            $this->output->error($str);
            Utils::L("userdetail", $str, false, true);
        } else {
            $this->output->writeln($str);
        }
    }
}