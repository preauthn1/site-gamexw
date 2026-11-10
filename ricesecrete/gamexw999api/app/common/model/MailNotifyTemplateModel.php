<?php

namespace app\common\model;

use think\Model;

class MailNotifyTemplateModel extends Model {

    protected $schema = [
        'id' => 'int',
        'state' => 'tinyint',
        'desc' => 'varchar',
        'sign' => 'varchar',
        'title' => 'varchar',
        'content' => 'text',
    ];

    protected $name = 'mail_notify_template';

    public function getField(){
        $field = [
            'id' => 'id',
            'state' => lang('邮件模板状态'),
            'desc' => lang('邮件模板简介'),
            'sign' => lang('邮件模板标识'),
            'title' => lang('邮件模板标题'),
            'content' => lang('邮件模板内容'),
        ];
        $set_key = [
            ['state' => [2 => lang('关闭'), 1 => lang('开启')]],
        ];
        return ['field'=>$field, 'set_key'=>$set_key, 'table'=>$this->name];
    }

}