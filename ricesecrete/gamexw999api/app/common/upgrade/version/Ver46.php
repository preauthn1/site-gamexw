<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;
use think\exception\PDOException;

class Ver46 extends VersionBase {

    public function run() {            
        if (!$this->existTableField("dq_system", "agent_rate")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `agent_rate` DECIMAL(10,2) DEFAULT 0.00 NULL COMMENT '代理分佣（只针对三方游戏）/100';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_agent", "rec_id")) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `rec_id` INT(10) DEFAULT 0 NULL COMMENT '上级ID';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_agent", "tree")) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `tree` VARCHAR(255) DEFAULT '' NULL COMMENT '关系树';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_agent", "rate")) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `rate` DECIMAL(10,2) DEFAULT 0.00 NULL COMMENT '收益率，需要/100';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_user_agent", "type")) {
            $sql = "ALTER TABLE `dq_user_agent` ADD COLUMN `type` TINYINT(1) DEFAULT 0 NULL COMMENT '类型 0 外部 1 内部'; ";
            $this->execute($sql);
        }
        
        if (!$this->existTableIndex("dq_user_agent", "idx_rec_id")) {
            $sql="ALTER TABLE `dq_user_agent` ADD INDEX `idx_rec_id` (`rec_id`);";
            $this->execute($sql);
        }        
        
        if (!$this->existTableIndex("dq_user_agent", "idx_tree")) {
            $sql="ALTER TABLE `dq_user_agent` ADD INDEX `idx_tree` (`tree`);";
            $this->execute($sql);
        }        
    }
}
