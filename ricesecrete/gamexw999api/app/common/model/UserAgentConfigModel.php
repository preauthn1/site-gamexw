<?php

namespace app\common\model;

use app\api\redis\ApiRedis;
use think\Model;

class UserAgentConfigModel extends Model {

    protected $name = 'user_agent_website_config';

    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $scene = [
        'id'                        => 'int',
        'website_name'              => 'varchar',
        'website_web_url'           => 'varchar',
        'website_favicon'           => 'varchar',
        'website_pc_logo'           => 'varchar',
        'website_desktop_logo'      => 'varchar',
        'website_mobile_logo'       => 'varchar',
        'website_uid'               => 'int',
        'website_status'            => 'tinyint',
        'android_download_url'      => 'varchar',
        'ios_download_url'          => 'varchar',
        'pc_service_url'            => 'varchar',
        'mobile_service_url'        => 'varchar',
        'quick_recharge'            => 'varchar',
        'quick_withdrawal'          => 'varchar',
        'usdt_quick_recharge'       => 'varchar',
        'usdt_quick_withdrawal'     => 'varchar',
        'phone_bind'                => 'tinyint',
        'is_reg_type'               => 'tinyint',
        'phone_check'               => 'tinyint',
        'parent_allot'              => 'tinyint',
        'create_time'               => 'int',
        'update_time'               => 'int',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'website_name'=>lang('网站名称'),
            'website_web_url'=>lang('域名，多个英文逗号隔开，例：test.com,test1.com'),
            'website_favicon'=>lang('浏览器icon（32*32）'),
            'website_pc_logo'=>lang('PC端 LOGO（284*70）'),
            'website_desktop_logo'=>lang('加载logo（1024*1024）'),
            'website_mobile_logo'=>lang('移动端 LOGO（100*100）'),
            'website_uid'=>lang('代理对应用户id'),
            'website_status'=>lang('状态（1启用）'),
            'android_download_url'=>lang('安卓下载链接'),
            'ios_download_url'=>lang('iOS下载链接'),
            'pc_service_url'=>lang('pc端客服链接'),
            'mobile_service_url'=>lang('移动端客服链接'),
            'quick_recharge'=>lang('快捷银行充值金额'),
            'quick_withdrawal'=>lang('快捷银行提现金额'),
            'usdt_quick_recharge'=>lang('快捷USDT充值金额'),
            'usdt_quick_withdrawal'=>lang('快捷USDT提现金额'),
            'phone_bind'=>lang('注册账号类型1手机，2邮箱，3同时开启'),
            'is_reg_type'=>lang('需要额外填信息0否，1手机，2邮箱'),
            'phone_check'=>lang('注册验证码1开启，0关闭'),
            'parent_allot'=>lang('上级分配1开启，0关闭，开启后使用上面域名注册的用户，注册的用户上级为该代理的子代理（平均分配）'),
        ];
        $set_key = [
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    //清除代理配置相关缓存
    public static function clearCache($agentId, $domain){
        $apiRedis=new ApiRedis();
        //获取站点配置中的logo和网站名称
        $agentWebsiteKey = 'agent_website_key_'.$domain;
        $apiRedis->delete($agentWebsiteKey);

        //获取站点配置中的logo和网站名称(agentId)
        $agentWebsiteUidKey = 'agent_website_uid_key_'.$agentId;
        $apiRedis->delete($agentWebsiteUidKey);

        //获取配置了站点的代理id
        $agentWebsiteConfigKey = 'judge_is_set_website_config_agent_id_'. $agentId;
        $apiRedis->delete($agentWebsiteConfigKey);

        //根据id判断是否是站点代理
        $judgeIsSiteAgentByAgentId = 'judge_is_site_agent_by_agent_id_'. $agentId;
        $apiRedis->delete($judgeIsSiteAgentByAgentId);

        //判断域名是否绑定代理
        $judgeDomainIsBindAgent = 'judge_domain_is_bind_agent_'. $domain;
        $apiRedis->delete($judgeDomainIsBindAgent);

        //所有分站数据缓存
        $allSiteUrlKey = 'agent_all_website_url_key';
        $apiRedis->delete($allSiteUrlKey);

    }
}