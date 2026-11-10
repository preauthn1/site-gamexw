<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver128 extends VersionBase {

    public function run() {
        //前端平台id
        if (!$this->existTableField("dq_games", "g_web_tcid")){
            $sql = "ALTER TABLE `dq_games` ADD COLUMN `g_web_tcid` int(11) NOT NULL DEFAULT 0 COMMENT '前端平台id' after g_tcid;";
            $this->execute($sql);
            $sql = "UPDATE `dq_games` SET `g_web_tcid`=`g_tcid` WHERE `g_web_tcid`<>`g_tcid`";
            $this->execute($sql);
        }
    }
}