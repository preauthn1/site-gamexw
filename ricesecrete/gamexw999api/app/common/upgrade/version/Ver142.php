<?php

namespace app\common\upgrade\version;

class Ver142 extends VersionBase
{

    public function run()
    {
        //基础货币配置
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'monetary_type') ){
            //添加网站SEO关键词对应参数
            $this->addSysSets(
                'monetary_type', '基础货币配置', '', 'string', '基础货币配置'
            );
        }
    }
}