<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver209 extends VersionBase
{
    public function run()
    {
        if (!$this->existTable("dq_wufu_content")) {
            $sql = <<<SQL
CREATE TABLE `dq_wufu_content`  (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `w_id` int(11) NOT NULL COMMENT '五福id',
  `lang` varchar(50)  NOT NULL COMMENT '语言',
  `name` varchar(200)  NOT NULL COMMENT '名称',
  `image` varchar(500)  NULL DEFAULT NULL COMMENT '封面',
  `losing_lottery_img` varchar(500)  NULL DEFAULT NULL COMMENT '未中奖图片',
  `intro` varchar(255)  NULL DEFAULT NULL COMMENT '简介',
  `introduce` longtext  NULL COMMENT '规则',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `lang`(`lang`) USING BTREE,
  INDEX `w_id`(`w_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT = '五福内容';
SQL;
            $this->execute($sql);
        }

        //配置菜单
        $wufuNodeId = $this->getNodeIdByNodeUrl('/wufu/wufuList');
        if( $wufuNodeId ){
            $contentNodeId = $this->getNodeIdByNodeUrlPid('content', 'content');
            if( !$contentNodeId ){
                $this->addPublicParentNode(
                    '内容设置', 'content',
                    'content', 'content',
                    $wufuNodeId, 1, true
                );
            }
        }
    }
}