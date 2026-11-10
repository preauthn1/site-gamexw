<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver17 extends VersionBase {

    public function run() {
        // 版本升级自动执行代码

        // 判断表字段是否已存在
        if (!$this->existTableField("dq_gonggao", "sort")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_gonggao` ADD COLUMN `sort` int(11) DEFAULT 0 COMMENT '排序 数值越大越靠前';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_system", "bold_filter")) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `bold_filter` int(10) DEFAULT 0 COMMENT '波胆投注选项过滤,例如：4 则大于等于4以上的选项均不显示 为空则全部显示';";
            $this->execute($sql);
        }

    }

}
