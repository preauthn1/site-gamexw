<?php
/**
 * 飞行员报表模型
 */

namespace app\common\model;
use think\Model;

class GameZhudanModel extends Model
{

    protected $name = 'game_zhudan';

    protected $schema = [
        'id'            => 'bigint',
        'zd_id'         => 'varchar',
        'username'      => 'varchar',
        'game_id'       => 'bigint',
        'bets_coin'     => 'decimal',//投注金额
        'reward_coin'   => 'decimal',//收益金额
        'before_coin'   => 'decimal',//
        'after_coin'    => 'decimal',//
        'add_time'      => 'bigint',//添加时间
        'begin_time'    => 'bigint',
        'end_time'      => 'bigint',
        'game_name'     =>  'varchar',
        'lebal'         =>  'tinyint',
        'status'         =>  'tinyint',
    ];

    /**
     * @param $value
     * @return false|string
     */
    public function getAddTimeAttr($value)
    {
        return $value ?  date('Y-m-d H:i:s',$value) : '';
    }

    /**
     * @param $value
     * @return false|string
     */
    public function getBeginTimeAttr($value)
    {
        return $value ?  date('Y-m-d H:i:s',$value) : '';

    }

    /**
     * @param $value
     * @return false|string
     */
    public function getEndTimeAttr($value)
    {
        return $value ?  date('Y-m-d H:i:s',$value) : '';
    }
}