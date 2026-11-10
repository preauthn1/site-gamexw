<?php

namespace app\common\upgrade\version;

class Ver247 extends VersionBase
{

    public function run()
    {
        //反水赠送类型
        if (!$this->judgeDataIsExist('sys_sets', 'set_code', 'sys_cj_backpay')) {
            //反水赠送类型
            $this->addSysSets(
                'sys_cj_backpay', '反水赠送类型', 0, 'int', '反水设置', '反水赠送类型（0真金钱包，1彩金钱包）'
            );
        }
    }

}
