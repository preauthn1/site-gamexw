<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver186 extends VersionBase
{

    public function run()
    {
        //返佣类型（all全部分佣,bypayin有充值条件）
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'fyfy_type') ){
            //返佣类型
            $this->addSysSets(
                'fyfy_type', '返佣类型', 'all', 'string', '代理设置', '返佣类型（all全部分佣,bypayin有充值条件）'
            );
        }
        //返佣周期（sametopay与返佣周期一致，his历史充值）
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'fyfy_cycle') ){
            //返佣周期
            $this->addSysSets(
                'fyfy_cycle', '返佣类型', 'his', 'string', '代理设置', '返佣周期（sametopay与返佣周期一致，his历史充值）'
            );
        }
        //充值金额条件
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'fyfy_recharge_condition') ){
            //充值金额条件
            $this->addSysSets(
                'fyfy_recharge_condition', '返佣类型', 0, 'number', '代理设置', '充值金额条件，0不限制'
            );
        }
    }
}