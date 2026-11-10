<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver234 extends VersionBase
{

    public function run()
    {
        if (!$this->existTable("dq_user_address")) {
            $sql = "CREATE TABLE `dq_user_address`  (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
                  `pay_id` int(11) NOT NULL DEFAULT 0 COMMENT '支付渠道id',
                  `address` varchar(100)  NOT NULL DEFAULT '' COMMENT '收币地址',
                  `qr_code` varchar(150)  NOT NULL DEFAULT '' COMMENT '二维码',
                  `time` int(11) NOT NULL DEFAULT 0,
                  PRIMARY KEY (`id`) USING BTREE
                ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT = '用户货币对应收币地址' ROW_FORMAT = Dynamic;";
            $this->execute($sql);
        }


        if ($this->existTableField("dq_user_offline", "othr_number")){
            $sql = "ALTER TABLE `dq_user_offline` 
MODIFY COLUMN `othr_number` varchar(100)  NULL DEFAULT '' COMMENT '第三方订单编号';";
            $this->execute($sql);
        }

        if ($this->existTableField("dq_user_withdrawal", "pay_order_sn")){
            $sql = "ALTER TABLE `dq_user_withdrawal` 
MODIFY COLUMN `pay_order_sn` varchar(100)  NULL DEFAULT '' COMMENT '三方订单号';";
            $this->execute($sql);
        }
    }
}