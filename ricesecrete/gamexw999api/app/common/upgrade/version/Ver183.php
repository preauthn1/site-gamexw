<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver183 extends VersionBase
{

    public function run()
    {
        //用户三方表rtp
        if (!$this->existTableField("dq_user_thirds", "ut_rtp")) {
            $sql = "ALTER TABLE `dq_user_thirds` ADD COLUMN `ut_rtp` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '用户rtp';";
            $this->execute($sql);
        }
        //菜单增加rtp权限控制
        $thirdNodeId = $this->getNodeIdByNodeUrl('/threeGames/gamePlatform');
        if( $thirdNodeId ){
            $rtpNodeId = $this->getNodeIdByNodeUrl('rtp');
            if( !$rtpNodeId ){
                $in_all = [
                    'node_name' => 'RTP管理',
                    'node_url' => 'rtp',
                    'path' => 'rtp',
                    'name' => 'rtp',
                    'pid' => $thirdNodeId,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 1,
                ];
                Db::name('admin_node')->insert($in_all);
            }
        }

    }
}