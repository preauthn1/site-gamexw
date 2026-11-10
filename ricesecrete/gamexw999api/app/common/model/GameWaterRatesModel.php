<?php
namespace app\common\model;
use think\facade\Db;
use think\Model;
class GameWaterRatesModel extends Model
{
    protected $name = 'game_water_rates';
    protected $pk = 'gwr_gid';

    protected $schema = [
        'gwr_gid' =>  'int',
        'gwr_gtid' =>  'int',
        'gwr_water_rate' =>  'decimal',
        'gwr_cjwater_rate' =>  'decimal',
    ];

    //存日志记录字段
    public function getField(){
        $field = [
            'gwr_gid' => lang('游戏id'),
            'gwr_gtid' => lang('游戏分类id'),
            'gwr_water_rate' => lang('真金流水扣减比率'),
            'gwr_cjwater_rate' => lang('彩金流水扣减比率'),
        ];
        return ['field' => $field, 'table' => $this->name];
    }


    /** 设置游戏分类或游戏流水扣减比率
     * @param $gtid 游戏分类ID gtid gid有其一即可，优先识别gid
     * @param $gid 游戏ID 指定GID的时候如果设置比例不存在会增加一条新的记录 有值时oldrate无效
     * @param $wtype 设置比率类型 1-真金流水比率 2-彩金流水比率
     * @param $rate 要设置的扣减比率 -1表示设置为分类游戏中最多（非最大）的扣减比率【此时oldrate无效】，如游戏分类中没有设置扣减比率，则使用默认值1，>=0表示设置具体的扣减比率,>1的自动变1
     * @param $oldrate 原来的扣减比率，指定具体的值 0~1之前的数值则表示只将原来是这个比率的进行更改，-1表示全部更改
     * @return bool
     */
    public static function setGameWaterRates($gtid,$gid,$wtype,$rate,$oldrate){
        if ($gid){
            $gtid=GamesModel::where('g_id',$gid)->value('g_type');
            $gi=self::where('gwr_gid',$gid)->find();
            if (!$gi){
                self::insert(['gwr_gid'=>$gid,'gwr_gtid'=>$gtid]);
            }
        }
        if (!$gtid) return false;
        if ($rate>1) $rate=1;
        if ($wtype==1){
            $field='gwr_water_rate';
        }else{
            $field='gwr_cjwater_rate';
        }
        if ($rate==-1){//找到分类中最多的扣减比率，并重新设定比率，如果找不到则使用默认值1
            $rlt=Db::query("select * from(
select {$field},count(*) as num from dq_game_water_rates where gwr_gtid={$gtid} group by {$field}
) tb order by num desc limit 1");
            //print_r([$rlt,$field]);
            if (count($rlt)>0 && (!empty($rlt[0][$field]) || $rlt[0][$field]===0)){
                $rate=$rlt[0][$field];
            }else{
                $rate=1;
            }
        }
        $m=self::newQuery();
        if ($gid){
            $m->where('gwr_gid',$gid);
        }else{
            $m->where('gwr_gtid',$gtid);
        }
        if ($oldrate==-1 || $gid){
            $flag=$m->update([$field=>$rate]);
        }else{
            $flag=$m->where($field,$oldrate)->update([$field=>$rate]);
        }
        return !!$flag;
    }

    /** 根据游戏分类获取分类下的游戏扣减比率
     * @param $gtid
     * @return mixed
     */
    public static function get_game_water_rates($gtid)
    {
        $rates = GameWaterRatesModel::where('gwr_gtid',$gtid)->fieldRaw('gwr_water_rate as rates,count(*) as num')->group('gwr_water_rate')->select()->toArray();
        $cjrates=GameWaterRatesModel::where('gwr_gtid',$gtid)->fieldRaw('gwr_cjwater_rate as rates,count(*) as num')->group('gwr_cjwater_rate')->select()->toArray();
        return ["rates"=>$rates,"cjrates"=>$cjrates];
    }

    //初始化游戏比率设置列表，补齐没有的游戏
    public static function initRates(){
        $rlt=GamesModel::join('dq_game_water_rates','g_id=gwr_gid','left')->field('g_id,g_type')->whereNull('gwr_gid')->select()->toArray();
        foreach ($rlt as $v){
            self::setGameWaterRates($v['g_type'],$v['g_id'],1,-1,-1);
            self::setGameWaterRates($v['g_type'],$v['g_id'],2,-1,-1);
        }
    }

    /** 获取游戏扣减比率列表
     * @param array $limit 分页参数array（offset,limit）
     * @param null|int $gtid 类型ID
     * @param null|string $gname 游戏名称
     * @param null|int $wtype 1-真金流水比率 2-彩金流水比率 null-全部
     * @param null|float $rate
     * @return mixed
     */
    public static function game_water_rates_list($limit=null,$gtid=null,$gname=null,$wtype=null,$rate=null){
        //初始化游戏比率设置列表，补齐没有的游戏
        self::initRates();
        if (empty($limit)){
            $limit=["offset"=>0,"limit"=>10];
        }
        $m=(new GameWaterRatesModel())
            ->join('dq_game_type',"gwr_gtid=id")
            ->join('dq_games',"gwr_gid=g_id")
            ->join('dq_game_infos','gwr_gid=gi_gid');
        if($gtid){
            $m=$m->where('gwr_gtid',$gtid);
        }
        if($gname){
            $m=$m->where('gi_name', 'like', '%'.$gname.'%');
        }
        if($wtype==1){
            $field='gwr_water_rate';
        }else if($wtype==2){
            $field='gwr_cjwater_rate';
        }else{
            $field=null;
        }
        if(($rate || $rate === 0) && $field){
            $m=$m->where($field,$rate);
        }
        $cnt=(clone $m)->count();
        $datas=$m
            ->field('gwr_gid,gwr_gtid,gwr_water_rate,gwr_cjwater_rate,gi_name,g_code,g_state,g_hot,g_show,g_is_del,name')
            ->order('gwr_gid')->limit($limit['offset'],$limit['limit'])->select()->toArray();
        return ['total'=>$cnt,'data'=>$datas];
    }

}