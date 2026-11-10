<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver106 extends VersionBase {

    public function run() {
        $thirdGameNodeId = $this->getNodeIdByNodeUrl('/threeGames');
        if($thirdGameNodeId){
            $thirdLogNodeId = $this->getNodeIdByNodeUrl('/thirdlog/list');
            if( !$thirdLogNodeId ){
                //平台日志
                $thirdLogNodeId = $this->addPublicParentNode(
                    '平台日志',
                    '/thirdlog/list',
                    'thirdLogList',
                    '/thirdlog/list',
                    $thirdGameNodeId,
                    90
                );
                //重新处理
                $this->addPublicParentNode(
                    '重新处理',
                    'handle',
                    'handle',
                    'handle',
                    $thirdLogNodeId
                );
            }
        }
    }
}