<?php

namespace app\common\upgrade\version;

class Ver255 extends VersionBase
{

    public function run()
    {

        if (!$this->existTable("dq_telegrambot_config")) {
            $sql = <<<SQL
               CREATE TABLE `dq_telegrambot_config`  (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `api_key` varchar(255)  NULL DEFAULT NULL COMMENT '机器人token',
                  `bot_name` varchar(255)  NULL DEFAULT NULL COMMENT '机器人名称',
                  PRIMARY KEY (`id`) USING BTREE
                ) ENGINE = MyISAM CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT = 'Telegrambot配置';
SQL;
            $this->execute($sql);
            $api_key = env('bot.api_key','');
            $username = env('bot.username','');
            $inssetSql = "INSERT INTO `dq_telegrambot_config` (`id`,`api_key`,`bot_name`) VALUES (1, '{$api_key}', '{$username}');";
            $this->execute($inssetSql);
        }

        if (!$this->existTable("dq_telegrambot_message")) {
            $sql = <<<SQL
                CREATE TABLE `dq_telegrambot_message`  (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型 1充值申请 2充值成功 3提现申请 4提现成功 5游戏派彩',
                  `text` text  NULL,
                  `lang` varchar(10)  NULL DEFAULT 'cn' COMMENT '发送语言',
                  `number` int(11) NULL DEFAULT 0 COMMENT '单次发送数量（5游戏派彩用）',
                  `min_price` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT '随机最小金额（5游戏派彩用）',
                  `max_price` decimal(20, 4) NULL DEFAULT 0.0000 COMMENT '随机最大金额（5游戏派彩用）',
                  `chat_id` varchar(50)  NULL DEFAULT '' COMMENT '发送目标ID个人/群',
                  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1开启 0关闭',
                  `create_time` bigint(20) NULL DEFAULT NULL,
                  PRIMARY KEY (`id`) USING BTREE
                ) ENGINE = MyISAM CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci  COMMENT = 'Telegrambot消息模版';
SQL;
            $this->execute($sql);
            $range = strtolower(env('bot.lang','cn'));
            $chat_id = env('bot.chat_id','');
            $time = time();
            $text1 = "<b>[充值通知]</b>=用户ID:{:uid}=账号:{:username}=手机号码:{:phone}=订单编号:{:order_sn}=客戶本名:{:real_name}=充值金额:<u>{:apply_price}</u>=今日总充值:<u>{:today_total_price}</u>=充值时间:{:add_time}";
            $inssetSql1 = "INSERT INTO `dq_telegrambot_message` (`id`,`type`,`text`,`lang`,`number`,`min_price`,`max_price`,`chat_id`,`status`,`create_time`) VALUES (1, 1, '{$text1}','{$range}',0,0,0,'{$chat_id}',1,'{$time}');";
            $this->execute($inssetSql1);

            $text2 = "<b>[充值完成]</b>=用户ID:{:uid}=账号:{:username}=手机号码:{:phone}=订单编号:{:order_sn}=客戶本名:{:real_name}=充值金额:<u>{:apply_price}</u>=今日总充值:<u>{:today_total_price}</u>=充值时间:{:add_time}";
            $inssetSql2 = "INSERT INTO `dq_telegrambot_message` (`id`,`type`,`text`,`lang`,`number`,`min_price`,`max_price`,`chat_id`,`status`,`create_time`) VALUES (2, 2, '{$text2}','{$range}',0,0,0,'{$chat_id}',1,'{$time}');";
            $this->execute($inssetSql2);

            $text3 = "<b>[提现通知]</b>=用户ID:{:uid}=账号:{:username}=手机号码:{:phone}=订单编号:{:order_sn}=客戶本名:{:real_name}=银行名称:{:bank_name}=银行编码:{:bank_code}=提现金额(渠道):<u>{:arrival_prices}</u>提现金额(平台):<u>{:arrival_price}</u>=今日总提现:<u>{:withdrawal_today_total_price}</u>=提现时间:{:add_time}";
            $inssetSql3 = "INSERT INTO `dq_telegrambot_message` (`id`,`type`,`text`,`lang`,`number`,`min_price`,`max_price`,`chat_id`,`status`,`create_time`) VALUES (3, 3, '{$text3}','{$range}',0,0,0,'{$chat_id}',1,'{$time}');";
            $this->execute($inssetSql3);

            $text4 = "<b>[提现完成]</b>=用户ID:{:uid}=账号:{:username}=手机号码:{:phone}=订单编号:{:order_sn}=客戶本名:{:real_name}=银行名称:{:bank_name}=银行编码:{:bank_code}=提现金额(渠道):<u>{:arrival_prices}</u>=提现金额(平台):<u>{:arrival_price}</u>=今日总提现:<u>{:withdrawal_today_total_price}</u>=提现时间:{:add_time}";
            $inssetSql4 = "INSERT INTO `dq_telegrambot_message` (`id`,`type`,`text`,`lang`,`number`,`min_price`,`max_price`,`chat_id`,`status`,`create_time`) VALUES (4, 4, '{$text4}','{$range}',0,0,0,'{$chat_id}',1,'{$time}');";
            $this->execute($inssetSql4);

            $count = env('bot.number',1);
            $min_price = env('bot.min_price',1);
            $max_price = env('bot.max_price',10);
            $text5 = "🎉恭喜玩家{:username}，在<b>{:third}</b>平台，<b>{:game}</b>游戏，喜中<b><u>{:amount}</u>{:currency}</b>🎉";
            $inssetSql5 = "INSERT INTO `dq_telegrambot_message` (`id`,`type`,`text`,`lang`,`number`,`min_price`,`max_price`,`chat_id`,`status`,`create_time`) VALUES (5, 5, '{$text5}','{$range}','{$count}','{$min_price}','{$max_price}','{$chat_id}',1,'{$time}');";
            $this->execute($inssetSql5);
        }

        $pid = $this->getNodeIdByName("系统管理");
        $this->addPublicParentNode(
            'TelegramBot消息管理',
            '/telegrambot/index',
            'index',
            '/telegrambot/index',
            $pid,
            31,
            true,
            0
        );
        $pid2 = $this->getNodeIdByName("TelegramBot消息管理");
        $this->addPublicParentNode(
            '配置',
            'config',
            'config',
            'config',
            $pid2,
            0
        );
        $this->addPublicParentNode(
            '发送测试消息',
            'sendtest',
            'sendtest',
            'sendtest',
            $pid2,
            0
        );
    }

}
