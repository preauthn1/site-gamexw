<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver200 extends VersionBase
{
    public function run()
    {
        if(!$this->existTable('dq_lottery_news')){
            $sql = <<<SQL
CREATE TABLE `dq_lottery_news` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lang` varchar(30) NOT NULL DEFAULT '' COMMENT '语言种类',
  `lottery_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联转盘Id',
  `news_title` varchar(255) NOT NULL DEFAULT '',
  `news_images` varchar(255) NOT NULL DEFAULT '',
  `news_intro` varchar(255) NOT NULL DEFAULT '',
  `news_content` mediumtext,
  `add_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='转盘简介';
SQL;
            $this->execute($sql);
        }
        if($this->existTable('dq_lottery_config')){
            //标题
            if (!$this->existTableField("dq_lottery_config",'title')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `title` varchar(100) DEFAULT '' COMMENT '标题';";
                $this->execute($sql);
            }
            //最高奖励
            if (!$this->existTableField("dq_lottery_config",'max_award')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `max_award` decimal(15,2) DEFAULT '0.00' COMMENT '最高奖励';";
                $this->execute($sql);
            }
            //主题类型
            if (!$this->existTableField("dq_lottery_config",'theme_type')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `theme_type` varchar(10) DEFAULT '' COMMENT '主题类型  silver银色，golden金色， violet紫色';";
                $this->execute($sql);
            }
            //语言
            if (!$this->existTableField("dq_lottery_config",'lang')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `lang` varchar(50) DEFAULT '' COMMENT '语言';";
                $this->execute($sql);
            }
            //任务条件多选
            if (!$this->existTableField("dq_lottery_config",'task_condition')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN  `task_condition` varchar(100) DEFAULT '' COMMENT '任务条件多选（0最低下注额，1最低充值，2最低倍数，3累计充值，4在线时长，5累计下注，6游戏时长）';";
                $this->execute($sql);
            }
            //任务数量
            if (!$this->existTableField("dq_lottery_config",'task_num')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN  `task_num` int(11) DEFAULT '0' COMMENT '任务数量';";
                $this->execute($sql);
            }
            //最低下注
            if (!$this->existTableField("dq_lottery_config",'min_bet')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN   `min_bet` decimal(20,2) DEFAULT '0.00' COMMENT '最低下注（奖励类型0时用到）';";
                $this->execute($sql);
            }
            //最低充值
            if (!$this->existTableField("dq_lottery_config",'min_recharge')) {
                $sql = "ALTER TABLE `dq_lottery_config`ADD COLUMN `min_recharge` decimal(20,2) DEFAULT '0.00' COMMENT '最低充值（奖励类型1时用到）';";
                $this->execute($sql);
            }
            //倍数
            if (!$this->existTableField("dq_lottery_config",'multiple')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `multiple` decimal(10,1) DEFAULT '0.0' COMMENT '倍数（奖励类型为2和3时用到）';";
                $this->execute($sql);
            }
            //累计下注
            if (!$this->existTableField("dq_lottery_config",'cumulative_bet')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN  `cumulative_bet` decimal(20,2) DEFAULT '0.00' COMMENT '累计下注';";
                $this->execute($sql);
            }
            //累计充值
            if (!$this->existTableField("dq_lottery_config",'cumulative_recharge')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN  `cumulative_recharge` decimal(20,2) DEFAULT '0.00' COMMENT '累计充值';";
                $this->execute($sql);
            }
            //邀请有效用户数量
            if (!$this->existTableField("dq_lottery_config",'invite_people')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN  `invite_people` int(11) DEFAULT '0' COMMENT '邀请有效用户数量'";
                $this->execute($sql);
            }
            //创建时间
            if (!$this->existTableField("dq_lottery_config",'create_time')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `create_time` int(11) DEFAULT '0' COMMENT '创建时间';";
                $this->execute($sql);
            }
            //更新时间
            if (!$this->existTableField("dq_lottery_config",'update_time')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `update_time` int(11) DEFAULT '0' COMMENT '更新时间';";
                $this->execute($sql);
            }
            //奖励免费次数
            if (!$this->existTableField("dq_lottery_config",'reward_free_count')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `reward_free_count` int(11) NOT NULL DEFAULT 1 COMMENT '条件奖励的免费次数';";
                $this->execute($sql);
            }
            //条件可完成次数
            if (!$this->existTableField("dq_lottery_config",'condition_finish_count')) {
                $sql = "ALTER TABLE `dq_lottery_config` ADD COLUMN `condition_finish_count` int(11) NOT NULL DEFAULT 1 COMMENT '条件奖励可完成次数（-1不限制）';";
                $this->execute($sql);
            }
        }
        //转盘id
        if (!$this->existTableField("dq_lottery_prize",'lottery_id')) {
            $sql = "ALTER TABLE `dq_lottery_prize` ADD COLUMN  `lottery_id` int(11) DEFAULT '1' COMMENT '转盘id';";
            $this->execute($sql);
        }
        //转盘id
        if (!$this->existTableField("dq_lottery_record",'lottery_id')) {
            $sql = "ALTER TABLE `dq_lottery_record` ADD COLUMN  `lottery_id` int(11) DEFAULT '1' COMMENT '转盘id';";
            $this->execute($sql);
        }

        $zwNodeId = $this->getNodeIdByNodeUrl('/huodongguanli');
        if ($zwNodeId) {
            $NodeId = $this->getNodeIdByNodeUrl('/huodongguanli/lotteryConfig');
            if (empty($NodeId)) {
                $pid = $this->addPublicParentNode(
                    '抽奖配置',
                    '/huodongguanli/lotteryConfig',
                    'lotteryConfig',
                    '/huodongguanli/lotteryConfig',
                    $zwNodeId,
                    3,
                    true,
                    0
                );
                $this->addPublicChildrenNode($pid);
                //设置内容
                $save = [
                    'node_name' => '设置内容',
                    'node_url' => 'content',
                    'path' => 'content',
                    'name' => 'content',
                    'pid' => $pid,
                    'sort' => 0,
                    'create_time' => time(),
                    'update_time' => time(),
                    'is_menu' => 1
                ];
                //批量添加添加，编辑，删除
                Db::name('admin_node')->save($save);
            }else{
                $ret = Db::name('admin_node')->where('pid', $NodeId)->where('path', '=', 'content')->find();
                if(empty($ret)){
                    //设置内容
                    $save = [
                        'node_name' => '设置内容',
                        'node_url' => 'content',
                        'path' => 'content',
                        'name' => 'content',
                        'pid' => $NodeId,
                        'sort' => 0,
                        'create_time' => time(),
                        'update_time' => time(),
                        'is_menu' => 1
                    ];
                    //批量添加添加，编辑，删除
                    Db::name('admin_node')->save($save);
                }
            }
            $conditionRecordNodeId = $this->getNodeIdByNodeUrl('/huodongguanli/lotteryConditionRecord');
            if(!$conditionRecordNodeId){
                //转盘条件记录
                $pid = $this->addPublicParentNode(
                    '转盘条件记录',
                    '/huodongguanli/lotteryConditionRecord',
                    'lotteryConditionRecord',
                    '/huodongguanli/lotteryConditionRecord',
                    $zwNodeId,
                    3,
                    false,
                    0
                );
            }
        }
        if(!$this->existTable('dq_lottery_condition_record')){
            $sql = <<<SQL
CREATE TABLE `dq_lottery_condition_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `lottery_id` int(11) NOT NULL DEFAULT '0' COMMENT '关联转盘Id',
  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户uid',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '奖励类型 0:下注金额，1:充值金额，2:倍数，3:累计充值，4:在线时长，5:累计下注，6:游戏时长',
  `reward_count` decimal(20, 2) NOT NULL COMMENT '奖励次数',
  `create_time` int NOT NULL COMMENT '创建时间',
  `update_time` int NOT NULL COMMENT ' 修改时间',
  PRIMARY KEY (`id`),
  INDEX `uid`(`uid`) USING BTREE,
  INDEX `lottery_id`(`lottery_id`) USING BTREE,
  INDEX `type`(`type`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='转盘条件任务领取记录';
SQL;
            $this->execute($sql);
        }
    }
}