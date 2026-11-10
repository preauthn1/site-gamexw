<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver100 extends VersionBase {

    public function run() {
        // 用户在线表
        if (!$this->existTable("dq_user_online")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_online` (
`uo_uid` int(11) NOT NULL COMMENT '用户id',
  `uo_stime` int(11) NOT NULL DEFAULT '0' COMMENT '最近上线时间',
  `uo_etime` int(11) NOT NULL DEFAULT '0' COMMENT '最近下线时间',
  `uo_onstate` int(11) NOT NULL DEFAULT '0' COMMENT '在线状态 0下线 1在线 2疑似下线',
  `uo_ontime` int(11) NOT NULL DEFAULT '0' COMMENT '最近在线时长',
  `uo_nowpage` varchar(255) DEFAULT NULL COMMENT '最后所在页面',
  `uo_nowpagetime` int(11) DEFAULT '0' COMMENT '最后页面进入时间',
  `uo_nowgame` int(11) DEFAULT NULL COMMENT '最后进入游戏',
  `uo_nowgametime` int(11) DEFAULT '0' COMMENT '最后游戏进入时间',
  `uo_nowstate` int(11) DEFAULT '0' COMMENT '当前/最后所处项目 0未知 1页面 2游戏中',
  `uo_intime` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `uo_uptime` int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
  PRIMARY KEY (`uo_uid`) USING BTREE,
  KEY `uo_nowpage` (`uo_nowpage`),
  KEY `uo_nowgame` (`uo_nowgame`),
  KEY `uo_onstate` (`uo_onstate`),
  KEY `uo_nowstate` (`uo_nowstate`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户在线表';
SQL;
            $this->execute($sql);
        }
        // 用户在线实时日志临时表
        if (!$this->existTable("dq_user_online_temp")) {
            $sql = <<<SQL
CREATE TABLE `dq_user_online_temp` (
`uot_uid` int(11) NOT NULL COMMENT '用户id',
  `uot_nowpage` varchar(255) DEFAULT NULL COMMENT '所在页面，offline为特殊指令，表示下线',
  `uot_nowpagetime` int(11) NOT NULL DEFAULT '0' COMMENT '页面进入时间',
  `uot_nowgame` int(11) DEFAULT NULL COMMENT '进入游戏',
  `uot_nowgametime` int(11) NOT NULL DEFAULT '0' COMMENT '游戏进入时间',
  `uot_intime` int(11) NOT NULL DEFAULT '0' COMMENT '时间',
  PRIMARY KEY (`uot_uid`) USING BTREE
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户在线实时日志临时表';
SQL;
            $this->execute($sql);
        }

        //创建用户列表节点
        $pid = $this->getNodeIdByName('会员管理');
        if( $pid ){
            //领取码列表
            $this->addPublicParentNode(
                '在线用户',
                '/user/pages/onlineUserList',
                'onlineUserList',
                '/user/pages/onlineUserList',
                $pid,
                2
            );
        }

        //添加总在线时长
        if (!$this->existTableField("dq_user_online", "uo_ontimecnt")){
            $sql = "ALTER TABLE dq_user_online ADD COLUMN uo_ontimecnt int(11) NULL DEFAULT 0 COMMENT '总在线时长（包括疑似下线的时间）' AFTER uo_nowstate,
ADD COLUMN uo_ontimecntreal int(11) NULL DEFAULT 0 COMMENT '总在线时长（不包括疑似下线的时间）' AFTER uo_ontimecnt;";
            $this->execute($sql);
        }
    }
}