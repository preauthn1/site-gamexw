<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver121 extends VersionBase {

    public function run() {
        // 用户原VIP等级
        if (!$this->existTableField("dq_user", "original_level_id")){
            $sql = "ALTER TABLE `dq_user` ADD COLUMN `original_level_id` int(11) NOT NULL DEFAULT 0 COMMENT '原VIP等级';";
            $this->execute($sql);
        }
        //添加修改VIP和还原VIP节点
        $userNodeId = Db::name('admin_node')->where('node_url', '=', '/user/pages/list')->value('id');
        if( $userNodeId ){
            $vipEditNodeId = $this->getNodeIdByNodeUrl('vip_edit');
            if( !$vipEditNodeId ){
                $saveAll = [];
                //修改
                $saveAll[] = [
                    'node_name' => '修改VIP',
                    'node_url' => 'vip_edit',
                    'path' => 'vip_edit',
                    'name' => 'vip_edit',
                    'pid' => $userNodeId,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                //还原
                $saveAll[] = [
                    'node_name' => '还原VIP',
                    'node_url' => 'vip_reset',
                    'path' => 'vip_reset',
                    'name' => 'vip_reset',
                    'pid' => $userNodeId,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insertAll($saveAll);
            }

        }
    }
}