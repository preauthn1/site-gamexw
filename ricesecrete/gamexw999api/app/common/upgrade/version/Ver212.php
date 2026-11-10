<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver212 extends VersionBase
{
    public function run()
    {

        //配置菜单
        $userWithdrawalNodeId = $this->getNodeIdByNodeUrl('/offline/userwithdrawal');
        if( $userWithdrawalNodeId ){
            $remarksNodeId = $this->getNodeIdByNodeUrlPid('set_remarks', $userWithdrawalNodeId);
            if( !$remarksNodeId ){
                $this->addPublicParentNode(
                    '自定义备注', 'set_remarks',
                    'set_remarks', 'set_remarks',
                    $userWithdrawalNodeId
                );
            }
        }

    }
}