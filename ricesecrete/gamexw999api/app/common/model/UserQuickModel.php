<?php
namespace app\common\model;
use think\Model;
class UserQuickModel extends Model
{
	protected $name = 'user_quick';
	protected $schema = [
        'id'          => 'int',
        'uid'  =>  'int',
        'quick1'  =>  'decimal',
        'quick2'  =>  'decimal',
        'quick3'  =>  'decimal',
    ];
    
}