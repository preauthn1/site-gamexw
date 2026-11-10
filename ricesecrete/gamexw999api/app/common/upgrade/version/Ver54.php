<?php

namespace app\common\upgrade\version;
use app\common\model\AdminNodeOldModel;
use app\common\upgrade\version\VersionBase;
use think\facade\Db;

class Ver54 extends VersionBase {

    public function run() {
        if (!$this->existTableField("dq_system", "is_reg_type")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `is_reg_type` INT(10) DEFAULT 0 NULL COMMENT '注册时是否需要填其他联系方式，0否 1手机 2邮箱';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_pay_set", "icon")) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `icon` varchar(255) NULL DEFAULT '' COMMENT '图标';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_pay_set", "remarks")) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `remarks` varchar(255) NULL DEFAULT '' COMMENT '备注';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_third_configs", "simple_icon")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `simple_icon` varchar(255) NULL DEFAULT '' COMMENT '图标';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_third_stats_fyfylog", "sffl_dw")) {
            $sql = "ALTER TABLE dq_third_stats_fyfylog 
ADD COLUMN sffl_dw int(11) NULL COMMENT '分佣数据周YYYYWW，在按周结算时使用' AFTER sffl_dt,
ADD INDEX(sffl_dw, sffl_uid);";
            $this->execute($sql);
            $sql1 = "ALTER TABLE dq_third_stats_fyfylog 
DROP INDEX sffl_m,
ADD INDEX sffl_m(sffl_dt, sffl_uid) USING BTREE;";
            $this->execute($sql1);
        }

        if (!$this->existTableField("dq_third_stats_fyfy", "sff_w")) {
            $sql = "ALTER TABLE dq_third_stats_fyfy 
ADD COLUMN sff_w int(11) NULL COMMENT '结算周YYYYWW，仅在按周结算时使用' AFTER sff_m,
DROP INDEX sff_m,
ADD INDEX sff_m(sff_m, sff_uid) USING BTREE,
ADD UNIQUE INDEX(sff_w, sff_uid);";
            $this->execute($sql);
        }
    }
}
