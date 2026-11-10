<?php

namespace app\common\upgrade\version;

class Ver248 extends VersionBase
{

    public function run()
    {
        //优惠是否可以重复领取
        if (!$this->judgeDataIsExist('wallet_system', 'wallet_field', 'discount_can_repeat')) {
           $this->addWalletData('discount_can_repeat', 1, '优惠是否可以重复领取');
        }
        //优惠是否可以重复领取条件金额
        if (!$this->judgeDataIsExist('wallet_system', 'wallet_field', 'discount_can_repeat_condition_amount')) {
           $this->addWalletData('discount_can_repeat_condition_amount', 0, '优惠是否可以重复领取条件金额');
        }
    }

}
