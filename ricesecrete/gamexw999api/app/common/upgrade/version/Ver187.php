<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver187 extends VersionBase
{

    public function run()
    {
        //自动出款充值提现比
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'auto_withdrawal_recharge_rate') ){
            //自动出款充值提现比
            $this->addSysSets(
                'auto_withdrawal_recharge_rate', '自动出款充值提现比', '0.00', 'string', '提现设置', '自动出款充值提现比 用户充值总额/(用户提现金额+用户充值成功金额+用户余额) 小于设定值即可自动出款，例：0.3为30%，公式计算小于该值则会自动出款'
            );
        }
    }
}