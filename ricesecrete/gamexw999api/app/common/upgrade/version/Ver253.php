<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver253 extends VersionBase {

    public function run() {
        // 三方平台API补充参数模板表，用于某些需要动态手动输入参数的情况，此表中的数据将会合并到原API参数中（覆盖）
        if (!$this->existTable("dq_game_apis_extpl")) {
            $sql = <<<SQL
CREATE TABLE `dq_game_apis_extpl`  (
  `gae_id` int NOT NULL AUTO_INCREMENT,
  `gae_tcid` int NOT NULL COMMENT '平台ID',
  `gae_gid` int NOT NULL DEFAULT 0 COMMENT '游戏ID，0为通用',
  `gae_apiname` varchar(32)  NOT NULL COMMENT 'API名',
  `gae_forminfo` varchar(1000)  NULL DEFAULT NULL COMMENT '填写此模板数据时的提示性文字，将出现在使用此模板的表单上',
  `gae_param_gettpl` varchar(1000)  NULL DEFAULT NULL COMMENT '补充的URL参数模板,querystring格式',
  `gae_param_posttpl` varchar(4000)  NULL DEFAULT NULL COMMENT '补充的POST参数模板,JSON格式',
  `gae_param_headtpl` varchar(4000)  NULL DEFAULT NULL COMMENT '补充的HEADER参数模板,JSON格式',
  PRIMARY KEY (`gae_id`) USING BTREE,
  UNIQUE INDEX `gae_gid`(`gae_gid`,`gae_apiname`,`gae_tcid`) USING BTREE,
  INDEX `gae_tcid`(`gae_tcid` ASC) USING BTREE,
  INDEX `gae_apiname`(`gae_apiname` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3  COMMENT = '三方平台API补充参数模板表，用于某些需要动态手动输入参数的情况，此表中的数据将会合并到原API参数中（覆盖）' ROW_FORMAT = Dynamic;
SQL;
            $this->execute($sql);
        }

        // 赔送用户三方平台免费游戏记录表
        if (!$this->existTable("dq_user_freebets")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_freebets`  (
  `ufg_id` int NOT NULL AUTO_INCREMENT COMMENT '同时作为给三方平台的唯一标识,标签为freesn',
  `ufg_uid` int NOT NULL COMMENT '用户ID',
  `ufg_tcid` int NOT NULL COMMENT '平台ID',
  `ufg_gid` int NOT NULL COMMENT '游戏ID',
  `ufg_intime` int NOT NULL COMMENT '赠送时间，此值会用于附加参数模板，标签为freebetStarttime',
  `ufg_state` int NULL DEFAULT 0 COMMENT '赠送状态 0失败 1成功',
  `ufg_endfreetime` int NULL DEFAULT 0 COMMENT '有效期，此值会用于附加参数模板，标签为 freebetEndtime 0表示不限制',
  `ufg_pricecount` decimal(20, 4) NULL DEFAULT NULL COMMENT '单局游戏总赠送投注金额，需要手动填写',
  `ufg_usedprice` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT '某些游戏一局有多注的时候可能需要用到字个字段记录奖励余额',
  `ufg_betsinfo` varchar(4000)  NULL DEFAULT NULL COMMENT '投注信息，根据API模板写入',
  `ufg_usedbygbsn` varchar(64)  NULL DEFAULT NULL COMMENT '核销GBSN',
  `ufg_staffid` int NULL DEFAULT NULL COMMENT '操作管理员ID',
  PRIMARY KEY (`ufg_id`) USING BTREE,
  INDEX `ufg_uid`(`ufg_uid` ASC) USING BTREE,
  INDEX `ufg_tcid`(`ufg_tcid` ASC) USING BTREE,
  INDEX `ufg_gid`(`ufg_gid` ASC) USING BTREE,
  INDEX `ufg_intime`(`ufg_intime` ASC) USING BTREE,
  INDEX `ufg_pricecount`(`ufg_pricecount` ASC) USING BTREE,
  INDEX `ufg_usedbygbsn`(`ufg_usedbygbsn` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 62 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT = '赔送用户三方平台免费游戏记录表' ROW_FORMAT = Dynamic;
SQL;
            $this->execute($sql);
        }

        //赠送免费游戏核销注单表
        if (!$this->existTable("dq_user_freebets_used")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_freebets_used` (
`ufu_id` int NOT NULL AUTO_INCREMENT,
`ufu_ufgid` int NOT NULL COMMENT '免费游戏赠送ID',
`ufu_gbid` int DEFAULT '0' COMMENT '核销注单ID',
`ufu_gbsn` varchar(64) NOT NULL COMMENT '核销注单SN',
`ufu_overtime` int DEFAULT NULL COMMENT '核销时间冗余gb_overtime',
`ufu_tcid` int NOT NULL COMMENT '三方平台ID冗余',
`ufu_gid` int NOT NULL COMMENT '游戏ID冗余',
`ufu_uid` int NOT NULL COMMENT '用户ID冗余',
`ufu_winprice` decimal(20,4) DEFAULT '0.0000' COMMENT '赢金额',
PRIMARY KEY (`ufu_id`) USING BTREE,
UNIQUE KEY `ufu_ufgid` (`ufu_ufgid`,`ufu_gbsn`),
KEY `ufu_gbid` (`ufu_gbid`),
KEY `ufu_gbsn` (`ufu_gbsn`),
KEY `ufu_winprice` (`ufu_winprice`),
KEY `ufu_tcid` (`ufu_tcid`),
KEY `ufu_gid` (`ufu_gid`),
KEY `ufu_uid` (`ufu_uid`),
KEY `ufu_overtime` (`ufu_overtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='赠送免费游戏核销注单表';
SQL;
            $this->execute($sql);
        }


        //配置菜单
        $gameNodeId = $this->getNodeIdByNodeUrl('/games');
        if( $gameNodeId ){
            $freeBetNodeId = $this->getNodeIdByNodeUrl('/freebet');
            if( !$freeBetNodeId ){
                $freeBetNodeId = $this->addPublicParentNode(
                    '免费游戏赠送列表', '/freebet/list',
                    'freebetlist', '/freebet/list',
                    $gameNodeId, 7, false,0
                );
                //赠送免费游戏
                $this->addPublicParentNode(
                    '免费游戏赠送', 'give',
                    'give', 'give',
                    $freeBetNodeId, 15
                );
                //导出
                $this->addPublicParentNode(
                    '导出', 'export',
                    'export', 'export',
                    $freeBetNodeId
                );
            }
        }
        //赠送局数
        if (!$this->existTableField("dq_user_freebets", "ufg_freerounds")) {
            $sql = "ALTER TABLE `dq_user_freebets` ADD COLUMN `ufg_freerounds` tinyint(3) NOT NULL DEFAULT 1 COMMENT '赠送局数，默认1，此值会用于附加参数模板，标签为freebetRounds';";
            $this->execute($sql);
        }
        //赠送总金额
        if (!$this->existTableField("dq_user_freebets", "ufg_roundspricecount")) {
            $sql = "ALTER TABLE `dq_user_freebets` ADD COLUMN `ufg_roundspricecount` decimal(20, 4) NOT NULL DEFAULT 0 COMMENT '总赠送金额=局数*单局金额，自动计算';";
            $this->execute($sql);
        }
        //自定义配置保存字段
        if (!$this->existTableField("dq_game_apis_extpl", "gae_cfg")) {
            $sql = "ALTER TABLE `dq_game_apis_extpl` ADD COLUMN `gae_cfg` varchar(4000) DEFAULT NULL COMMENT '自定义配置保存字段,null或JSON';";
            $this->execute($sql);
        }
        //自定义配置保存字段
        if (!$this->existTableField("dq_user_freebets", "ufg_usedrounds")) {
            $sql = "ALTER TABLE `dq_user_freebets` ADD COLUMN `ufg_usedrounds` int(10) DEFAULT 0 NULL COMMENT '核销注数';";
            $this->execute($sql);
        }
        //赠送免费游戏时的单注倍率
        if (!$this->existTableField("dq_game_configs", "gc_freebet_rate")) {
            $sql = "ALTER TABLE `dq_game_configs` ADD COLUMN `gc_freebet_rate` int(11) NOT NULL DEFAULT 0 NULL COMMENT '赠送免费游戏时的单注倍率';";
            $this->execute($sql);
        }

    }

}
