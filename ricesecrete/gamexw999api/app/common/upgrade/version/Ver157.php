<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;
use think\facade\Db;

class Ver157 extends VersionBase
{

    public function run()
    {
        //导出数据包
        if (!$this->existTable("dq_export_reports")) {
            $sql = <<<SQL
CREATE TABLE `dq_export_reports` (
`er_id` int(11) NOT NULL AUTO_INCREMENT,
`er_muid` int(11) DEFAULT '0' COMMENT '管理员ID',
`er_intime` int(11) DEFAULT NULL COMMENT '任务写入时间',
`er_filename` varchar(255) DEFAULT NULL COMMENT '文件名前缀',
`er_downloadurl` varchar(1000) DEFAULT '' COMMENT '下载文件名',
`er_process` int(255) DEFAULT '0' COMMENT '已处理量',
`er_datacount` int(255) DEFAULT '0' COMMENT '数据总量',
`er_started` int(255) DEFAULT '0' COMMENT '0未开始/排队中 1已开始',
`er_ended` int(255) DEFAULT '0' COMMENT '0未完成 1已完成 2已打包 3已上传',
`er_endtime` int(11) DEFAULT '0' COMMENT '数据生成完成时间',
`er_invalidtime` int(11) DEFAULT '0' COMMENT '失效时间',
`er_taskjson` text NOT NULL COMMENT '任务包信息',
PRIMARY KEY (`er_id`),
KEY `er_ended` (`er_ended`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SQL;
            $this->execute($sql);
        }

        $systemNodeId = $this->getNodeIdByNodeUrl('/system');
        if ($systemNodeId) {
            $exportNodeId = $this->getNodeIdByNodeUrl('/report/exportreports');
            if (empty($exportNodeId)) {
                //导出数据包列表
                $exportNodeId = $this->addPublicParentNode(
                    '导出数据包列表',
                    '/report/exportreports',
                    'exportreports',
                    '/report/exportreports',
                    $systemNodeId,
                    10
                );
                $add = [
                    'node_name' => '下载',
                    'node_url' => 'download',
                    'path' => 'download',
                    'name' => 'download',
                    'pid' => $exportNodeId,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time()
                ];
                Db::name('admin_node')->insert($add);
            }

        }
    }
}