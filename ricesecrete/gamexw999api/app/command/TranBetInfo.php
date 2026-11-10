<?php

namespace app\command;

use Carbon\Carbon;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\Exception;
use think\facade\Db;

class TranBetInfo extends Command
{

    protected function configure()
    {
        // 指令配置
        $this->setName('tran:betinfo')
            ->addArgument("size", Argument::OPTIONAL, '单次迁移数据量', 100000)
            ->setDescription('迁移bets表info字段');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln("start " . Carbon::now());
        $start = max([Db::name('game_betsex')->max('gbe_id'), 0]);
        $size = $input->getArgument('size');
        if (empty($size)) {
            throw new Exception('参数有误');
        }
        $start += 1;
        $end = $start + $size - 1;

        while (true) {
            $output->writeln("迁移 {$start} - {$end} ");
            $bet = Db::name('game_bets')->where('gb_id', '>=', $start)->find();
            if (empty($bet) || $bet['gb_bettime'] > strtotime("-2 day")) {
                $output->writeln("只迁移2天之前到数据");
                break;
            }
            Db::startTrans();
            try {
                $sql = "insert into dq_game_betsex (gbe_id,gbe_inmonth,gbe_betinfo) select gb_id,FROM_UNIXTIME(gb_bettime,'%Y%m'),gb_betinfo from dq_game_bets where gb_id BETWEEN {$start} and {$end}";
                $ret = Db::execute($sql);
                $output->writeln("迁移数据：{$ret} - " . Carbon::now());
                if (empty($ret)) {
                    $output->writeln("迁移结束 - " . Carbon::now());
                    break;
                }
                $sql = "update dq_game_bets set gb_betinfo='' where gb_id BETWEEN {$start} and {$end}";
                Db::execute($sql);
                Db::commit();
            } catch (Exception $e) {
                Db::rollback();
                $output->error("迁移错误 - " . Carbon::now() );
                $output->error($e->getMessage());
                break;
            }
            sleep(1);
            $start += $size;
            $end += $size;
        }
        $output->writeln("done - " . Carbon::now());
    }

    private function createTable() {
        // 判断表是否已存在
        if(!$this->existTableFullName("dq_game_betsex")){
            $sql = "CREATE TABLE dq_game_betsex (
  gbe_id int(11) NOT NULL COMMENT '注单ID',
  gbe_inmonth int(11) DEFAULT NULL COMMENT '切表所属月份',
  gbe_betinfo longtext COMMENT '注单原始信息',
  PRIMARY KEY (gbe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='注单扩展信息表';";
            Db::execute($sql);
        }
    }

    public function existTableFullName($full_table_name, $config = "") {
        $exist = Db::connect($config)->query("show tables like '{$full_table_name}'");
        return empty($exist) ? false : true;
    }
}