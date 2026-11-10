<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver219 extends VersionBase
{
    public function run()
    {
        //人工调整导入
        $nodeId = $this->getNodeIdByNodeUrl('/offline/useradjust');
        if( $nodeId ){
            $_node_id = $this->getNodeIdByNodeUrlPid('import', $nodeId);
            if( !$_node_id ){
                $this->addPublicParentNode(
                    '导入', 'import',
                    'import', 'import',
                    $nodeId
                );
            }
        }
    }
}