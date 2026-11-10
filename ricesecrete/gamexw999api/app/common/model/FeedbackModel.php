<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2021/6/9
 * Time: 14:50
 */
namespace app\common\model;
use think\Model;
class FeedbackModel extends Model
{
    protected $name = 'feedback';
    protected $schema = [
        'id'          => 'int',
        'uid'  =>  'tinyint',
        'content'=>'text',
        'add_time' => 'bigint',
    ];

}