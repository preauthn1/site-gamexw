<?php

namespace app\common\upgrade\version;

use app\common\model\PddInviteRecordModel;
use app\common\model\PddTurntableRecordModel;

class Ver138 extends VersionBase
{

    public function run()
    {
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'seo_keyword') ){
            //添加网站SEO关键词对应参数
            $this->addSysSets(
                'seo_keyword', '网站SEO关键词', '', 'string', '网站SEO关键词'
            );
        }
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'seo_intro') ){
            //添加网站SEO简介对应参数
            $this->addSysSets(
                'seo_intro', '网站SEO简介', '', 'string', '网站SEO简介'
            );
        }

        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'share_title') ){
            //添加三方分享标题对应参数
            $this->addSysSets(
                'share_title', '三方分享标题', '', 'string', '三方分享标题'
            );
        }
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'share_img') ){
            //添加三方分享图标对应参数
            $this->addSysSets(
                'share_img', '三方分享图标', '', 'string', '三方分享图标'
            );
        }
        if( !$this->judgeDataIsExist('sys_sets', 'set_code', 'share_info') ){
            //添加三方分享简介对应参数
            $this->addSysSets(
                'share_info', '三方分享简介', '', 'string', '三方分享简介'
            );
        }
    }
}