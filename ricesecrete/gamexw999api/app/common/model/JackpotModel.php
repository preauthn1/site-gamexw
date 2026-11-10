<?php
namespace app\common\model;
use Carbon\Carbon;
use think\facade\Db;
use think\Model;
class JackpotModel extends Model
{
    protected $name = 'jackpot';
    protected $schema = [
        'id'          => 'int',
        'j_type'          => 'tinyint',
        'uid'          => 'int',
        'username'  =>  'varchar',
        'head_img' =>  'varchar',
        'price' =>  'decimal',
        'type' =>  'tinyint',
        'day' =>  'int',
        'ranking' =>  'int',
        'min_rand' =>  'decimal',
        'max_rand' =>  'decimal',
        'upper_limit' =>  'decimal',
        'create_time' =>  'bigint',
        'update_time' =>  'bigint',
    ];


    //存日志记录字段
    public function getField(){
        $field = [
            'id'=>'id',
            'j_type'=>lang('类型'),
            'uid'=>lang('活动开始时间'),
            'username'=>lang('用户名'),
            'head_img'=>lang('投下给你'),
            'price'=>lang('初始下注金额'),
            'type'=>lang('用户类型'),
            'day'=>lang('日期'),
            'ranking'=>lang('排名'),
            'min_rand'=>lang('假数据随机最小值'),
            'max_rand'=>lang('假数据随机最大值'),
            'upper_limit'=>lang('假数据金额上限'),
            'create_time'=>lang('创建时间'),
            'update_time'=>lang('修改时间'),
        ];
        $set_key = [
            ['j_type'=>[1=>lang('日榜'),2=>lang('周榜'),3=>lang('月榜')]],
            ['type'=>[1=>lang('真实用户'),2=>lang('假数据')]],
        ];
        return ['field'=>$field,'set_key'=>$set_key,'table'=>$this->name];
    }

    /**
     * 根据类型获取时间段查询和当期时间
     * @param $jType int 1:日榜 2:周榜 3:月榜
     * @param $issue int 期数，0当期 -1上期
     * @return array|false
     */
    public static function getTimeByJType(int $jType, int $issue = 0){
        switch ($jType) {
            case 1:
                //日
                $day = $startDate = $endDate = date('Ymd', Carbon::now()->addDay($issue)->startOfDay()->timestamp);
                break;
            case 2:
                //周
                $startDate = date('Ymd', Carbon::now()->addWeek($issue)->startOfWeek()->timestamp);
                $endDate = date('Ymd', Carbon::now()->addWeek($issue)->endOfWeek()->timestamp);
                $day = $startDate;
                break;

            case 3:
                //月
                $startDate = date('Ymd', Carbon::now()->addMonth($issue)->startOfMonth()->timestamp);
                $endDate = date('Ymd', Carbon::now()->addMonth($issue)->endOfMonth()->timestamp);
                $day = $startDate;
                break;
            default:
                return false;
        }
        return ['startDate' => $startDate, 'endDate' => $endDate, 'day' => $day];
    }

    //根据类型获取下次开始时间
    public static function getNextStartTime($jType){
        switch ($jType){
            case 1:
                //日榜
                $startTime = Carbon::today()->addDay(+1)->timestamp;
                break;
            case 2:
                //周榜
                $startWeekTime = Carbon::now()->startOfWeek()->timestamp;
                $startTime = Carbon::parse(date('Y-m-d H:i:s', $startWeekTime))->addWeek(+1)->timestamp;
                break;
            case 3:
                //月榜
                $startWeekTime = Carbon::now()->startOfMonth()->timestamp;
                $startTime = Carbon::parse(date('Y-m-d H:i:s', $startWeekTime))->addMonth(+1)->timestamp;
                break;
            default:
                return 0;
        }
        return $startTime;
    }


    //统计排行耪排名
    public static function rank_statistics($type){
        $timeResult = self::getTimeByJType($type);
        $sql = 'SET @rank := 0;';
        Db::execute($sql);
        $sql = "UPDATE dq_jackpot
JOIN (
    SELECT
        id,  -- 假设表中有唯一标识符列 id
        @rank := @rank + 1 AS new_ranking
    FROM
        dq_jackpot
    ORDER BY
        price DESC
) ranked
ON dq_jackpot.id = ranked.id
SET dq_jackpot.ranking = ranked.new_ranking
WHERE dq_jackpot.j_type = {$type} AND dq_jackpot.day = {$timeResult['day']};";
        Db::execute($sql);
        return true;
    }

    //根据配置获参与最大人数
    public static function getMaxNum($config){
        $maxNum = 0;
        foreach ($config as $v){
            $maxNum = max($maxNum, $v['end']);
        }
        return $maxNum;
    }
}