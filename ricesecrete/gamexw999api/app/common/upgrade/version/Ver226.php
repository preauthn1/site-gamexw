<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver226 extends VersionBase
{

    public function run()
    {
        $nodeId = $this->getNodeIdByNodeUrl('/offline/useroffline');
        if ($nodeId) {
            $_nodeId = $this->getNodeIdByNodeUrlPid('set_remarks', $nodeId);
            if (!$_nodeId) {
                $this->addPublicParentNode(
                    '自定义备注', 'set_remarks',
                    'set_remarks', 'set_remarks',
                    $nodeId
                );
            }
        }

        //复充人数
        if (!$this->existTableField("dq_operating_agent", "again_recharge_amount")){
            $sql = "ALTER TABLE `dq_operating_agent` ADD COLUMN `again_recharge_amount` int(11) NOT NULL DEFAULT 0 COMMENT '复充人数';";
            $this->execute($sql);
        }

        //杀率
        if (!$this->existTableField("dq_operating_agent", "betting_loss_rate")){
            $sql = "ALTER TABLE `dq_operating_agent` ADD COLUMN `betting_loss_rate` decimal(10, 2) NOT NULL DEFAULT 0 COMMENT '杀率';";
            $this->execute($sql);
        }
    }
}