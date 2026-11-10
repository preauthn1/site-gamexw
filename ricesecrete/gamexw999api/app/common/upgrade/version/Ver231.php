<?php

namespace app\common\upgrade\version;
use think\facade\Db;

class Ver231 extends VersionBase
{
    public function run()
    {
        if ( !$this->existTable("dq_user_level_down") ) {
            $sql = "CREATE TABLE `dq_user_level_down` (
            `id` int NOT NULL AUTO_INCREMENT,
    `level_id` int NOT NULL COMMENT '等级ID',
    `recharge_amount` decimal(20,2) DEFAULT '0.00' COMMENT '累积充值金额',
    `water` decimal(20,2) DEFAULT '0.00' COMMENT '游戏流水',
    PRIMARY KEY (`id`) USING BTREE,
    KEY `idx_level_id` (`level_id`)
  ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COLLATE=utf8mb4_unicode_ci COMMENT='用户等级保级表';";
            $this->execute($sql);

            $ret = Db::name('user_level')->column('id');
            $insertvue="";
            foreach($ret as &$val){
                $insertvue.='('.$val.','.$val.',0.00,0.00),';
            }
            $insertvue=rtrim($insertvue,',');
            $sql="INSERT INTO `dq_user_level_down` (`id`, `level_id`, `recharge_amount`, `water`)VALUES ".$insertvue.";";
            $this->execute($sql);
        }
        
        if ( !$this->existTable("dq_user_level_down_record") ) {
            $sql = "CREATE TABLE `dq_user_level_down_record` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `uid` int NOT NULL COMMENT '用户ID',
    `level_id` int NOT NULL COMMENT '当前VIP等级（降级后）',
    `old_level_id` int NOT NULL COMMENT '原来VIP等级（降级前）',
    `time` int DEFAULT NULL COMMENT '降级时间',
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COLLATE=utf8mb4_unicode_ci COMMENT='用户降级记录表';";

            $this->execute($sql);
        }

        $zgNodeId = $this->getNodeIdByNodeUrl('/user');
        if ($zgNodeId) {
            $NodeId = $this->getNodeIdByNodeUrl('/user/pages/levelDownRecord');
            if (empty($NodeId)) {
                $NodeId = $this->addPublicParentNode(
                    '会员等级降级',
                    '/user/pages/levelDownRecord',
                    'levelDown',
                    '/user/pages/levelDownRecord',
                    $zgNodeId,
                    3, false, 0
                );

                $this->addPublicParentNode(
                    '降级记录',
                    '/user/pages/levelDownRecord',
                    'levelDownRecord',
                    '/user/pages/levelDownRecord',
                    $NodeId,
                    1, false, 0
                );
            }
            $_node = $this->getNodeIdByNodeUrl('/user/pages/levelDown');
            if( !$_node ){
                $CNodeId = $this->addPublicParentNode(
                    '保级设置',
                    '/user/pages/userLevelDown',
                    'userLevelDown',
                    '/user/pages/userLevelDown',
                    $NodeId,
                    2, false, 0
                );
                $_node = $this->getNodeIdByNodeUrlPid('edit', $CNodeId);
                if (empty($_node)) {
                    $in_all = [
                        'node_name' => '修改',
                        'node_url' => 'edit',
                        'path' => 'edit',
                        'name' => 'edit',
                        'pid' => $CNodeId,
                        'sort' => 0,
                        'create_time' => time(),
                        'update_time' => time(),
                        'is_menu' => 1,
                    ];
                    Db::name('admin_node')->insert($in_all);
                }
            }
        }

        //保级增加周期
        if (!$this->existTableField("dq_user_level_down", "down_cycle")){
            $sql = "ALTER TABLE `dq_user_level_down` ADD COLUMN `down_cycle` tinyint(1) NOT NULL DEFAULT 2 COMMENT '保级周期，0每天，1每周，2每月';";
            $this->execute($sql);
        }
        //保级增加充值方式
        if (!$this->existTableField("dq_user_level_down", "type")){
            $sql = "ALTER TABLE `dq_user_level_down` ADD COLUMN `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '充值方式，0累计金额，1单笔金额';";
            $this->execute($sql);
        }
    }
}