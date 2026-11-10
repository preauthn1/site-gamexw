<?php

namespace app\common\upgrade\version;

class Ver140 extends VersionBase
{

    public function run()
    {
        //附属机构页面管理表
        if (!$this->existTable("dq_affiliate")) {
            $sql = <<<SQL
CREATE TABLE `dq_affiliate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `today_tip` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  COMMENT '今天-数据提示说明',
  `all_tip` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  COMMENT '全部-数据提示说明',
  `team_url` varchar(255) DEFAULT NULL COMMENT '团队说明链接',
  `share_title` varchar(255) DEFAULT '' COMMENT '分享标题',
  `share_platform` text COMMENT '分享平台',
  `share_intro` varchar(255) DEFAULT NULL COMMENT '分享简介',
  `service_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL COMMENT '服务群聊标题',
  `service_info` varchar(255) DEFAULT NULL COMMENT '服务群聊简介',
  `service_tip` varchar(255) DEFAULT NULL COMMENT '服务群聊提示信息',
  `service_contact` varchar(50) DEFAULT NULL COMMENT '联系方式',
  `service_platform` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  COMMENT '服务平台',
  `issues_list` text COMMENT '常问问题',
  `rule_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  DEFAULT NULL COMMENT '佣金奖励规则标题',
  `rule_info` varchar(255) DEFAULT NULL COMMENT '佣金奖励规则说明',
  `brokerage_min` decimal(20,2) DEFAULT '0.00' COMMENT '规则区间最小值',
  `brokerage_max` decimal(20,2) DEFAULT '0.00' COMMENT '规则区间最大值',
  `brokerage_ratio` decimal(20,2) DEFAULT '0.00' COMMENT '规则区间比例',
  `pop_info` text COMMENT '奖励规则说明',
  `pop_explain` text COMMENT '奖励规则补充',
  `calculate_title` varchar(255) DEFAULT NULL COMMENT '佣金计算规则标题',
  `calculate_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  COMMENT '佣金计算规则内容',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='附属机构管理';
SQL;
            $this->execute($sql);
        }
        if (!$this->judgeDataIsExist('affiliate', 'id', 1)) {
            //插入一条数据
            $sql = <<<SQL
INSERT INTO `dq_affiliate` VALUES ('1', '<p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">1.注册 - 注册用户数量</span></p><p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">2.新玩家 - 今日注册用户中的新付费玩家</span></p><p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">3.equipar 上的有效投注[num] - 来自您团队的指示和贡献的有效投注总价值。num - 提名总数及其做出有效投注的团队成员。</span></p><p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">注意：今日数据每3分钟更新一次</span></p>', '<p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">1.注册 - 注册用户数量</span></p><p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">2.新玩家 - 今日注册用户中的新付费玩家</span></p><p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">3.equipar 上的有效投注[num] - 来自您团队的指示和贡献的有效投注总价值。num - 提名总数及其做出有效投注的团队成员。</span></p><p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">注意：今日数据每3分钟更新一次</span></p>', 'https://test9f.kggames888.com/commissionRewardRules', '与您的社交社区分享', '[\"Telegram\",\"WhatsApp\",\"Facebook\"]', '与您的朋友分享此推荐链接', '代理之间通过官方 9F.COM 电报进行群聊', '点击左侧图标按钮，加入我们官方TG联盟群，我们将为您提供专业的联盟词和专业的联盟工具。并帮助您获得真正的好处。', '如果你有大量的观众和追随者。我们有特殊条件供您定制您的推荐计划！欲了解更多详情，请联系我们：', 'busin@aaa.com', 'Telegram', '[\"44\",\"45\"]', '佣金奖励规则', '推荐朋友到9F.COM即可赚钱，赚取80%以上佣金奖励！', '10.00', '10000.00', '50.00', '<p style=\"text-align:center;\"><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);font-size:29px;\">下属无限发展</span></p><img src=\"http://192.168.50.187/storage/img/20240118/0a471df798168bcd206127595e0ca7df.png\" alt=\"0a471df798168bcd206127595e0ca7df.png\" /><p> </p><p> </p><p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">1.每当您推荐的玩家下注、获胜或失败时,您都会收到不同的佣金百分比。</span></p><p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">2.系统每3分钟计算一次佣金。</span></p><p> </p>', '<p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">注册 - 注册用户数量 </span></p><p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">新玩家 - 今日注册用户中的新付费玩家 </span></p><p><span style=\"background-color:rgb(255,255,255);color:rgb(34,34,34);\">equipar 上的有效投注[num] - 来自您团队的指示和贡献的有效投注总价值。 </span></p>', '佣金计算规则', '<p><img src=\"http://192.168.50.187/storage/img/20240118/64cd1ed3946518086d3684380987726b.jpg\" alt=\"64cd1ed3946518086d3684380987726b.jpg\" /></p><p><img src=\"http://192.168.50.187/storage/img/20240118/03178bd27fba5a094dc6a9a1bdc841df.jpg\" alt=\"03178bd27fba5a094dc6a9a1bdc841df.jpg\" /></p><p> 例如，如果投注额为10-200000，则相应的奖金率为70/10000，如果投注额大于3000000，则代理奖金为110/10000。 例如，如果投注额为10-200000，则相应的奖金率为70/10000，如果投注额大于3000000，则代理奖金为110/10000。 例如，如果投注额为10-200000，则相应的奖金率为70/10000，如果投注额大于3000000，则代理奖金为110/10000。 例如，如果投注额为10-200000，则相应的奖金率为70/10000，如果投注额大于3000000，则代理奖金为110/10000。 例如，如果投注额为10-200000，则相应的奖金率为70/10000，如果投注额大于3000000，则代理奖金为110/10000。 例如，如果投注额为10-200000，则相应的奖金率为70/10000，如果投注额大于3000000，则代理奖金为110/10000。 例如，如果投注额为10-200000，则相应的奖金率为70/10000，如果投注额大于3000000，则代理奖金为110/10000。 </p>');
SQL;
            $this->execute($sql);
        }
        $systemNodeId = $this->getNodeIdByNodeUrl('/system');
        if ($systemNodeId) {
            $affiliateNodeId = $this->getNodeIdByNodeUrl('/admin/affiliateSetting');
            if (empty($affiliateNodeId)) {
                //附属机构管理
                $cNodeId = $this->addPublicParentNode(
                    '附属机构管理',
                    '/admin/affiliateSetting',
                    'affiliateSetting',
                    '/admin/affiliateSetting',
                    $systemNodeId,
                    4
                );
                $this->addPublicChildrenNode($cNodeId);
            }

        }
    }
}