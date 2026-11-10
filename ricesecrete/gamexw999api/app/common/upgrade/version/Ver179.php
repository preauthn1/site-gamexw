<?php

namespace app\common\upgrade\version;

use app\model\GameBetsex;

class Ver179 extends VersionBase
{

    public function run() {
        //添加银行卡验证
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'add_bank_verify') ){
            //添加银行卡验证
            $this->addSysSets(
                'add_bank_verify', '添加银行卡验证', 1, 'int', '提现设置', '1开启后只能添加相同开户行的银行卡，且别的会员不能添加该银行卡，0关闭后则不限制'
            );
        }
    }
}