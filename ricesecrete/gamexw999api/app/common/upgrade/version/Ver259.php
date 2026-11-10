<?php

namespace app\common\upgrade\version;

use think\facade\Db;
use function Symfony\Component\Translation\t;

class Ver259 extends VersionBase
{

    public function run()
    {
        //添加指定流水索引
        if ($this->existTableField("dq_user_award_logs", "ual_awardid") && $this->existTableField("dq_user_award_logs", "ual_gid")){
            //获取索引列表，判断索引不存在则添加索引
            $indexArr = $this->getIndexs('dq_user_award_logs');
            if( !in_array('ual_dis', $indexArr) ){
                $sql = "ALTER TABLE dq_user_award_logs ADD INDEX ual_dis(ual_awardid, ual_gid);";
                $this->execute($sql);
            }
        }

        //
        if (!$this->existTableField("dq_third_configs", "tc_exfield")) {
            $sql = "ALTER TABLE `dq_third_configs` ADD COLUMN `tc_exfield` varchar(1000) NULL COMMENT '扩展信息存放字段，比如自已拼接游戏地址的URL前缀等，自定义，建议使用JSON';";
            $this->execute($sql);
        }


    }
}