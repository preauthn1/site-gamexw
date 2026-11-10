<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver235 extends VersionBase
{

    public function run()
    {
        //实名认证通过后禁用优惠
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'user_verify_disable_discount') ){
            //实名认证通过后禁用优惠
            $this->addSysSets(
                'user_verify_disable_discount', '实名认证通过后才能领取奖励', 0, 'int', '实名认证通过后才能领取奖励'
            );
        }
    }
}