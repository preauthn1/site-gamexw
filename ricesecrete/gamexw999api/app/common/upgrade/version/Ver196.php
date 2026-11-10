<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver196 extends VersionBase
{
    public function run()
    {
        //每周流水限制
        if( !$this->existTableField('dq_user_level', 'week_water') ){
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `week_water` decimal(20,2) DEFAULT '0.00' COMMENT '每周流水';";
            $this->execute($sql);
        }
        //每月流水限制
        if( !$this->existTableField('dq_user_level', 'month_water') ){
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `month_water` decimal(20,2) DEFAULT '0.00' COMMENT '每月流水';";
            $this->execute($sql);
        }
        //每日免费提现额度
        if( !$this->existTableField('dq_user_level', 'day_free_limit') ){
            $sql = "ALTER TABLE `dq_user_level` ADD COLUMN `day_free_limit` int(10) DEFAULT '0' COMMENT '每日出款免费次数';";
            $this->execute($sql);
        }
    }
}