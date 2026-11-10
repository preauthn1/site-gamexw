<?php

namespace app\common\upgrade\version;

use think\facade\Db;
use function Symfony\Component\Translation\t;

class Ver257 extends VersionBase
{

    public function run()
    {
        //队列状态
        if (!$this->existTableField("dq_action_apply", "queue_status")) {
            //队列状态
            $sql = "ALTER TABLE `dq_action_apply` ADD COLUMN `queue_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '队列状态（0正常1实名认证阻断2剩余彩金阻断，其他看模型）';";
            $this->execute($sql);
        }
    }
}