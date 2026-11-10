<?php

namespace app\common\upgrade\version;

class Ver94 extends VersionBase
{
    public function run() {
        if (!$this->existTableField("dq_lang", "web_status")) {
            $sql = "ALTER TABLE `dq_lang` ADD COLUMN `web_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '前端状态';";
            $this->execute($sql);
        }

        $this->addPublicParentNode(
            '回访管理',
            '/follow',
            'follow',
            '/follow',
            '0',
            '31'
        );


        $pid = $this->getNodeIdByName("回访管理");
        $this->addPublicParentNode(
            '会员报表',
            '/follow/userreport',
            'userReport',
            '/follow/userreport',
            $pid,
            1
        );
        $this->addPublicParentNode(
            '回访列表',
            '/follow/pendingfollow',
            'pendingfollow',
            '/follow/pendingfollow',
            $pid,
            2
        );
        $this->addPublicParentNode(
            '赠金列表',
            '/follow/distribute',
            'distribute',
            '/follow/distribute',
            $pid,
            3
        );
        $this->addPublicParentNode(
            '回访报表',
            '/follow/reports',
            'reports',
            '/follow/reports',
            $pid,
            4
        );

        $pid = $this->getNodeIdByName("会员报表");
        if($pid) {
            $full_table_name = 'dq_admin_node';
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `node_name` = '导出' AND `node_url` = 'export' AND `pid` = '{$pid}' ";
            if(!$this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}`(`node_name`, `node_url`, `pid`,`special_menu`, `create_time`, `update_time`, `path`, `name`, `status`) VALUES ('导出', 'export', '{$pid}', 'normal',   ".time().", ".time().", 'export', 'export', 1);";
                $this->execute($sql);
            }
        }

        $this->addPublicParentNode(
            '批量派发',
            'assign',
            'assign',
            'assign',
            $pid,
            2
        );

        $this->addPublicParentNode(
            '派发设置',
            'distributionconfig',
            'distributionconfig',
            'distributionconfig',
            $pid,
            3
        );

        $this->addPublicParentNode(
            '回访派发记录',
            'followlist',
            'followlist',
            'followlist',
            $pid,
            4
        );

        $pid = $this->getNodeIdByName("回访列表");
        $this->addPublicParentNode(
            '编辑',
            'save',
            'save',
            'save',
            $pid,
            1
        );

        $pid = $this->getNodeIdByName("赠金列表");
        $this->addPublicParentNode(
            '批量审核',
            'distributesave',
            'distributesave',
            'distributesave',
            $pid,
            1
        );

    }
}