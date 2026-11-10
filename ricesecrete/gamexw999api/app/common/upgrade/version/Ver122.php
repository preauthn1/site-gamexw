<?php

namespace app\common\upgrade\version;

use think\facade\Db;

class Ver122 extends VersionBase {

    public function run() {
        //代理相同号码注册
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'same_phone_register') ) {
            //添加网站对应参数
            $this->addSysSets(
                'same_phone_register', '相同号码注册', 0, 'string', '代理设置'
            );
        }
    }
}