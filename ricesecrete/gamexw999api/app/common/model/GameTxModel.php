<?php
namespace app\common\model;
use think\Model;
class GameTxModel extends Model
{
    protected $name = 'game_tx';
    protected $schema = [
        'id'      => 'int',
        'game_id' =>  'int',
        'uid' =>  'int',
        'bet_amount' =>  'decimal',
        'actual_amount' =>  'decimal',
        'add_time' =>  'bigint',
        'ref_id' =>  'text',
        'tx_number' =>  'text',
        'txid' =>  'int',
        'odds' =>  'decimal',
        'bet_status' =>  'int',
        'status'=>'text',
        'tx_type'=>'int',
        'payout'=>'decimal',
        'update_time' =>  'bigint',
        'bet_time' =>  'bigint',
        'parlay_type'=>'text',//串关类型 Parlay_Mix, Parlay_System, Parlay_Lucky, ** SingleBet_ViaLucky
        'detail'=>'text',//json 串关详情
        'game_name'=>'text',//3rd类型
        'product_name'=>'text',//3rd类型
        'extra'=>'text',//3rd类型
        'bet_choice'=>'text',
    ];

    /**
     * extra 将字段处理好  存进详情字段 后台点击详情的时候 直接遍历该字段
     * @var string[]
     */
    public $extra = [
        'ref_id' => '游戏方唯一ID',
        'match_type' => '比赛类型',
        'match_id' => '比赛ID',
        'ls_name' => '联赛名称',
        'home_name' => '主队',
        'home_score' => '得分',
        'away_name' => '客队',
        'away_score' => '得分',
        'option_name' => '投注项目',
        'option_type' => '投注类型',
        'status' => '结果',
        'odds' => '赔率',
        'match_start_time' => '赛事开始时间',
        'match_end_time' => '赛事结束时间',
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
    public function getBetTimeAttr($value)
    {
        return $value ?  date('Y-m-d H:i:s',$value) : '';

    }

    /**
     * @param $value
     * @return false|string
     */
    public function getUpdateTimeAttr($value)
    {
        return $value ?  date('Y-m-d H:i:s',$value) : '';
    }


    public function User(){
        return $this->hasOne(UserModel::class, 'id', 'uid')->bind(['username' => 'username']);
    }
}