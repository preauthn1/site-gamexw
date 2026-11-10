<?php
namespace app\api\factory;

use app\common\lib\Util;
use app\common\model\GameZhudanModel;
use think\facade\Db;


trait GameZhuDanFactory
{

    /**
     * 获取表模型 未存在就新增 已存在直接返回
     * @param string $type
     * @param boolean $add 是否新增 flase的时候  就算没有也不新增
     * @return string
     */
    public function getZhudanTable(string $type = 'today', bool $add = true)
    {
        $tableName = self::getZhuDanTableByDay($type, $add);
        /* @var GameZhudanModel $GameZhudanModel*/
        $GameZhudanModel = app()->make(GameZhudanModel::class);
        //动态设置后缀 直接将 qd_game_zhudan 去掉 拿到时间 就能获取到模型了
        if (empty(Util::existTable($tableName))) {
            ajax_return(lang('数据不存在'),0);
        }else{
            $model = $GameZhudanModel->setSuffix(explode('dq_game_zhudan', $tableName)[1]);
            return $model;
        }
    }


    /**
     * 获取新建的注单表名称
     * @param string $type
     * @return string
     */
    public function getZhuDanTableByDay($type = 'today', $add = ''): string
    {
        if ($type == 'today') {
            $Ymd =Util::timeToStr(time(), 'Ymd');
        } elseif ($type == 'yesterday') {
            $Ymd = Util::timeToStr(strtotime("-1 day"), 'Ymd');
        } else {
            $Ymd = Util::timeToStr(Util::getMkTime($type), 'Ymd');
        }
        $full_table_name = "dq_game_zhudan_{$Ymd}";
        if($add){
            self::createZhuDanDayTable($full_table_name);
        }
        return $full_table_name;
    }

    /**
     * 创建当日注单统计表
     */
    public function createZhuDanDayTable($full_table_name) {
        //创建当日变帐统计表
        if (empty(Util::existTable($full_table_name))) {
            $sql = <<<EOC
                CREATE TABLE `{$full_table_name}` (
                  `id` bigint(20) NOT NULL AUTO_INCREMENT,
                  `zd_id` varchar(225) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0' COMMENT '注单id',
                  `username` varchar(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0' COMMENT '用户名',
                  `game_id` int(11) NOT NULL DEFAULT 0 COMMENT '游戏id',
                  `bets_coin` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '投注金额',
                  `reward_coin` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '收益金额',
                  `before_coin` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '投注后余额',
                  `after_coin` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '结算后余额',
                  `add_time` bigint(20) NOT NULL DEFAULT 0 COMMENT '添加时间',
                  `begin_time` bigint(20) NOT NULL DEFAULT 0 COMMENT '游戏开始时间',
                  `end_time` bigint(20) NOT NULL DEFAULT 0 COMMENT '游戏结束时间 ',
                  `game_name` varchar(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'flyer' COMMENT '游戏名称',
                  `lebal` tinyint(1) NOT NULL DEFAULT 1 COMMENT '用户标签，1一般用户，2测试用户',
                  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0无 1下注 2取消 3输 4赢',
                  PRIMARY KEY (`id`, `zd_id`) USING BTREE,
                  INDEX `idx_username`(`username`) USING BTREE,
                  INDEX `idx_begin_time`(`begin_time`) USING BTREE,
                  INDEX `idx_end_time`(`end_time`) USING BTREE,
                  INDEX `idx_add_time`(`add_time`) USING BTREE,
                  INDEX `idx_bets_coin`(`bets_coin`) USING BTREE,
                  INDEX `reward_coin`(`reward_coin`) USING BTREE,
                  UNIQUE INDEX `zd_id_key`(`zd_id`) USING BTREE
                ) ENGINE = MyISAM AUTO_INCREMENT = 0 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci COMMENT = '注单记录表' ROW_FORMAT = Dynamic;
EOC;
            Db::execute($sql);
        }
    }

}