<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver96 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_game_bets", "gb_otherwin")) {
            $sql = <<<SQL
ALTER TABLE `dq_game_bets`
ADD COLUMN `gb_otherwin` decimal(20, 2) NULL DEFAULT 0 COMMENT '统计输赢金额（派彩-投注）',
ADD COLUMN `gb_validwater` decimal(20, 2) NULL DEFAULT 0 COMMENT '统计有效流水 if(派彩>投注，投注，投注-派彩）';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_third_stats_users", "su_validwater")) {
            $sql = <<<SQL
ALTER TABLE `dq_third_stats_users`
ADD COLUMN `su_validwater` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效流水';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_third_stats_users_temp", "su_validwater")) {
            $sql = <<<SQL
ALTER TABLE `dq_third_stats_users_temp`
ADD COLUMN `su_validwater` decimal(20, 2) NULL DEFAULT 0 COMMENT '有效投注额';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_third_stats_users", "su_otherwin")) {
            $sql = <<<SQL
ALTER TABLE `dq_third_stats_users`
MODIFY COLUMN `su_win` decimal(20, 2) NULL DEFAULT 0.00 COMMENT '派彩金额',
ADD COLUMN `su_otherwin` decimal(20, 2) NULL DEFAULT 0 COMMENT '用户输赢';
SQL;
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_third_stats_users_temp", "su_otherwin")) {
            $sql = <<<SQL
ALTER TABLE `dq_third_stats_users_temp`
MODIFY COLUMN `su_win` decimal(20, 2) NULL DEFAULT 0.00 COMMENT '派彩金额',
ADD COLUMN `su_otherwin` decimal(20, 2) NULL DEFAULT 0 COMMENT '用户输赢';
SQL;
            $this->execute($sql);
        }
        // 判断表是否已存在
        if(!$this->existTableFullName("dq_game_betsex")){
            $sql = "CREATE TABLE dq_game_betsex (
  gbe_id int(11) NOT NULL COMMENT '注单ID',
  gbe_inmonth int(11) DEFAULT NULL COMMENT '切表所属月份',
  gbe_betinfo longtext COMMENT '注单原始信息',
  PRIMARY KEY (gbe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='注单扩展信息表';";
            Db::execute($sql);
        }
    }
}