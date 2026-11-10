<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use app\common\model\GameBetsModel;
use app\common\model\UserBalanceMxModel;

class GameBet extends Command
{

    protected function configure()
    {
        $this->setName('gamebet')
            ->addArgument("uid", Argument::REQUIRED, '') //用户ID
            ->setDescription('对账用户游戏记录');
    }

    protected function execute(Input $input, Output $output)
    {
        $uid = $input->getArgument('uid');
        $bets = GameBetsModel::where('gb_uid', $uid)->select();
        $total = count($bets);
        $i = 1;
        foreach ($bets AS $row) {
            // ->whereLike('msg', "%{$row->gb_gid}%")
            $mx_2_price = UserBalanceMxModel::where('uid', $row->gb_uid)->where('type', 2)->whereLike('msg', "%{$row->gb_sn}%")->sum('price');
            $mx_1_price = UserBalanceMxModel::where('uid', $row->gb_uid)->where('type', 1)->whereLike('msg', "%{$row->gb_sn}%")->sum('price');
            if ($mx_1_price != $row->gb_winprice || $mx_2_price != $row->gb_price) {
                $output->error("[{$i}/{$total}]{$row->gb_uid}=>{$row->gb_sn}->[下注]{$row->gb_price}/{$mx_2_price}->[结算]{$row->gb_winprice}/{$mx_1_price}");
            } else {
                $output->writeln("[{$i}/{$total}]{$row->gb_uid}=>{$row->gb_sn}->[下注]{$row->gb_price}/{$mx_2_price}->[结算]{$row->gb_winprice}/{$mx_1_price}");
            }
            $i++;
        }
    }
}