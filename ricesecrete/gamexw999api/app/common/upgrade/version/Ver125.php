<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver125 extends VersionBase {

    public function run() {
        //赠送类型（0 下级注册赠送，1 下级充值赠送，2 充值赠送）
        if (!$this->existTableField("dq_pdd_turntable", "count_type")){
            $sql = "ALTER TABLE `dq_pdd_turntable` ADD COLUMN `count_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '赠送类型（0下级注册赠送，1下级充值赠送，2充值赠送）';";
            $this->execute($sql);
        }
        //赠送余额（范围用_隔开，例：10_20）
        if (!$this->existTableField("dq_pdd_turntable", "amount_range")){
            $sql = "ALTER TABLE `dq_pdd_turntable` ADD COLUMN `amount_range` varchar(255) NULL COMMENT '赠送余额（范围用_隔开，例：10_20）';";
            $this->execute($sql);
        }
        //赠送余额几率
        if (!$this->existTableField("dq_pdd_turntable", "amount_odds")){
            $sql = "ALTER TABLE `dq_pdd_turntable` ADD COLUMN `amount_odds` tinyint(3) NOT NULL DEFAULT 0 COMMENT '赠送余额几率';";
            $this->execute($sql);
        }

        //类型（0未知，1转盘余额。2钱包余额）
        if (!$this->existTableField("dq_pdd_invite_record", "type")){
            $sql = "ALTER TABLE `dq_pdd_invite_record` ADD COLUMN `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型（0未知，1转盘余额。2钱包余额）';";
            $this->execute($sql);
        }
        //转盘数量
        if (!$this->existTableField("dq_pdd_invite_record", "turntable_num")){
            $sql = "ALTER TABLE `dq_pdd_invite_record` ADD COLUMN `turntable_num` int(11) NOT NULL DEFAULT 1 COMMENT '转盘数量';";
            $this->execute($sql);
        }
        //已转数量
        if (!$this->existTableField("dq_pdd_invite_record", "finish_turntable_num")){
            $sql = "ALTER TABLE `dq_pdd_invite_record` ADD COLUMN `finish_turntable_num` int(11) NOT NULL DEFAULT 0 COMMENT '已转数量';";
            $this->execute($sql);
            //处理以前旧数据,status=1已经转的该值为1
            PddInviteRecordModel::where('status', '=', 1)->update([
                'finish_turntable_num' => 1
            ]);
        }

        //创建转盘记录表
        if (!$this->existTable("dq_pdd_turntable_record")) {
            $sql = <<<SQL
CREATE TABLE `dq_pdd_turntable_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `turntable_id` int(11) NOT NULL COMMENT '转盘id',
  `invite_record_id` int(11) NOT NULL COMMENT '邀请记录id',
  `uid` int(11) NOT NULL COMMENT '用户id',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型（0未知，1转盘余额。2钱包余额）',
  `amount` decimal(10, 2) NOT NULL COMMENT '抽奖金额',
  `withdrawal_amount` decimal(10, 2) NOT NULL COMMENT '取款金额',
  `is_withdrawal` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否取款（1已取款，0待取款）',
  `create_time` int(11) NOT NULL COMMENT '创建时间',
  `update_time` int(11) NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT = 1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PDD转盘记录';
SQL;
            $this->execute($sql);
            //添加原来数据
            $condition = [];
            $condition[] = ['status', '=', 1];
            $inviteRecordList = PddInviteRecordModel::where($condition)->select();
            $saveAll = [];
            foreach ($inviteRecordList as $v){
                $_time = is_numeric($v['update_time']) ? $v['update_time'] : strtotime($v['update_time']);
                $arr = [];
                $arr['turntable_id'] = $v['turntable_id'];
                $arr['invite_record_id'] = $v['id'];
                $arr['uid'] = $v['uid'];
                $arr['type'] = $v['type'];
                $arr['amount'] = $v['amount'];
                $arr['withdrawal_amount'] = $v['withdrawal_amount'];
                $arr['is_withdrawal'] = $v['is_withdrawal'];
                $arr['create_time'] = $_time;
                $arr['update_time'] = $_time;
                $saveAll[] = $arr;
            }
            (new PddTurntableRecordModel())->saveAll($saveAll);
        }

    }
}