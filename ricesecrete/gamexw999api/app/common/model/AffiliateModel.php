<?php

namespace app\common\model;

use think\Model;

class AffiliateModel extends Model
{
    protected $name = 'affiliate';
    protected $schema = [
        'id' => 'int',
        'lang' => 'varchar',
        'today_tip' => 'text',
        'all_tip' => 'text',
        'team_url' => 'varchar',
        'share_title' => 'varchar',
        'share_platform' => 'text',
        'share_intro' => 'varchar',
        'service_title' => 'varchar',
        'service_info' => 'varchar',
        'service_tip' => 'varchar',
        'service_contact' => 'varchar',
        'service_platform' => 'text',
        'issues_list' => 'text',
        'rule_title' => 'varchar',
        'rule_info' => 'varchar',
        'brokerage_min' => 'decimal',
        'brokerage_max' => 'decimal',
        'brokerage_ratio' => 'decimal',
        'pop_info' => 'text',
        'pop_explain' => 'text',
        'calculate_title' => 'text',
        'calculate_text' => 'text',
    ];

    //存日志记录字段
    public function getField()
    {
        $field = [
            'id' => 'id',
            'lang' => lang('语言类型'),
            'today_tip' => lang('今天-数据提示说明'),
            'all_tip' => lang('全部-数据提示说明'),
            'team_url' => lang('团队说明链接'),
            'share_title' => lang('分享标题'),
            'share_platform' => lang('分享平台'),
            'share_intro' => lang('分享简介'),
            'service_title' => lang('服务群聊标题'),
            'service_info' => lang('服务群聊简介'),
            'service_tip' => lang('服务群聊提示信息'),
            'service_contact' => lang('服务群聊联系方式'),
            'service_platform' => lang('服务平台'),
            'issues_list' => lang('常问问题'),
            'rule_title' => lang('佣金奖励规则标题'),
            'rule_info' => lang('佣金奖励规则说明'),
            'brokerage_min' => lang('规则区间最小值'),
            'brokerage_max' => lang('规则区间最大值'),
            'brokerage_ratio' => lang('规则区间比例'),
            'pop_info' => lang('奖励规则说明'),
            'pop_explain' => lang('奖励规则补充'),
            'calculate_title' => lang('佣金计算规则标题'),
            'calculate_text' => lang('佣金计算规则内容')
        ];
        $set_key = [

        ];
        return ['field' => $field, 'set_key' => $set_key, 'table' => $this->name];
    }

}