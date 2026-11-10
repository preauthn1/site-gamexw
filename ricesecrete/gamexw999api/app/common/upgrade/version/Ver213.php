<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver213 extends VersionBase
{
    public function run()
    {

        //配置菜单
        $userNodeId = $this->getNodeIdByNodeUrl('/user');
        if( $userNodeId ){
            $userWaterNodeId = $this->getNodeIdByNodeUrlPid('/user/pages/userWaters', $userNodeId);
            if( !$userWaterNodeId ){
                $this->addPublicParentNode(
                    '会员流水', '/user/pages/userWaters',
                    'userWaters', '/user/pages/userWaters',
                    $userNodeId, 3
                );
            }
        }

    }
}