<?php

namespace app\common\upgrade\version;

class Ver150 extends VersionBase
{

    public function run()
    {
        //首次提现后最小充值金额配置
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'recharge_limit') ){
            //首次提现后最小充值金额配置参数
            $this->addSysSets(
                'recharge_limit', '首次提现后最小充值金额配置', 0, 'int', '最小充值金额'
            );
        }
    }
}