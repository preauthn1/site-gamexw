<?php

namespace app\common\upgrade\version;

class Ver174 extends VersionBase
{

    public function run()
    {
        //提现支付密码验证是否开启
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'pay_passwd_verify') ){
            //提现支付密码验证是否开启
            $this->addSysSets(
                'pay_passwd_verify', '支付密码验证', 0, 'int', '支付密码验证'
            );
        }
        //提现支付密码修改是否需要手机验证
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'paypasswd_phone_check') ){
            //添加提现支付密码修改是否需要手机验证
            $this->addSysSets(
                'paypasswd_phone_check', '支付密码修改验证手机', 0, 'int', '支付密码修改验证手机'
            );
        }
    }
}