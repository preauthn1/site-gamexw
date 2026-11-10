<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver228 extends VersionBase
{

    public function run()
    {
        //最低出款金额
        if (!$this->existTableField("dq_user_withdrawal", "is_lock")){
            $sql = "ALTER TABLE `dq_user_withdrawal` ADD COLUMN `is_lock` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否锁定（1锁定，0解锁）';";
            $this->execute($sql);
        }

        //提现锁定或解锁
        $nodeId = $this->getNodeIdByNodeUrl('/offline/userwithdrawal');
        if ($nodeId) {
            $_nodeId = $this->getNodeIdByNodeUrlPid('lock', $nodeId);
            if (!$_nodeId) {
                $this->addPublicParentNode(
                    '锁定/解锁', 'lock',
                    'lock', 'lock',
                    $nodeId
                );
            }
        }
    }
}