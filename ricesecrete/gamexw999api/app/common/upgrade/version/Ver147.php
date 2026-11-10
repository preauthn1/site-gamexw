<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver147 extends VersionBase
{

    public function run() {
        //提现自动审核
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'withdrawal_auto') ){
            //提现自动审核
            $this->addSysSets(
                'withdrawal_auto', '提现自动审核', '0', 'int', '提现设置', '提现自动审核（1开启，0关闭）'
            );
            //打码倍数
            $this->addSysSets(
                'savings_multiple', '打码倍数', '0', 'int', '提现设置', '当天充值满足的打码倍数'
            );
            //最高出款金额
            $this->addSysSets(
                'auto_max_amount', '最高出款金额', '0', 'int', '提现设置', '最高出款金额'
            );
        }
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'auto_withdrawal_pay_id') ){
            //自动出款渠道
            $this->addSysSets(
                'auto_withdrawal_pay_id', '自动出款渠道', '', 'uint', '提现设置', '自动出款渠道，不设置将不能自动出款'
            );
        }

    }

}