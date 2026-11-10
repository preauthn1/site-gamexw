<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver148 extends VersionBase
{

    public function run() {
        //多时区表
        if (!$this->existTable("dq_third_stats_global_temp_js")) {
            $sql = "create table dq_third_stats_users_temp_js like dq_third_stats_users_temp;";
            $this->execute($sql);
        }
        //多时区表
        if (!$this->existTable("dq_third_stats_global_temp_js")) {
            $sql = "create table dq_third_stats_global_temp_js like dq_third_stats_global_temp;";
            $this->execute($sql);
        }
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'COUNT_DATA_TIMEZONE_LIST') ){
            //统计数据时区列表
            $this->addSysSets(
                'COUNT_DATA_TIMEZONE_LIST', '统计数据时区列表', 'DEFAULT|-3:00', 'list', '基础设置', '格式：时区标志|时区+-值，每一个时区都会生成一张后缀为时区代码的表（包括切表） 默认表不带后缀，不设置此配置时DEFAULT的时候按env.mysql_timezone，+0：00顺序取值，设置时按env.mysql_timezone,此设置值顺序取值'
            );
        }
    }

}