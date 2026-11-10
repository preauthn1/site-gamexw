<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver165 extends VersionBase
{

    public function run()
    {
        //推广有效有效流水
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'tg_condition_valid_bet') ){
            //推广有效有效流水
            $this->addSysSets(
                'tg_condition_valid_bet', '推广有效有效流水', '0', 'string', '推广设置', '设置有效流水，如设置：100，推广进来的用户有效下注100，才算有效推广用户，0 不限制'
            );
        }
    }
}