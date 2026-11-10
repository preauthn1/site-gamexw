<?php

namespace app\common\model;

use think\Model;

class UserWaterModel extends Model {

    protected $name = 'user_water';

    protected $scene = [
        'id'                =>      'int',
        'uid'               =>      'int',
        'amount'            =>      'decimal',
        'multiple'          =>      'int',
        'water'             =>      'decimal',
        'origin_id'         =>      'int',
        'type'              =>      'tinyint',
        'remark'            =>      'varchar',
        'create_time'       =>      'bigint',
    ];

}