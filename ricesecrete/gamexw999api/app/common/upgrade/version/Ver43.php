<?php

namespace app\common\upgrade\version;
use app\common\upgrade\version\VersionBase;

class Ver43 extends VersionBase {

    public function run() {
        $full_table_name = "dq_admin_role";
        $field = "admin_id";
        if (!$this->existTableField($full_table_name, $field)) {
            // 执行增加表字段SQL
            $sql = "ALTER TABLE `{$full_table_name}` ADD COLUMN `{$field}`int(10) NULL DEFAULT 0 COMMENT '哪个用户创建的角色'";
            $this->execute($sql);
        }

        if(!$this->existTable("dq_admin_node")){
            $sql = "CREATE TABLE `dq_admin_node` (
                  `id` int(10) NOT NULL AUTO_INCREMENT,
                  `node_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT '权限节点名称',
                  `node_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT '权限节点路径',
                  `pid` int(10) NOT NULL DEFAULT 0 COMMENT '父级id',
                  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT NULL COMMENT '图标',
                  `sort` tinyint(3) NOT NULL DEFAULT 0 COMMENT '排序值',
                  `is_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '超管才能看到的权限',
                  `not` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0需要权限 1 免权限可访问 ',
                  `special_menu` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT 'normal' COMMENT '一些配置中开启了 才会出现的节点 比如转盘 normal 正常的 turntable 转盘 shaba  第三方游戏 fund   基金 third   三方登录 offline_pay 线下收款 online_pay 线上收款',
                  `create_time` int(11) NOT NULL DEFAULT 0,
                  `update_time` int(11) NOT NULL DEFAULT 0,
                  PRIMARY KEY (`id`)
                ) ENGINE=MyISAM AUTO_INCREMENT=304 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";

            $this->execute($sql);

            $inssetSql = "INSERT INTO `dq_admin_node` (`id`,`node_name`,`node_url`,`pid`,`icon`,`sort`,`is_admin`,`not`,`special_menu`,`create_time`,`update_time`)
            VALUES
           (74, '系统管理', '', 0, 'layui-icon-set', 0, 0, 0, 'normal', 1658638832, 1658638832),
            (73, '榜单管理', '', 0, 'layui-icon-form', 0, 0, 0, 'normal', 1658638786, 1658638786),
            (72, '语言管理', '', 0, 'layui-icon-website', 0, 0, 0, 'normal', 1658638746, 1658638746),
            (71, '站务管理', '', 0, 'layui-icon-util', 0, 0, 0, 'normal', 1658638610, 1658638610),
            (70, '第三方游戏', '', 0, 'layui-icon-theme', 0, 0, 0, 'normal', 1658638571, 1658638571),
            (66, '报表管理', '', 0, 'layui-icon-chart', 0, 0, 0, 'normal', 1658638387, 1658638387),
            (68, '账号管理', '', 0, 'layui-icon-username', 0, 0, 0, 'normal', 1658671361, 1658671361),
            (67, '微商管理', '', 0, 'layui-icon-user', 0, 0, 0, 'normal', 1658638923, 1658638923),
            (69, '三方登录', '', 0, 'layui-icon-senior', 0, 0, 0, 'normal', 1658638499, 1658638499),
            (64, '出入金管理', '', 0, 'layui-icon-about', 0, 0, 0, 'normal', 1658638127, 1658638127),
            (62, '活动管理', '', 0, 'layui-icon-gift', 0, 0, 0, 'normal', 1658638679, 1658818746),
            (61, '游戏管理', '', 0, 'layui-icon-template', 0, 0, 0, 'normal', 1658637874, 1658637874),
            (60, '风控仪表盘', '', 0, 'layui-icon-console', 0, 0, 0, 'normal', 1658637706, 1658645556),
            (65, '银行/收款/支付管理', '', 0, 'layui-icon-tabs', 0, 0, 0, 'normal', 1658638360, 1658638360),
            (75, '权限管理', '', 0, 'layui-icon-auz', 1, 0, 0, 'normal', 1658671341, 1658823266),
            (76, '及时监控', '/index/work', 60, '', 0, 0, 0, 'normal', 1658639000, 1658686620),
            (77, '设定', '/index/set', 60, '', 0, 0, 0, 'normal', 1658639016, 1658823262),
            (78, '联赛管理', '/liansai/list', 61, '', 0, 0, 0, 'normal', 1658639036, 1658639036),
            (79, '赛程管理', '/liansai_sc/list', 61, '', 0, 0, 0, 'normal', 1658639042, 1658639042),
            (80, '注单管理', '/liansai_zhudan/lists', 61, '', 0, 0, 0, 'normal', 1658639050, 1658639050),
            (81, '赛果管理', '/liansai_sc_res/lists', 61, '', 0, 0, 0, 'normal', 1658639058, 1658639058),
            (82, '优惠管理', '/action/list', 62, '', 0, 0, 0, 'normal', 1658639252, 1658639252),
            (83, '优惠申请', '/action_apply/list', 62, '', 0, 0, 0, 'normal', 1658639259, 1658639259),
            (88, '抽奖配置', '/action/lottery_config', 62, '', 0, 0, 0, 'turntable', 1658643428, 1658643428),
            (89, '奖品列表', '/action/lottery_prize', 62, '', 0, 0, 0, 'turntable', 1658643438, 1658643438),
            (90, '抽奖记录', '/action/lottery_record', 62, '', 0, 0, 0, 'turntable', 1658643447, 1658643447),
            (91, '积分记录', '/action/integral_record', 62, '', 0, 0, 0, 'turntable', 1658643457, 1658643457),
            (92, '抽奖报表', '/action/lottery_data_record', 62, '', 0, 0, 0, 'turntable', 1658643465, 1658643465),
            (96, '会员充值', '/user_offline/list', 64, '', 0, 0, 0, 'normal', 1658645729, 1658687660),
            (97, '会员提现', '/user_withdrawal/list', 64, '', 0, 0, 0, 'normal', 1658645737, 1658645737),
            (98, '人工调整', '/artificial_balance/list', 64, '', 0, 0, 0, 'normal', 1658645744, 1658645744),
            (99, '线下收款账户', '/bank_sk/list', 65, '', 0, 0, 0, 'offline_pay', 1658646131, 1658646131),
            (100, '支付平台', '/pay_set/lists', 65, '', 0, 0, 0, 'online_pay', 1658646153, 1658646153),
            (101, '渠道统计', '/pay_set/count', 65, '', 0, 0, 0, 'normal', 1658646171, 1658646171),
            (102, '运营总表', '/report/index', 66, '', 0, 0, 0, 'normal', 1658646478, 1658646478),
            (103, '财务报表', '/chart_caiwu/list', 66, '', 0, 0, 0, 'normal', 1658646487, 1658646487),
            (104, '用户输赢', '/report/user', 66, '', 0, 0, 0, 'normal', 1658646495, 1658646495),
            (105, '充值排行榜', '/user_offline/leaderboard', 66, '', 0, 0, 0, 'normal', 1658646503, 1658685261),
            (106, '提现排行榜', '/user_withdrawal/leaderboard', 66, '', 0, 0, 0, 'normal', 1658646518, 1658646518),
            (107, '代理返佣', '/agent_rakeback/list', 67, '', 0, 0, 0, 'normal', 1658646581, 1658646581),
            (108, '推广奖金', '/agent_selllog/list', 67, '', 0, 0, 0, 'normal', 1658646589, 1658646589),
            (109, '微商报表', '/agent_report/index', 67, '', 0, 0, 0, 'normal', 1658646596, 1658646596),
            (110, '微商列表', '/agent_list/lists', 67, '', 0, 0, 0, 'normal', 1658646602, 1658646602),
            (111, '微商设置', '/agent_setting/list', 67, '', 0, 0, 0, 'normal', 1658646609, 1658646609),
            (113, '会员管理', '/user/list', 68, '', 0, 0, 0, 'normal', 1658646644, 1658646644),
            (114, '会员等级', '/user_level/list', 68, '', 0, 0, 0, 'normal', 1658646651, 1658646651),
            (115, '银行卡', '/user_bank/bank_list', 68, '', 0, 0, 0, 'normal', 1658646659, 1658646659),
            (116, '用户登录日志', '/user_login_log/list', 68, '', 0, 0, 0, 'normal', 1658646667, 1658646667),
            (117, '平台管理', '/third_set/list', 69, '', 0, 0, 0, 'third', 1658646686, 1658646686),
            (118, '游戏设置', '/game_set/list', 70, '', 9, 0, 0, 'shaba', 1658646702, 1663664239),
            (119, '游戏订单', '/game_tx/list', 70, '', 0, 0, 0, 'shaba', 1658646713, 1658646713),
            (120, '游戏注单', '/game_zhudan/list', 70, '', 0, 0, 0, 'shaba', 1658646724, 1658646724),
            (121, '游戏报表', '/game_report/index', 70, '', 0, 0, 0, 'shaba', 1658646734, 1658646734),
            (122, '首页轮播图', '/banner/list', 71, '', 0, 0, 0, 'normal', 1658646751, 1658646751),
            (123, '公告管理', '/gonggao/list', 71, '', 0, 0, 0, 'normal', 1658646758, 1658646758),
            (124, '基础问答', '/news/lists', 71, '', 0, 0, 0, 'normal', 1658646766, 1658646766),
            (125, '客服管理', '/Service/list', 71, '', 0, 0, 0, 'normal', 1658646772, 1658646772),
            (126, '规则管理', '/rule/lists', 71, '', 0, 0, 0, 'normal', 1658646779, 1658646779),
            (127, '站内信', '/usermsg/list', 71, '', 0, 0, 0, 'normal', 1658646787, 1658646787),
            (128, '语言种类', '/language/index', 72, '', 0, 0, 0, 'normal', 1658646804, 1658646804),
            (129, '语言类目', '/language/items', 72, '', 0, 0, 0, 'normal', 1658646815, 1658646815),
            (130, '榜单设定', '/bangdan/list', 73, '', 0, 0, 0, 'normal', 1658646828, 1658646828),
            (131, '假账户管理', '/false_accout/list', 73, '', 0, 0, 0, 'normal', 1658646839, 1658646839),
            (132, '操作记录', '/admin_log/list', 74, '', 0, 0, 0, 'normal', 1658646852, 1658646852),
            (133, '系统设定', '/system/edit', 74, '', 0, 0, 0, 'normal', 1658646860, 1658646860),
            (134, 'IP黑名单', '/ip_limit/list', 74, '', 0, 0, 0, 'normal', 1658646867, 1658646867),
            (135, '权限规则', '/admin_node/list', 75, '', 0, 0, 1, 'normal', 1658646895, 1658646895),
            (136, '角色管理', '/admin_role/list', 75, '', 0, 0, 0, 'normal', 1658646903, 1658646903),
            (137, '管理员列表', '/admin/list', 75, '', 0, 0, 0, 'normal', 1658646912, 1658646912),
            (142, '添加管理员', '/admin/add', 137, '', 0, 0, 0, 'normal', 1658823680, 1658832846),
            (139, '添加角色', '/admin_role/node_add', 136, '', 0, 0, 0, 'normal', 1658823498, 1658831611),
            (140, '删除角色', '/admin_role/delete', 136, '', 0, 0, 0, 'normal', 1658823518, 1658831621),
            (141, '编辑角色', '/admin_role/node_edit', 136, '', 0, 0, 0, 'normal', 1658823531, 1658831632),
            (144, '删除管理员', '/admin/delete', 137, '', 0, 0, 0, 'normal', 1658824779, 1658831659),
            (145, '编辑管理员', '/admin/edit', 137, '', 0, 0, 0, 'normal', 1658824816, 1658832877),
            (146, '设置水位', '/index/cjl_set', 76, '', 0, 0, 0, 'normal', 1658833348, 1658833348),
            (147, '忽略', '/index/cjl_ignore', 76, '', 0, 0, 0, 'normal', 1658833364, 1658833364),
            (148, '取消赛事并退回所有投注', '/index/cancel_match', 76, '', 0, 0, 0, 'normal', 1658837097, 1658837097),
            (149, '结算', '/liansai_sc_res/jiesuan', 76, '', 0, 0, 0, 'normal', 1658837182, 1658837182),
            (150, '队伍管理', '/liansai_team/list', 78, '', 0, 0, 0, 'normal', 1658839296, 1658839296),
            (151, '编辑联赛', '/liansai/edit', 78, '', 0, 0, 0, 'normal', 1658839324, 1658839406),
            (152, '删除联赛', '/liansai/delete', 78, '', 0, 0, 0, 'normal', 1658839479, 1658839479),
            (153, '添加联赛', '/liansai/add', 78, '', 0, 0, 0, 'normal', 1658839498, 1658839498),
            (154, '添加赛程', '/liansai_sc/add', 79, '', 0, 0, 0, 'normal', 1658839749, 1658839749),
            (155, '添加队伍', '/liansai_team/add', 150, '', 0, 0, 0, 'normal', 1658840553, 1658840553),
            (156, '启停用管理员', '/admin/status_edit', 137, '', 0, 0, 0, 'normal', 1658841868, 1658842285),
            (157, '编辑队伍', '/liansai_team/edit', 150, '', 0, 0, 0, 'normal', 1658848045, 1658848045),
            (158, '停启用队伍', '/liansai_team/status_edit', 150, '', 0, 0, 0, 'normal', 1658848068, 1658848068),
            (159, '停启用联赛', '/liansai/status_edit', 78, '', 0, 0, 0, 'normal', 1658848636, 1658848636),
            (160, '编辑赛程', '/liansai_sc/edit', 79, '', 0, 0, 0, 'normal', 1658848878, 1658848878),
            (161, '波胆赔率设定', '/liansai_sc/bd_set', 79, '', 0, 0, 0, 'normal', 1658848894, 1658848894),
            (162, '更新波胆', '/liansai_sc/bd_update', 79, '', 0, 0, 0, 'normal', 1658848915, 1658848915),
            (165, '注销注单', '/liansai_zhudan/status_edit', 80, '', 0, 0, 0, 'normal', 1658849392, 1658849392),
            (164, '启停用赛程', '/liansai_sc/status_edit', 79, '', 0, 0, 0, 'normal', 1658849022, 1658849022),
            (166, '修改注单', '/liansai_zhudan/edit', 80, '', 0, 0, 0, 'normal', 1658849406, 1658849406),
            (167, '导出表格', '/liansai_zhudan/excel', 80, '', 0, 0, 0, 'normal', 1658849501, 1658849501),
            (168, '结算赛果', '/liansai_sc_res/jiesuan', 81, '', 0, 0, 0, 'normal', 1658850842, 1658850842),
            (169, '退回注单', '/liansai_sc_res/order_back', 81, '', 0, 0, 0, 'normal', 1658850859, 1658850859),
            (173, '删除优惠', '/action/delete', 82, '', 0, 0, 0, 'normal', 1658851709, 1658851709),
            (171, '包赔', '/liansai_sc_res/indemnity', 81, '', 0, 0, 0, 'normal', 1658850901, 1658850901),
            (172, '重新结算', '/liansai_sc_res/settlement', 81, '', 0, 0, 0, 'normal', 1658850920, 1658850920),
            (174, '添加优惠', '/action/add', 82, '', 0, 0, 0, 'normal', 1658851723, 1658851723),
            (175, '编辑优惠', '/action/edit', 82, '', 0, 0, 0, 'normal', 1658851746, 1658851746),
            (176, '设置活动内容', '/news/lists', 82, '', 0, 0, 0, 'normal', 1658851776, 1658852218),
            (177, '删除活动内容', '/news/delete', 176, '', 0, 0, 0, 'normal', 1658852465, 1658852596),
            (178, '添加活动内容', '/news/add', 176, '', 0, 0, 0, 'normal', 1658852477, 1658852599),
            (179, '编辑活动内容', '/news/edit', 176, '', 0, 0, 0, 'normal', 1658852486, 1658852602),
            (180, '(拒绝)派送优惠', '/action_apply/ps', 83, '', 0, 0, 0, 'normal', 1658856149, 1658856209),
            (181, '添加/编辑奖品', '/action/lottery_prize_save', 89, '', 0, 0, 0, 'normal', 1658856463, 1658856740),
            (182, '删除奖品', '/action/lottery_prize_delet', 89, '', 0, 0, 0, 'normal', 1658856732, 1658856732),
            (183, '添加假记录', '/action/lottery_fakerecord', 90, '', 0, 0, 0, 'normal', 1658857049, 1658857146),
            (184, '添加积分记录', '/action/integral_save', 91, '', 0, 0, 0, 'normal', 1658857806, 1658857806),
            (185, '导出积分记录', '/action/integral_record_excel', 91, '', 0, 0, 0, 'normal', 1658858404, 1658858404),
            (188, '手动退回', '/fund/found_return', 94, '', 0, 0, 0, 'normal', 1658858958, 1658858958),
            (189, '手动入单', '/user_offline/add', 96, '', 0, 0, 0, 'normal', 1658859169, 1658859169),
            (190, '导出报表', '/user_offline/excel', 96, '', 0, 0, 0, 'normal', 1658859187, 1658859187),
            (191, '审核/查看', '/user_offline/edit', 96, '', 0, 0, 0, 'normal', 1658859244, 1658859244),
            (192, '手动出款', '/user_withdrawal/add', 97, '', 0, 0, 0, 'normal', 1658859524, 1658859524),
            (193, '导出报表', '/user_withdrawal/excel', 97, '', 0, 0, 0, 'normal', 1658859532, 1658859532),
            (194, '审核/查看', '/user_withdrawal/edit', 97, '', 0, 0, 0, 'normal', 1658859575, 1658859575),
            (195, '添加', '/artificial_balance/add', 98, '', 0, 0, 0, 'normal', 1658859964, 1658859964),
            (196, '导出', '/artificial_balance/excel', 98, '', 0, 0, 0, 'normal', 1658859976, 1658859976),
            (197, '导入', '/artificial_balance/import', 98, '', 0, 0, 0, 'normal', 1658859993, 1658859993),
            (198, '删除收款账户', '/bank_sk/delete', 99, '', 0, 0, 0, 'normal', 1658888613, 1658888613),
            (199, '添加收款账户', '/bank_sk/add', 99, '', 0, 0, 0, 'normal', 1658888625, 1658888625),
            (200, '充值备注', '/bank_sk/bank_sk_set', 99, '', 0, 0, 0, 'normal', 1658888645, 1658888645),
            (201, '编辑收款信息', '/bank_sk/edit', 99, '', 0, 0, 0, 'normal', 1658888663, 1658888663),
            (202, '启停用账户', '/bank_sk/status_edit', 99, '', 0, 0, 0, 'normal', 1658888685, 1658888685),
            (203, '删除支付平台', '/pay_set/delete', 100, '', 0, 0, 0, 'normal', 1658889049, 1658889214),
            (204, '添加支付平台', '/pay_set/add', 100, '', 0, 0, 0, 'normal', 1658889059, 1658889224),
            (206, '编辑支付平台', '/pay_set/edit', 100, '', 0, 0, 0, 'normal', 1658889190, 1658889230),
            (207, '查看报表', '/report/get_data', 102, '', 0, 0, 0, 'normal', 1658889460, 1658889884),
            (208, '清除缓存', '/report/clear_cache', 102, '', 0, 0, 0, 'normal', 1658889470, 1658890171),
            (209, '导出', '/user_offline/leaderboard?excel', 105, '', 0, 0, 0, 'normal', 1658890399, 1658890399),
            (210, '导出', '/user_withdrawal/leaderboard?excel', 106, '', 0, 0, 0, 'normal', 1658890649, 1658890687),
            (211, '导出报表', '/agent_rakeback/excel', 107, '', 0, 0, 0, 'normal', 1658890753, 1658890753),
            (212, '一键派发', '/agent_rakeback/distribute', 107, '', 0, 0, 0, 'normal', 1658890818, 1658890818),
            (213, '派发', '/agent_rakeback/sell', 107, '', 0, 0, 0, 'normal', 1658890829, 1658890829),
            (214, '一键拒绝', '/agent_rakeback/refused', 107, '', 0, 0, 0, 'normal', 1658890862, 1658890862),
            (215, '拒绝', '/agent/reject', 107, '', 0, 0, 0, 'normal', 1658890874, 1658890874),
            (216, '导出', '/agent_selllog/excel', 108, '', 0, 0, 0, 'normal', 1658891611, 1658891611),
            (217, '一键派送', '/agent_selllog/distribute', 108, '', 0, 0, 0, 'normal', 1658891635, 1658891635),
            (218, '拒绝派送', '/agent_selllog/reject', 108, '', 0, 0, 0, 'normal', 1658891650, 1658891650),
            (219, '派送', '/agent_selllog/sell', 108, '', 0, 0, 0, 'normal', 1658891679, 1658891679),
            (220, '查看下级数据', '/agent_report/index?look', 109, '', 0, 0, 0, 'normal', 1658892051, 1658892051),
            (221, '下级充提报表', '/agent_report/user', 109, '', 0, 0, 0, 'normal', 1658892082, 1658892082),
            (222, '下层注单列表', '/agent_report/userzhudan', 109, '', 0, 0, 0, 'normal', 1658892089, 1658892089),
            (223, '下层彩金报表', '/agent_report/usergiving', 109, '', 0, 0, 0, 'normal', 1658892097, 1658892097),
            (224, '删除', '/agent_setting/delete', 111, '', 0, 0, 0, 'normal', 1658892973, 1658892973),
            (225, '添加', '/agent_setting/add', 111, '', 0, 0, 0, 'normal', 1658892985, 1658892985),
            (226, '编辑', '/agent_setting/edit', 111, '', 0, 0, 0, 'normal', 1658892998, 1658892998),
            (227, '添加用户', '/user/add', 113, '', 0, 0, 0, 'normal', 1658893499, 1658894372),
            (228, '代理审核列表', '/user_agent/list', 113, '', 0, 0, 0, 'normal', 1658893538, 1658893538),
            (229, '导入报表', '/user/import', 113, '', 0, 0, 0, 'normal', 1658893552, 1658893552),
            (230, '批量设置下注限额', '/user/set_limit', 113, '', 0, 0, 0, 'normal', 1658893601, 1658893601),
            (231, '测试用户列表', '/user/cs_user', 113, '', 0, 0, 0, 'normal', 1658893620, 1658893620),
            (232, '批量清零流水', '/user/remove_water', 113, '', 0, 0, 0, 'normal', 1658893657, 1658893657),
            (233, '导出报表', '/user/excel', 113, '', 0, 0, 0, 'normal', 1658893680, 1658893680),
            (234, '启停用用户', '/user/status_edit', 113, '', 0, 0, 0, 'normal', 1658893719, 1658893719),
            (235, '查看用户资料', '/user/base', 113, '', 0, 0, 0, 'normal', 1658893749, 1658893762),
            (236, '编辑用户资料', '/user/edit', 113, '', 0, 0, 0, 'normal', 1658893787, 1658893787),
            (237, '修改上级', '/user/rec_edit', 113, '', 0, 0, 0, 'normal', 1658893806, 1658893806),
            (238, '审核', '/user_agent/status_edit', 228, '', 0, 0, 0, 'normal', 1658894580, 1658894580),
            (239, '用户银行卡', '/user_bank/user_bank_list', 235, '', 0, 0, 0, 'normal', 1658894830, 1658894843),
            (240, '用户登陆日志', '/user_login/list', 235, '', 0, 0, 0, 'normal', 1658894861, 1658894861),
            (241, '用户操作记录', '/user_admin_log/list', 235, '', 0, 0, 0, 'normal', 1658894871, 1658896658),
            (242, '收益明细', '/user_balance_mx/list', 235, '', 0, 0, 0, 'normal', 1658894880, 1658896777),
            (243, '订单记录', '/liansai_zhudan/user_lists', 235, '', 0, 0, 0, 'normal', 1658894891, 1658894891),
            (244, '充值记录', '/user_offline/user_list', 235, '', 0, 0, 0, 'normal', 1658894900, 1658894900),
            (245, '提现记录', '/user_withdrawal/user_list', 235, '', 0, 0, 0, 'normal', 1658894907, 1658894907),
            (246, '上级会员', '/user/user_top', 235, '', 0, 0, 0, 'normal', 1658894915, 1658894915),
            (247, '流水总计', '/user/running_water', 235, '', 0, 0, 0, 'normal', 1658894921, 1658894921),
            (249, '编辑会员等级', '/user_level/edit', 114, '', 0, 0, 0, 'normal', 1658897934, 1658897934),
            (250, '删除', '/user_bank/delete', 115, '', 0, 0, 0, 'normal', 1658898139, 1658898139),
            (251, '修改', '/user_bank/edit', 115, '', 0, 0, 0, 'normal', 1658898148, 1658898148),
            (252, '添加平台', '/third_set/add', 117, '', 0, 0, 0, 'normal', 1658898233, 1658898351),
            (253, '编辑平台', '/third_set/edit', 117, '', 0, 0, 0, 'normal', 1658898366, 1658898366),
            (255, '添加游戏', '/game_set/add', 118, '', 0, 0, 0, 'normal', 1658898565, 1658898565),
            (256, '编辑游戏', '/game_set/edit', 118, '', 0, 0, 0, 'normal', 1658898573, 1658898573),
            (257, '手动下分', '/game_tx/get_score', 119, '', 0, 0, 0, 'normal', 1658898793, 1658898793),
            (258, '订单详情', '/game_tx/detail', 119, '', 0, 0, 0, 'normal', 1658898806, 1658898806),
            (259, '添加轮播图', '/banner/add', 122, '', 0, 0, 0, 'normal', 1658898919, 1658898919),
            (260, '删除轮播图', '/banner/delete', 122, '', 0, 0, 0, 'normal', 1658898937, 1658898937),
            (261, '编辑轮播图', '/banner/edit', 122, '', 0, 0, 0, 'normal', 1658898964, 1658898964),
            (262, '停启用轮播图', '/banner/status_edit', 122, '', 0, 0, 0, 'normal', 1658898981, 1658898981),
            (263, '删除公告', '/gonggao/delete', 123, '', 0, 0, 0, 'normal', 1658899165, 1658899165),
            (264, '添加公告', '/gonggao/add', 123, '', 0, 0, 0, 'normal', 1658899179, 1658899179),
            (265, '编辑公告', '/gonggao/edit', 123, '', 0, 0, 0, 'normal', 1658899194, 1658899194),
            (266, '停启用公告', '/gonggao/status_edit', 123, '', 0, 0, 0, 'normal', 1658899209, 1658899209),
            (267, '删除问答', '/news/delete', 124, '', 0, 0, 0, 'normal', 1658899446, 1658899446),
            (268, '添加问答', '/news/add', 124, '', 0, 0, 0, 'normal', 1658899455, 1658899455),
            (269, '编辑问答', '/news/edit', 124, '', 0, 0, 0, 'normal', 1658899461, 1658899461),
            (270, '删除客服', '/service/delete', 125, '', 0, 0, 0, 'normal', 1658899607, 1658899607),
            (271, '添加客服', '/service/add', 125, '', 0, 0, 0, 'normal', 1658899619, 1658899619),
            (272, '编辑客服', '/service/edit', 125, '', 0, 0, 0, 'normal', 1658899632, 1658899632),
            (273, '启停用客服', '/service/status_edit', 125, '', 0, 0, 0, 'normal', 1658899660, 1658899780),
            (274, '删除规则', '/rule/delete', 126, '', 0, 0, 0, 'normal', 1658899923, 1658899923),
            (275, '添加规则', '/rule/add', 126, '', 0, 0, 0, 'normal', 1658899931, 1658899931),
            (276, '编辑规则', '/rule/edit', 126, '', 0, 0, 0, 'normal', 1658899939, 1658899939),
            (277, '删除站内信', '/usermsg/delete', 127, '', 0, 0, 0, 'normal', 1658900013, 1658900013),
            (278, '添加站内信', '/usermsg/add', 127, '', 0, 0, 0, 'normal', 1658900026, 1658900026),
            (279, '删除语言种类', '/language/delete', 128, '', 0, 0, 0, 'normal', 1658900218, 1658900218),
            (280, '添加语言种类', '/language/add', 128, '', 0, 0, 0, 'normal', 1658900237, 1658900237),
            (281, '编辑语言种类', '/language/edit', 128, '', 0, 0, 0, 'normal', 1658900259, 1658900259),
            (282, '更新语言包', '/language/update', 128, '', 0, 0, 0, 'normal', 1658900280, 1658900433),
            (283, '设置默认语言/启停用', '/language/setstatus', 128, '', 0, 0, 0, 'normal', 1658900297, 1658900330),
            (284, '添加类目', '/language/edititem', 129, '', 0, 0, 0, 'normal', 1658900623, 1658900623),
            (285, '类目导出', '/language/excel', 129, '', 0, 0, 0, 'normal', 1658900637, 1658900637),
            (286, '类目导入', '/language/import', 129, '', 0, 0, 0, 'normal', 1658900651, 1658900651),
            (287, '更新语言包', '/language/update', 129, '', 0, 0, 0, 'normal', 1658900678, 1658900678),
            (288, '上传图片', '/upload/upload_img', 129, '', 0, 0, 0, 'normal', 1658900695, 1658900695),
            (289, '删除类目', '/language/delitem', 129, '', 0, 0, 0, 'normal', 1658900723, 1658900723),
            (290, '删除', '/bangdan/delete', 130, '', 0, 0, 0, 'normal', 1658901035, 1658901035),
            (291, '添加', '/bangdan/add', 130, '', 0, 0, 0, 'normal', 1658901046, 1658901046),
            (292, '编辑', '/bangdan/edit', 130, '', 0, 0, 0, 'normal', 1658901071, 1658901071),
            (293, '删除假账号', '/false_accout/delete', 131, '', 0, 0, 0, 'normal', 1658901180, 1658901199),
            (294, '添加假账号', '/false_accout/add', 131, '', 0, 0, 0, 'normal', 1658901215, 1658901215),
            (295, '编辑假账号', '/false_accout/edit', 131, '', 0, 0, 0, 'normal', 1658901231, 1658901231),
            (296, '启停用假账号', '/false_accout/status_edit', 131, '', 0, 0, 0, 'normal', 1658901251, 1658901251),
            (297, '系统维护设置', '/system/upd_list', 133, '', 0, 0, 0, 'normal', 1658901438, 1658901438),
            (298, '添加', '/system/upd_add', 297, '', 0, 0, 0, 'normal', 1658901557, 1658901557),
            (299, '关闭', '/system/upd_edit_status', 297, '', 0, 0, 0, 'normal', 1658901573, 1658901573),
            (300, '删除', '/ip_limit/delete', 134, '', 0, 0, 0, 'normal', 1658901723, 1658901723),
            (301, '添加', '/ip_limit/add', 134, '', 0, 0, 0, 'normal', 1658901776, 1658901776),
            (302, '编辑', '/ip_limit/edit', 134, '', 0, 0, 0, 'normal', 1658901791, 1658901791),
            (303, '启停用', '/ip_limit/status_edit', 134, '', 0, 0, 0, 'normal', 1658901809, 1658901809),
            (304, '货币管理', '/currency/list', 65, '', 0, 0, 0, 'normal', 1663663316, 1663663316),
            (305, '添加货币', '/currency/add', 304, '', 0, 0, 0, 'normal', 1663663333, 1663663333),
            (306, '修改货币', '/currency/edit', 304, '', 0, 0, 0, 'normal', 1663663343, 1663663343),
            (307, '启/停货币', '/currency/state_edit', 304, '', 0, 0, 0, 'normal', 1663663349, 1663663349),
            (308, '国家列表', '/nation/index', 72, '', 10, 0, 0, 'normal', 1663663396, 1663666034),
            (309, '添加国家', '/nation/add', 308, '', 0, 0, 0, 'normal', 1663663410, 1663663410),
            (310, '编辑国家', '/nation/edit', 308, '', 0, 0, 0, 'normal', 1663663416, 1663663416),
            (311, '修改状态', '/nation/state_edit', 308, '', 0, 0, 0, 'normal', 1663663423, 1663663423),
            (312, '删除国家', '/nation/delete', 308, '', 0, 0, 0, 'normal', 1663663429, 1663663429),
            (313, '游戏分类', '/game_set/type', 70, '', 10, 0, 0, 'shaba', 1663664069, 1663664174),
            (314, '添加游戏分类', '/game_set/type_add', 313, '', 0, 0, 0, 'shaba', 1663664086, 1663664086),
            (315, '修改游戏分类', '/game_set/type_edit', 313, '', 0, 0, 0, 'shaba', 1663664091, 1663664091),
            (316, '删除游戏分类', '/game_set/type_delete', 313, '', 0, 0, 0, 'shaba', 1663664097, 1663664097),
            (317, '用户管理', '/game_user/list', 70, '', 8, 0, 0, 'normal', 1663664194, 1663664232),
            (318, '获取游戏记录', '/game_zhudan/get_logs', 120, '', 0, 0, 0, 'normal', 1663664261, 1663664261),
            (319, '获取游戏余额', '/game_user/get_balance', 317, '', 0, 0, 0, 'normal', 1663664355, 1663664355),
            (320, '余额转回', '/game_user/transfer_out', 317, '', 0, 0, 0, 'normal', 1663664367, 1663664367),
            (321, '统计报表数据', '/game_report/get_game_report', 121, '', 0, 0, 0, 'normal', 1663664458, 1663664609);";
            $this->execute($inssetSql);
        }

        $full_table_name = 'dq_admin_permission';

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '银行/收款/支付管理' AND `permission_url` = '' AND `pid` = '1' ";
        $selectData = $this->getTableData($select_sql);

        if(isset($selectData[0])) {
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '货币管理' AND `permission_url` = '/admin/currency/list' AND `pid` = '{$pid}' ";
            $selectData = $this->getTableData($select_sql);
            if(!isset($selectData[0])) {
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('货币管理', '/admin/currency/list', '{$pid}');";
                $this->execute($sql);

                $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '货币管理' AND `permission_url` = '/admin/currency/list' AND `pid` = '{$pid}' ";
                $selectData = $this->getTableData($select_sql);
                if(isset($selectData[0])) {
                    $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('添加货币', '/admin/currency/add', '{$selectData[0]['id']}');";
                    $this->execute($sql);

                    $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('修改货币', '/admin/currency/edit', '{$selectData[0]['id']}');";
                    $this->execute($sql);

                    $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('启/停货币', '/admin/currency/state_edit', '{$selectData[0]['id']}');";
                    $this->execute($sql);
                }
            }


        }

        if (!$this->existTable("dq_currency")) {
            $sql = "CREATE TABLE `dq_currency`  (
                      `id` int(10) NOT NULL AUTO_INCREMENT,
                      `type` tinyint(1) NOT NULL COMMENT '是否USDT 1 是 2否 只能存在一个usdt的数据',
                      `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '货币名称',
                      `country_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '国家代码',
                      `recharge_rate` decimal(20, 6) NOT NULL COMMENT '充值汇率',
                      `withdrawal_rate` decimal(20, 6) NOT NULL COMMENT '提现汇率',
                      `link_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NULL DEFAULT NULL COMMENT '链接名称 比如usdt 可能会有几种链接名称  复选框勾选',
                      `state` tinyint(1) NOT NULL DEFAULT 1 COMMENT '使用状态  1启用 2禁用',
                      `is_defa` tinyint(1) NOT NULL DEFAULT 2 COMMENT '默认币种 1默认 ',
                      `create_time` int(11) NOT NULL,
                      `update_time` int(11) NOT NULL,
                      PRIMARY KEY (`id`) USING BTREE
                    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";

            $this->execute($sql);

            $inssetSql = "INSERT INTO `dq_currency` (`type`,`name`,`country_code`,`recharge_rate`,`withdrawal_rate`,`link_name`,`state`,`is_defa`,`create_time`,`update_time`)
            VALUES
            (1, 'USDT', 'USDT', 1.000000, 1.000000, '[\"TRC20\",\"ERC20\"]', 1, 2, 1659430314, 1659716775),
            (2, '法币', 'PG', 1.000000, 1.000000, NULL, 1, 1, 1659430357, 1659716780);";

            $this->execute($inssetSql);
        }


        if (!$this->existTableField("dq_pay_set", "currency_id")) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `currency_id` int(11) NOT NULL DEFAULT 0 COMMENT '货币id';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_pay_set", "is_all")) {
            $sql = "ALTER TABLE `dq_pay_set` ADD COLUMN `is_all` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否通用渠道 1是 2否（通用渠道即不限制语种）';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_bank_sk", "currency_id")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `currency_id` int(11) NOT NULL DEFAULT 0 COMMENT '货币id';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_bank_sk", "is_all")) {
            $sql = "ALTER TABLE `dq_bank_sk` ADD COLUMN `is_all` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否通用渠道 1是 2否（通用渠道即不限制语种）';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_user_bank", "currency_id")) {
            $sql = "ALTER TABLE `dq_user_bank` ADD COLUMN `currency_id` int(11) NOT NULL DEFAULT 0 COMMENT '货币id';";
            $this->execute($sql);
        }


        
    
        if (!$this->existTableField("dq_system", "agent_settlement_type")) {
            $sql = "ALTER TABLE `dq_system` ADD COLUMN `agent_settlement_type` TINYINT(1) DEFAULT 0 NULL COMMENT '返佣类型 0 按注单 1 按盈亏';";
            $this->execute($sql);
        }
        if (!$this->existTable("dq_agent_report")) {
            $sql = "CREATE TABLE `dq_agent_report` (
                `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `uid` int(10) NOT NULL DEFAULT '0' COMMENT '用户ID',
                `rec_id` int(10) DEFAULT '0' COMMENT '一级',
                `rec2_id` int(10) DEFAULT '0' COMMENT '二级',
                `rec3_id` int(10) DEFAULT '0' COMMENT '三级',
                `start_time` int(10) NOT NULL DEFAULT '0' COMMENT '统计开始时间',
                `end_time` int(10) NOT NULL DEFAULT '0' COMMENT '统计结束时间',
                `order_money` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '订单总额',
                `win_money` decimal(20,2) NOT NULL DEFAULT '0.00' COMMENT '总赢金额',
                `update_time` int(10) NOT NULL DEFAULT '0' COMMENT '更新时间',
                `add_time` int(10) NOT NULL DEFAULT '0' COMMENT '添加时间',
                `is_rebate` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否返佣',
                PRIMARY KEY (`id`),
                KEY `idx_uid` (`uid`),
                KEY `idx_stime` (`start_time`),
                KEY `idx_etime` (`end_time`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }


        if (!$this->existTable("dq_game_type")) {
            $sql = "CREATE TABLE `dq_game_type` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                      `name` varchar(100) NOT NULL DEFAULT '' COMMENT '分类名称',
                      `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '图标',
                      `sort` tinyint(3) NOT NULL DEFAULT 0 COMMENT '排序',
                      `state` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1开 2关',
                      `create_time` int(11) NOT NULL DEFAULT 0,
                      `update_time` int(11) NOT NULL DEFAULT 0,
                      PRIMARY KEY (`id`) USING BTREE
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";
            $this->execute($sql);
        }
    
        if (!$this->existTableField("dq_game_user", "last_login_time")) {
            $sql = "ALTER TABLE `dq_game_user` ADD COLUMN `last_login_time` int(11) NOT NULL DEFAULT 0 COMMENT '最后登录时间'";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_set", "wallet_type")) {
            $sql = "ALTER TABLE `dq_game_set` ADD COLUMN `wallet_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '钱包类型 1共享 2转账模式（进出游戏转进转出）'";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_set", "game_code")) {
            $sql = "ALTER TABLE `dq_game_set` ADD COLUMN `game_code` varchar(255) NOT NULL DEFAULT '' COMMENT '游戏code';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_set", "type")) {
            $sql = "ALTER TABLE `dq_game_set` ADD COLUMN `type` int(11) NOT NULL DEFAULT 0 COMMENT '游戏分类';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_set", "content")) {
            $sql = "ALTER TABLE `dq_game_set` ADD COLUMN `content` text NOT NULL  COMMENT '游戏说明';";
            $this->execute($sql);
        }

        if (!$this->existTableField("dq_game_report_operating", "game_code")) {
            $sql = "ALTER TABLE `dq_game_report_operating` ADD COLUMN `game_code` varchar(255) NOT NULL DEFAULT '' COMMENT '游戏代号';";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_liansai_team", "team_id")) {
            $sql = "ALTER TABLE `dq_liansai_team` ADD COLUMN `team_id` INT(10) DEFAULT 0 NULL;";
            $this->execute($sql);
        }
        
        if (!$this->existTableField("dq_liansai_team", "logo")) {
            $sql = "ALTER TABLE `dq_liansai_team` ADD COLUMN `logo` VARCHAR(255) DEFAULT '' NULL;";
            $this->execute($sql);
        }        
            
        $full_table_name = 'dq_admin_permission';
        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '第三方游戏' AND `permission_url` = '/admin/game_set/list' AND `pid` = '1' ";
        if(!$selectData = $this->getTableData($select_sql)){
            $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('第三方游戏', '/admin/game_set/list', '1');";
            $this->execute($sql);
        }

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '第三方游戏' AND `permission_url` = '/admin/game_set/list' AND `pid` = '1' ";
        $selectData = $this->getTableData($select_sql);
        if(isset($selectData[0])) {
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '游戏设置' AND `permission_url` = '/admin/game_set/list' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('游戏设置', '/admin/game_set/list', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '用户管理' AND `permission_url` = '/admin/game_user/list' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('用户管理', '/admin/game_user/list', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '游戏订单' AND `permission_url` = '/admin/game_tx/list' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('游戏订单', '/admin/game_tx/list', '{$pid}');";
                $this->execute($sql);
            }


            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '游戏注单' AND `permission_url` = '/admin/game_zhudan/list' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('游戏注单', '/admin/game_zhudan/list', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '游戏报表' AND `permission_url` = '/admin/game_report/index' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('游戏报表', '/admin/game_report/index', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '获取游戏记录' AND `permission_url` = '/admin/game_zhudan/get_logs' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('获取游戏记录', '/admin/game_zhudan/get_logs', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '游戏分类' AND `permission_url` = '/admin/game_set/type' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('游戏分类', '/admin/game_set/type', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '添加游戏分类' AND `permission_url` = '/admin/game_set/type_add' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('添加游戏分类', '/admin/game_set/type_add', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '修改游戏分类' AND `permission_url` = '/admin/game_set/type_edit' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('修改游戏分类', '/admin/game_set/type_edit', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '删除游戏分类' AND `permission_url` = '/admin/game_set/type_delete' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('删除游戏分类', '/admin/game_set/type_delete', '{$pid}');";
                $this->execute($sql);
            }

            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '获取游戏余额' AND `permission_url` = '/admin/game_user/get_balance' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('获取游戏余额', '/admin/game_user/get_balance', '{$pid}');";
                $this->execute($sql);
            }


            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '余额转回' AND `permission_url` = '/admin/game_user/transfer_out' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('余额转回', '/admin/game_user/transfer_out', '{$pid}');";
                $this->execute($sql);
            }


            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '统计报表数据' AND `permission_url` = '/admin/game_report/get_game_report' AND `pid` = '{$pid}' ";
            if(!$selectData = $this->getTableData($select_sql)){
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('统计报表数据', '/admin/game_report/get_game_report', '{$pid}');";
                $this->execute($sql);
            }


        }

        $full_table_name = 'dq_admin_permission';

        $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '语言管理' AND `permission_url` = '' AND `pid` = '1' ";
        $selectData = $this->getTableData($select_sql);

        if(isset($selectData[0])) {
            $pid = $selectData[0]['id'];
            $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '国家列表' AND `permission_url` = '/admin/nation/index' AND `pid` = '{$pid}' ";
            $selectData = $this->getTableData($select_sql);
            if(!isset($selectData[0])) {
                $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('国家列表', '/admin/nation/index', '{$pid}');";
                $this->execute($sql);

                $select_sql = "SELECT * FROM `{$full_table_name}` WHERE `permission_name` = '国家列表' AND `permission_url` = '/admin/nation/index' AND `pid` = '{$pid}' ";
                $selectData = $this->getTableData($select_sql);
                if(isset($selectData[0])) {
                    $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('添加国家', '/admin/nation/add', '{$selectData[0]['id']}');";
                    $this->execute($sql);

                    $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('编辑国家信息', '/admin/nation/edit', '{$selectData[0]['id']}');";
                    $this->execute($sql);

                    $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('修改国家状态', '/admin/nation/state_edit', '{$selectData[0]['id']}');";
                    $this->execute($sql);

                    $sql = "INSERT INTO `{$full_table_name}` (`permission_name`, `permission_url`, `pid`) VALUES ('删除国家', '/admin/nation/delete', '{$selectData[0]['id']}');";
                    $this->execute($sql);
                }
            }


        }

        if (!$this->existTable("dq_nation")) {
            $sql = "CREATE TABLE `dq_nation`  (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '国家名称',
                      `flag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL DEFAULT '' COMMENT '国旗',
                      `lang` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL COMMENT '语种',
                      `is_defa` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否默认 1是 2否',
                      `state` tinyint(1) NOT NULL COMMENT '是否启用 1开启 2关闭',
                      `sort` tinyint(3) NOT NULL DEFAULT 0 COMMENT '排序',
                      `create_time` int(11) NOT NULL,
                      `update_time` int(11) NOT NULL,
                      PRIMARY KEY (`id`) USING BTREE
                    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ";

            $this->execute($sql);

        }
    }

}
