<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver263 extends VersionBase
{

    public function run()
    {
        //游戏前端展示平台类型
        if (!$this->existTable("dq_third_configs_web")) {
            $sql = <<<SQL
CREATE TABLE `dq_third_configs_web` (
  `tc_id` int NOT NULL AUTO_INCREMENT,
  `tc_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '三方平台名称',
  `tc_status` int DEFAULT '1' COMMENT '0:弃用 1：启用',
  `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
  `icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '平台logo',
  `simple_icon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '图标',
  PRIMARY KEY (`tc_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方平台前端展示配置表';
SQL;
            $this->execute($sql);
        }
        $zgNodeId = $this->getNodeIdByNodeUrl('/threeGames');
        if ($zgNodeId) {
            $NodeId = $this->getNodeIdByNodeUrl('/threeGames/gamePlatformWeb');
            if (empty($NodeId)) {
                //前端展示平台
                $CNodeId = $this->addPublicParentNode(
                    '前端展示平台',
                    '/threeGames/gamePlatformWeb',
                    'gamePlatformWeb',
                    '/threeGames/gamePlatformWeb',
                    $zgNodeId,
                    4
                );
                $this->addPublicChildrenMenuNode($CNodeId);
            }
        }
    }
}