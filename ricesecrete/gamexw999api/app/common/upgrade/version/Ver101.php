<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver101 extends VersionBase {

    public function run() {
        // 系统添加数据
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'allow_not_recharge') ){
            $this->insertData('sys_sets', [
                'set_label' => '基础设置',
                'set_type' => 'int',
                'set_code' => 'allow_not_recharge',
                'set_title' => '允许未审核数量',
                'set_value' => 0,
                'set_info' => '1为用户只允许有一笔未审核的充值订单，0或为空为不限制',
            ]);
        }
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'allow_not_withdrawal') ){
            $this->insertData('sys_sets', [
                'set_label' => '基础设置',
                'set_type' => 'int',
                'set_code' => 'allow_not_withdrawal',
                'set_title' => '允许未审核数量',
                'set_value' => 0,
                'set_info' => '1为用户只允许有一笔未审核的提现订单，0或为空为不限制',
            ]);
        }
    }
}