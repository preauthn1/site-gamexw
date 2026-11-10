<?php
namespace app\api\factory;

use app\common\lib\Util;
use app\common\model\GameTxModel;
use think\facade\Db;


trait GameTxFactory
{

    /**
     * 获取表模型 未存在就新增 已存在直接返回
     * @param string $type
     * @param boolean $add 是否新增 flase的时候  就算没有也不新增
     * @return string
     */
    public function getTxTable(string $type = 'today', bool $add = true)
    {
        $tableName = self::getTxTableByDay($type, $add);
        /* @var GameTxModel $GameTxModel*/
        $GameTxModel = app()->make(GameTxModel::class);
        //动态设置后缀 直接将 qd_game_tx 去掉 拿到时间 就能获取到模型了
        if (empty(Util::existTable($tableName))) {
            ajax_return(lang('数据不存在'),0);
        }else{
            $model = $GameTxModel->setSuffix(explode('dq_game_tx', $tableName)[1]);
            return $model;
        }
    }


    /**
     * 获取新建的注单表名称
     * @param string $type
     * @return string
     */
    public function getTxTableByDay($type = 'today', $add = ''): string
    {
        if ($type == 'today') {
            $Ymd =Util::timeToStr(time(), 'Ymd');
        } elseif ($type == 'yesterday') {
            $Ymd = Util::timeToStr(strtotime("-1 day"), 'Ymd');
        } else {
            $Ymd = Util::timeToStr(Util::getMkTime($type), 'Ymd');
        }
        $full_table_name = "dq_game_tx_{$Ymd}";
        if($add){
            self::createTxDayTable($full_table_name);
        }
        return $full_table_name;
    }

    /**
     * 创建当日注单统计表
     */
    public function createTxDayTable($full_table_name) {
        //创建当日变帐统计表
        if (empty(Util::existTable($full_table_name))) {
            $sql = <<<EOC
                CREATE TABLE `{$full_table_name}`  (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `game_id` int(11) NOT NULL DEFAULT 0 COMMENT '游戏id',
              `uid` int(11) NOT NULL DEFAULT 0 COMMENT '用户id',
              `ref_id` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '三方游戏订单号',
              `tx_number` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '自己交易id',
              `txid` bigint(20) NOT NULL DEFAULT 0 COMMENT '三方交易ID',
              `bet_amount` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '投注金额',
              `actual_amount` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '结算金额',
              `odds` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '赔率',
              `bet_status` int(11) NOT NULL DEFAULT 0 COMMENT '0待确认 1确认 2取消 3已结算 4确认中 5已接单 6已拒单',
              `tx_type` int(11) NOT NULL DEFAULT 0 COMMENT '0单张注单 1串关注单2三方注单3CASHOUT',
              `status` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '结果',
              `payout` decimal(20, 2) NOT NULL DEFAULT 0.00 COMMENT '赢的奖金',
              `bet_time` bigint(20) NOT NULL DEFAULT 0 COMMENT '下注时间',
              `parlay_type` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '串关订单类型',
              `detail` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '串关订单详情',
              `game_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '游戏名称',
              `product_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '产品名称',
              `extra` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '额外参数',
              `bet_choice` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '投注选项',
              `add_time` bigint(20) NOT NULL COMMENT '添加时间',
              `update_time` bigint(20) NOT NULL DEFAULT 0 COMMENT '修改时间',
              PRIMARY KEY (`id`) USING BTREE,
              UNIQUE INDEX `tx_number`(`tx_number`) USING BTREE
            ) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = DYNAMIC;
EOC;
            Db::execute($sql);
        }
    }

}