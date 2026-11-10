<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver58 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_agent_setting", "name")) {
            $sql = "ALTER TABLE `dq_agent_setting` ADD COLUMN `name` varchar(100) NULL COMMENT '名称';";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_agent_setting", "icon")) {
            $sql = "ALTER TABLE `dq_agent_setting` ADD COLUMN `icon` varchar(500) NULL COMMENT '图标';";
            $this->execute($sql);
        }


        if (!$this->existTableField("dq_temp_fyfy_proxy_users", "tfpu_comlvl")) {
            $sql = "ALTER TABLE dq_temp_fyfy_proxy_users
                    ADD COLUMN tfpu_comlvl int(11) NULL DEFAULT 0 COMMENT '相对级别，用于按级别进行分佣时的统计' AFTER tfpu_scdm,
                    ADD INDEX(tfpu_comlvl);";
            $this->execute($sql);

            $sql = "ALTER TABLE dq_third_stats_fyfylog DROP INDEX sffl_dw, ADD INDEX sffl_dw(sffl_dw, sffl_uid) USING BTREE;";
            $this->execute($sql);
        }
        if (!$this->existTableField("dq_third_stats_fyfy", "sff_bet")) {
            $sql = "ALTER TABLE dq_third_stats_fyfy ADD COLUMN sff_bet decimal(20, 8) NULL DEFAULT 0 COMMENT '有效投注额，用于外部用户代理返佣' AFTER sff_prelosetotal;";
            $this->execute($sql);
        }

    }
}