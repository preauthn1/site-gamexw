<?php

namespace app\common\model;

class GameBetFeeModel extends BaseModel{

    protected $name = 'game_bet_fee';
    protected $autoWriteTimestamp = 'int';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $schema = [
        'id'            =>  'int',
        'tcid'          =>  'int',
        'uid'          =>  'int',
        'gid'           =>  'int',
        'gbid'          =>  'int',
        'gbsn'          =>  'varchar',
        'old_price'     =>  'decimal',
        'fee_ratio'     =>  'tinyint',
        'fee'           =>  'decimal',
        'now_price'    =>  'decimal',
        'remarks'       =>  'varchar',
        'create_time'   =>  'int',
        'update_time'   =>  'int',
    ];

}