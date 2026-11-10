<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver173 extends VersionBase
{

    public function run()
    {
        //提现代付失败额外状态
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'withdrawal_extra_status') ){
            //提现代付失败额外状态
            $this->addSysSets(
                'withdrawal_extra_status', '提现代付失败额外状态', '0', 'string', '提现设置', '提现代付失败额外状态，如果开启后三方代付失败后需要重新审核钱才会退回用户余额'
            );
        }
    }
}