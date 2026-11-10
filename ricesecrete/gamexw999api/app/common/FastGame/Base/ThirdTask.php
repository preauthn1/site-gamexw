<?php


namespace app\common\FastGame\Base;


use app\model\GameApis;
use app\model\GameBetsTemp;
use app\model\GameConfigs;
use app\model\Games;
use app\model\ThirdConfigs;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\Util;
use think\facade\Db;

/**
 * 三方平台任务执行类，在app\common\fastgame命名空间下继承，命名为 ThirdTask_{TCCODE}，同时要支持ThirdInterface
 * Class ThirdTask
 * @package app\common\FastGame\Base
 * @author twt326
 * @date 2022-09-17 12:22
 */
abstract class ThirdTask
{
    public $cfg=null;
    public $trdid=null;
    public $trdcode=null;
    public $cfgvalues=null;
    public $apiscfgvalues=[];
    public $trdClass=null;

    public $nowGetbetlistFunc='GETBETLIST';//当前获取注单列表的接口名称，默认为GETBETLIST，仅由封装处理值变更


    public static function run($tc_code = null){
        $where = ['tc_status' => 1];
        if (!is_null($tc_code)) {
            $where['tc_code'] = $tc_code;
        }
        //从三方平台配置库中取有效
        $trds=ThirdConfigs::where($where)->field('tc_id,tc_code')->select()->toArray();
        Utils::L('test',['执行任务开始：',$trds]);
        //执行 TODO 多线程
        for($i=0;$i<count($trds);$i++){
            //拿到继承任务类

            $cls=getc('ThirdTask_'.strtolower($trds[$i]['tc_code']));
            if ($cls){
                try {
                    /** @var ThirdTask $obj */
                    $extClass=new $cls();
                    $extClass->init($trds[$i]['tc_id'],$trds[$i]['tc_code']);
                    $extClass->exec();
                } catch (\Exception $e) {
                    Utils::L('ThirdTaskErrLogs', [
                        'msg' => '任务获取注单错误',
                        'track' => [
                            'errormsg' => $e->getMessage(),
                            'cfg' => $trds[$i]
                        ]]);
                    continue;
                }
            }else{
                Utils::L('ThirdErrLogs_notice',['msg'=>'三方平台ThirdTask继承类未实现，如此平台非主动平台或无计划任务则无需理会本通知','track'=>[
                    'tc_code'=>$trds[$i]['tc_code']
                ]]);
            }
        }
    }

    /**
     * 初始化任务，可重写
     * @param $trdid
     * @author twt326
     * @date 2022-08-25 12:21
     */
    public function init($trdid,$trdcode){
        $this->trdid=$trdid;
        $this->trdcode=$trdcode;
        $this->config(null);
        //处理缓存
        $cfgvalues=$this->cfg['trdcfg']['tc_taskvalue'];
        if (empty($cfgvalues)) {
            $cfgvalues=[
                'thirdst'=>0,
                'statst'=>0,
                'jsst'=>0,
                'fyst'=>0
            ];

        }else{
            if (is_string($cfgvalues)) $cfgvalues=json_decode($cfgvalues,true);
        }
        $this->cfgvalues=$cfgvalues;
    }

    /**
     * 读取或写入配置 ，可重写
     * @param null $value
     * @param bool $refresh
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author twt326
     * @date 2022-08-25 12:21
     */
    public function config($value=null,$refresh=false){
        if ($this->cfg==null || $refresh){
            if (!$refresh){
                $this->cfg=Redis::hget('trdcfgs',$this->trdcode);
                if ($this->cfg) $this->cfg=json_decode($this->cfg,true);
            }
            if ($refresh || !$this->cfg){
                $this->cfg=Games::getConfigs(null,$this->trdid,true);
                Redis::hset('trdcfgs',$this->trdcode,json_encode($this->cfg));
            }
        }
        if ($this->trdClass==null){
            $cls=getc('Third_'.strtolower($this->cfg['trdcfg']['tc_code']));
            if ($cls) {
                $this->trdClass=new $cls($this->cfg);
            }else{
                Utils::L('ThirdErrLogs',[
                    'msg'=>'三方平台ThirdTask初始化配置出错',
                    'suggest'=>'请继承Third类并实现相关方法',
                    'track'=>[
                        'tc_code'=>$this->cfg['trdcfg']['tc_code'],
                        'cls'=>'Third_'.strtolower($this->cfg['trdcfg']['tc_code'])
                    ]]);
            }
        }
        if ($value !=null){
            //更新配置 暂时不用备扩展
        }
    }

    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    abstract function exec();

    /**
     * 获取游戏列表的开始特征字段
     * @param string $gtag 游戏三方代码 如果三方平台获取注单模式与游戏无关，则传入的是  all
     * @param int $thistime 当前调用时间节点
     * @return mixed
     * @author twt326
     * @date 2022-09-18 17:23
     */
    abstract function getBetListStartFlag($gtag,$thistime);

    /** 根据读取列表的返回数据更新下次游戏列表的开始特征字段值
     * @param $gtag 游戏三方代码 如果三方平台获取注单模式与游戏无关，则传入的是  all
     * @param $thistime 前调用时间节点
     * @param $data 注单数据列表，可根据它的最后一条记录来更新结束标志时间
     * @return mixed
     * @author twt326
     * @date 2022-09-19 21:26
     */
    abstract function setBetListStartFlag($gtag,$thistime,$data);

    /**
     * 处理一条游戏记录数据为可写入注单表的状态
     * 注单将被统一处理写入正式表
     * 每条记录有 c 创建（默认） 和 u 更新两种状态
     * 实现时请根据注单记录判断并设置状态
     * 对于三方平台获取注单方式如果是与游戏无关的情况下，请在方法体中对 gb_gid进行赋值[通过Game::getGamesInfoBy($this->cfg['trdcfg']['tc_id'],$gcode,'tag','g_id')可以获取]
     * 对于三方平台获取注单方式如果是与游戏有关的情况下，gb_gid由封装自行处理，实现方法时无须理会
     * @param $gcode 游戏在三方平台的代码 如果此三方平台（主动式）获取注单的接口与游戏无关，则这里传入的是 all
     * @param $data 游戏注单数据（单条）
     * @return mixed
     * @author twt326
     * @date 2022-09-20 22:00
     */
    abstract function procBetData($gcode,$data);

    /**用于预处理首次获取的注单列表，如无需特别处理，不需要重写
     * 例见代码
     * 特别注意一定要阅读文档中关于此方法的描述，慎用 n 状态
     * @param mixed $gcfg
     * @param mixed $datas r_list标签标识出的数据，即注单数据
     * @param mixed $odatas 原始接口返回的数据
     * @return array
     * @author twt326
     * @date 2022-10-16 15:51
     */
    public function procBetDataPre($gcfg, $datas,$odatas){
        /**
         * 比如某种情况下首次获得的注单信息并不包含列表，而是另一个链接，或是一个JSON文件的地址等
         * 那么在这里需要将其处理成一个数组列表，后续将用于procBetData进行单条数据处理
         * 这里如果需要进行二次访问三方接口，则可以通过使用本方法 的附加参数（TODO根据实际情况后加）去调用原封装方法处理业务
         * 或自行处理
         */
        /**
        * 特别注意：同一gb_gid gb_sn gb_op相同的记录仅允许存在一条，因此实现时请注意属于更新状态的记录重复问题需要根据三方平台可能的返回值
        * 自行去重，比如一次注单列表中对同一个注单有两条记录：投注额部分取消   注单输赢结算
        * 这种情况下请将两条记录合并为单条，合并时 betinfo字段值请使用 ||| 分隔符组合
         *
         * 关于 gb_op的三种状态请认真理解技术文档中的 ThridTask类说明中的procBetData和procBetDataPre以及TransferBets,以免出现逻辑问题
         */
        return $datas;
    }

    /**
     * 执行完任务后或过程中更新配置值,需要可重写
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:39
     */
    function updateConfigValue(){
        //Utils::L('test',['更新三方配置值字段','cfgvalues'=>$this->cfgvalues,'trdid'=>$this->trdid]);
        if ($this->cfgvalues !=null) {
            ThirdConfigs::where('tc_id',$this->trdid)->update([
                "tc_taskvalue" => json_encode($this->cfgvalues)
            ]);
        }
    }

    function getApiCfgvalues($func,$gtag){
        //缓存读取
//        Redis::hdel('apiscfgvalues_'.$this->trdcode,[$func]);

        if (!isset($this->apiscfgvalues[$func])) {
            $this->apiscfgvalues[$func] = Redis::hget('apiscfgvalues_' . $this->trdcode, $func);
            if ($this->apiscfgvalues[$func]) {
                if (is_string($this->apiscfgvalues[$func])) $this->apiscfgvalues[$func] = json_decode($this->apiscfgvalues[$func], true);
            } else {
                $this->config(null,true);//更新最新缓存
                if (empty($this->cfg['apis'][$func]['ga_taskvalue'])) {
                    $this->apiscfgvalues[$func] = [];
                }else{
                    if (is_string($this->cfg['apis'][$func]['ga_taskvalue'])) {
                        $this->apiscfgvalues[$func] = json_decode($this->cfg['apis'][$func]['ga_taskvalue'], true);
                    }else{
                        $this->apiscfgvalues[$func] = $this->cfg['apis'][$func]['ga_taskvalue'];
                    }
                }
            }
        }
        if (isset($this->apiscfgvalues[$func][$gtag])) {
            return $this->apiscfgvalues[$func][$gtag];
        }else{
            //缓存初始化  TODO 可扩展
            switch ($func) {
                case 'GETBETLIST':
                    $this->apiscfgvalues[$func][$gtag] = [
                        "lastkey" => null,
                        "lastruntime" => 0,//最后一次任务执行时间节点
                        "curtime"=>0,//即时执行时间，用于判断执行频率
                    ];
                    break;
                default:
                    //支持多获取注单列表API
                    if (substr($func,0,10)=="GETBETLIST"){
                        $this->apiscfgvalues[$func][$gtag] = [
                            "lastkey" => null,
                            "lastruntime" => 0,//最后一次任务执行时间节点
                            "curtime"=>0,//即时执行时间，用于判断执行频率
                        ];
                    }
                    break;
            }
            Redis::hset('apiscfgvalues_'.$this->trdcode,$func,json_encode($this->apiscfgvalues[$func]));
            return $this->apiscfgvalues[$func][$gtag];

        }
    }

    /**
     * 更新API配置值
     * @param $func API代码
     * @param null $data 如果为null则直接保存缓存中间结果，传入值则用于保存值，但不影响中间缓存结果
     * @author twt326
     * @date 2022-09-20 21:15
     */
    function updateApiConfigValue($func,$data=null){
        Utils::L('test',['准备保存API配置值',$data]);
        if (isset($this->apiscfgvalues[$func])){
            GameApis::where('ga_tcid',$this->trdid)->where('ga_func',$func)->update([
                'ga_taskvalue'=>json_encode($data==null?$this->apiscfgvalues[$func]:$data)
            ]);
            Redis::hset('apiscfgvalues_'.$this->trdcode,$func,json_encode($data==null?$this->apiscfgvalues[$func]:$data));
        }
    }

    /**
     * 将指定游戏ID的临时注单转移到正式注单表（去重）
     * 如果不是通用的转移，有特殊数据进行处理的话，请在继承类中重写此方法
     * @param $gidortcid 游戏ID（$type=byame） 或 三方平台ID($type=all)
     * @param $type 注单转移模式：  all 三方平台注单获取不区分游戏时使用  bygame 三方平台注单是根据游戏来获取时使用
     * @author twt326
     * @date 2022-09-20 22:47
     */
    protected function transferBets($gidortcid,$type){
        //Utils::L('test',['准备迁移注单到正式注单表'.$type,$gidortcid]);
        $field=($type=='all'?'tcid':'gid');
        $sql="";
        //迁移注单到正式注单表
        Db::startTrans();
        try {
            //写入创建记录 gb_gid gb_sn 重复的忽略
            /* 换成分表了$sql="insert into dq_game_bets (gb_gid,gb_tcid,gb_uid,gb_intime,gb_price,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_backstateks,gb_cusfield,gb_betinfo)
select
tb.gb_gid,tb.gb_tcid,tb.gb_uid,tb.gb_intime,tb.gb_price,tb.gb_sn,tb.gb_winlose,tb.gb_bettime,tb.gb_overtime,tb.gb_winprice,tb.gb_backstateks,tb.gb_cusfield,tb.gb_betinfo
from dq_game_bets_temp tb left join dq_game_bets tc on tc.gb_gid=tb.gb_gid and tc.gb_sn=tb.gb_sn where tb.gb_$field=" . $gidortcid . " and tb.gb_op='c' and tc.gb_id is null";*/
            $sql="insert into dq_game_bets (gb_gid,gb_tcid,gb_uid,gb_intime,gb_price,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_backstateks,gb_cusfield,gb_otherwin,gb_validwater)
select 
tb.gb_gid,tb.gb_tcid,tb.gb_uid,tb.gb_intime,tb.gb_price,tb.gb_sn,tb.gb_winlose,tb.gb_bettime,tb.gb_overtime,tb.gb_winprice,tb.gb_backstateks,tb.gb_cusfield,
if(tb.gb_winlose>1,tb.gb_winprice-tb.gb_price,0) as otherwin,
if(tb.gb_winlose>1,if(tb.gb_winprice>tb.gb_price,tb.gb_price,tb.gb_price-tb.gb_winprice),0) as validwater
from dq_game_bets_temp tb left join dq_game_bets tc on tc.gb_gid=tb.gb_gid and tc.gb_sn=tb.gb_sn where tb.gb_$field=" . $gidortcid . " and tb.gb_op='c' and tc.gb_id is null";
            Db::execute($sql);
            //更新更新记录 主表不存在的记录忽略，已结算的记录忽略（仅更新未结算的）
            $sql="update dq_game_bets_temp tb,dq_game_bets tc 
set 
tc.gb_price=tb.gb_price+tc.gb_price,
tc.gb_winlose=tb.gb_winlose,
tc.gb_overtime=if(tb.gb_overtime is null,tc.gb_overtime,tb.gb_overtime),
tc.gb_winprice=tb.gb_winprice+tc.gb_winprice,
tc.gb_otherwin=if(tb.gb_winlose>1,(tb.gb_winprice+tc.gb_winprice)-(tb.gb_price+tc.gb_price),tc.gb_otherwin),
tc.gb_validwater=if(tb.gb_winlose>1,if(
    tb.gb_winprice+tc.gb_winprice>tb.gb_price+tc.gb_price,
    tb.gb_price+tc.gb_price,
    tb.gb_price+tc.gb_price-(tb.gb_winprice+tc.gb_winprice)
    ),tc.gb_validwater)
where tc.gb_sn=tb.gb_sn and tc.gb_gid=tb.gb_gid and tb.gb_$field=$gidortcid and tb.gb_op='u' and tc.gb_winlose=1";
            Db::execute($sql);
            //更新未知记录 未知记录指有确定是否有前置记录，则先将原记录存在的未结算部分更新，后将不存在的部分写入，注意不要图方便而全部设置为 n ，此逻辑仅是一种对正常 cu操作的补充(因为操作是先更新再插入，所以如果正常的cu用n代替的话，会导原来应为u的记录无法被更新)，能不用则尽量不用
            $sql="update dq_game_bets_temp tb,dq_game_bets tc 
set 
tc.gb_price=if(tb.gb_price is null,tc.gb_price,tb.gb_price),
tc.gb_winlose=tb.gb_winlose,
tc.gb_overtime=if(tb.gb_overtime is null,tc.gb_overtime,tb.gb_overtime),
tc.gb_winprice=if(tb.gb_winprice is null,tc.gb_winprice,tb.gb_winprice),
tc.gb_otherwin=if(tb.gb_winlose>1,if(tb.gb_winprice is null,tc.gb_winprice,tb.gb_winprice)-if(tb.gb_price is null,tc.gb_price,tb.gb_price),tc.gb_otherwin),
tc.gb_validwater=if(tb.gb_winlose>1,if(
    if(tb.gb_winprice is null,tc.gb_winprice,tb.gb_winprice)>if(tb.gb_price is null,tc.gb_price,tb.gb_price),
    if(tb.gb_price is null,tc.gb_price,tb.gb_price),
    if(tb.gb_price is null,tc.gb_price,tb.gb_price)-if(tb.gb_winprice is null,tc.gb_winprice,tb.gb_winprice)
    ),tc.gb_validwater)
where tc.gb_sn=tb.gb_sn and tc.gb_gid=tb.gb_gid and tb.gb_$field=$gidortcid and tb.gb_op='n' and tc.gb_winlose=1";
            Db::execute($sql);
            $sql="insert into dq_game_bets (gb_gid,gb_tcid,gb_uid,gb_intime,gb_price,gb_sn,gb_winlose,gb_bettime,gb_overtime,gb_winprice,gb_backstateks,gb_cusfield,gb_otherwin,gb_validwater)
select 
tb.gb_gid,tb.gb_tcid,tb.gb_uid,tb.gb_intime,tb.gb_price,tb.gb_sn,tb.gb_winlose,tb.gb_bettime,tb.gb_overtime,tb.gb_winprice,tb.gb_backstateks,tb.gb_cusfield,
if(tb.gb_winlose>1,tb.gb_winprice-tb.gb_price,0) as otherwin,
if(tb.gb_winlose>1,if(tb.gb_winprice>tb.gb_price,tb.gb_price,tb.gb_price-tb.gb_winprice),0) as validwater
from dq_game_bets_temp tb left join dq_game_bets tc on tc.gb_gid=tb.gb_gid and tc.gb_sn=tb.gb_sn  where tb.gb_$field=" . $gidortcid . " and tb.gb_op='n' and tc.gb_id is null";
            Db::execute($sql);

            //temp表中存在多条注单表中不存在的同一注单记录（即一次性读到创建和更新注单记录的情况），会导致写入不存在的记录时因为有两条主键一样的数据写入而导致出错，因此这里先处理一下重复记录的写入操作
            //重复记录的先写入
            $sql="insert into dq_game_betsex (gbe_id,gbe_inmonth,gbe_betinfo)
select tc.gb_id,FROM_UNIXTIME(tc.gb_bettime,'%Y%m'),tb.gb_betinfo from 
(select gb_id,gb_gid,gb_sn,gb_betinfo,count(*) as num from dq_game_bets_temp where gb_$field=" . $gidortcid . " group by gb_gid,gb_sn HAVING num>1) tb
left join dq_game_bets tc on tc.gb_gid=tb.gb_gid and tc.gb_sn=tb.gb_sn 
left join dq_game_betsex td on td.gbe_id=tc.gb_id
where tc.gb_id is not null and td.gbe_id is null";
            Db::execute($sql);
            //删除重复已经写入的记录(TODO 这里有可能会删除有用的数据 ，比如重复的记录不需要INSERT的那一部分，但一般情况下不会有问题，除非是重新拉取注单，但那样的话，原始信息其实已经早写入了，会出现的话，有一种情况是记录已经存在的情况下，单次读取又读到了多条该注单的记录，这样就会损失每一条，这种情况也非常之少见)
            $sql="delete from dq_game_bets_temp
where gb_id in
(
select gb_id from 
(select gb_id,gb_gid,gb_sn,gb_betinfo,count(*) as num from dq_game_bets_temp where gb_$field=" . $gidortcid . " group by gb_gid,gb_sn HAVING num>1) tta)";
            Db::execute($sql);
            //更新存在的betinfo记录
            $sql="update
dq_game_bets_temp tb
left join dq_game_bets tc on tc.gb_gid=tb.gb_gid and tc.gb_sn=tb.gb_sn 
left join dq_game_betsex td on td.gbe_id=tc.gb_id
set td.gbe_betinfo=CONCAT(td.gbe_betinfo,'|||',tb.gb_betinfo)
where tb.gb_$field=" . $gidortcid . " and td.gbe_id is not null";
            Db::execute($sql);
            //写不存在的betinfo记录
            $sql="insert into dq_game_betsex (gbe_id,gbe_inmonth,gbe_betinfo)
select tc.gb_id,FROM_UNIXTIME(tc.gb_bettime,'%Y%m'),tb.gb_betinfo from 
dq_game_bets_temp tb
left join dq_game_bets tc on tc.gb_gid=tb.gb_gid and tc.gb_sn=tb.gb_sn 
left join dq_game_betsex td on td.gbe_id=tc.gb_id
where tb.gb_$field=" . $gidortcid . " and  tc.gb_id is not null and td.gbe_id is null";
            Db::execute($sql);


            Db::execute("delete from dq_game_bets_temp where gb_$field=$gidortcid");
            Db::commit();
        }catch (\Exception $ex){
            Db::rollback();
            Utils::L('ThirdErrLogs',[
                'msg'=>'三方平台ThirdTask执行迁移注单出错',
                'suggest'=>'处理错误即可，下次执行时会自动处理game_bets_temp中的注单数据',
                'track'=>[
                    'gidortcid'=>$gidortcid,
                    'type'=>$type,
                    'exception'=>$ex->getMessage(),
                    'sql'=>$sql
            ]]);
        }
    }

    /**
     * 整理指定的临时注单数据，更新uid
     * @param $trdtype 三方平台用户名对应关系 'default','username','reccode','trduserid','trdusername'
     * @param $type 指定gidortcid值类型 tcid gid
     * @param $tcid 三方平台ID
     * @param $gid 游戏ID，type=tcid时不用传值 type=gid时必须传值
     * @author twt326
     * @date 2022-10-29 17:28
     */
    public static function procBetTempData($trdtype,$type,$tcid,$gid=0){
        $sql="";
        switch ($trdtype){
            case 'username':
                Db::execute("update dq_game_bets_temp left join dq_user on username=gb_uname 
set gb_uid=dq_user.id
where gb_uid=0 and gb_uname<>'' and dq_user.id is not null and gb_$type=" . $$type);
                break;
            case 'reccode':
                Db::execute("update dq_game_bets_temp left join dq_user on rec_code=gb_uname 
set gb_uid=dq_user.id
where gb_uid=0 and gb_uname<>'' and dq_user.id is not null and gb_$type=" . $$type);
                break;
            case 'trduserid':
                Db::execute("update dq_game_bets_temp left join dq_user_thirds on ut_trdid=gb_uname and ut_tcid=$tcid
set gb_uid=ut_uid
where gb_uid=0 and gb_uname<>'' and ut_uid is not null and gb_$type=" . $$type);
                break;
            case 'trdusername':
                Db::execute("update dq_game_bets_temp left join dq_user_thirds on ut_trdname=gb_uname and ut_tcid=$tcid
set gb_uid=ut_uid
where gb_uid=0 and gb_uname<>'' and ut_uid is not null and gb_$type=" . $$type);
                break;
            default:
                Db::execute("update dq_game_bets_temp left join dq_user on rec_code=gb_uname 
set gb_uid=dq_user.id
where gb_uid=0 and gb_uname<>'' and dq_user.id is not null and gb_$type=" . $$type);
                break;
        }
        //清除uid为0的数据
        Db::execute("delete from dq_game_bets_temp where gb_uid=0 and gb_$type=" . $$type);
    }

    /** 执行第三方任务类接口
     *
     * @param $cfg
     * @param $lasttime
     * @author twt326
     * @date 2022-08-25 15:10
     */
    public function runThirdTasks($lasttime=0)
    {
        //TODO 目前只有获取用户投注记录的，将来扩展可以拆分出去

        //$trdobjs=[];
        $ts=time();//当前执行任务时间节点
//        Redis::hdel('trdcfgs', ['FB','kyrianstg']);
//        die;
        $apis=$this->cfg['taskapis'];
        $wds=[];
        //获取需要执行任务的API
        for($i=0;$i<count($apis);$i++){
            if (substr($apis[$i]['ga_func'],0,10)=="GETBETLIST") {
                try {
                    $this->nowGetbetlistFunc = $apis[$i]['ga_func'];//记录
                    /*if (!isset($trdobjs[$this->cfg['trdcfg']['tc_code']])) {
                        $class=getc('Third_'.strtolower($this->cfg['trdcfg']['tc_code']));
                        if ($class) {
                            $trdobjs[$this->cfg['trdcfg']['tc_code']]=new $class();
                        }else{
                            continue;//TODO 错误日志
                        }
                    }*/

                    if ($this->cfg['trdcfg']['tc_getbetlisttype'] == 'all') {
                        //如果三方平台是按全局（不区分游戏）方式获取注单的，则需要在注单处理中自行处理gb_gid
                        $intime = time();
                        //处理每一个游戏的记录
                        //处理API配置值
                        $rltKeyFieldCfg = json_decode($apis[$i]['ga_keyfield'], true);
                        $gcfg = $this->cfg;
                        $acfg = $this->getApiCfgvalues($apis[$i]['ga_func'], 'all');
                        //读取接口数据及写入数据
                        //判断频率  TODO 对于有频率限制的API一次只能读取一页数据，后续可以分解此步，实现单独的获取逻辑
                        /*if ($acfg['curtime']+$apis[$i]['ga_rate']>time()){
                            continue;//如果低于三方设定频率，跳过
                        }*/
                        if ($apis[$i]['ga_rate'] > 0) {
                            Utils::L('test', '休眠：' . $apis[$i]['ga_rate']);
                            sleep($apis[$i]['ga_rate']);
                        }
                        $otherkvs = $this->getBetListStartFlag('all', $ts);
                        //Utils::L('taskfb',['otherkvs',$otherkvs]);
                        $rlt = Http::http($gcfg, $apis[$i]['ga_func'], $this->trdClass, $otherkvs);
                        //Utils::L('taskfb',['rlt',$rlt]);
                        //return $rlt;
                        if ($rlt) {
                            $bds = Utils::getDataFromArrayByStrKey($rltKeyFieldCfg['r_list']['key'], $rlt);
                            //Utils::L('taskfb',['游戏列表',$bds]);
                            $wds = [];
                            $bds = $this->procBetDataPre($gcfg, $bds, $rlt);
//                            Utils::L('taskfb',['处理后的游戏列表',$bds]);
                            foreach ($bds as $bdk => $bdv) {
                                //Utils::L('test',['处理',$bdv]);
                                $bd = $this->procBetData('all', $bdv);
                                //Utils::L('test',['处理结果',$bdv]);
                                $bd = array_merge($bd, [
                                    'gb_tcid' => $gcfg['trdcfg']['tc_id'],
                                    'gb_intime' => $intime,
                                    'gb_betinfo' => json_encode($bdv)
                                ]);
                                //Utils::L('test',['合并结果',$bd]);
                                array_push($wds, $bd);
                            }
                            //记录数据
                            (new GameBetsTemp())->saveAll($wds);
                            //写回配置值
                            $this->setBetListStartFlag('all', $ts, $rlt);
                            //处理uid为0的数据，写上UID的值（某些或全部的第三方返回的是用户名，不是用户ID）
                            self::procBetTempData($this->cfg['trdcfg']['tc_useridentify'], 'tcid', $gcfg['trdcfg']['tc_id'], 0);
                            /*Db::execute("update dq_game_bets_temp left join dq_user on rec_code=gb_uname
    set gb_uid=dq_user.id
    where gb_uid=0 and gb_uname<>'' and dq_user.id is not null and gb_tcid=" . $gcfg['trdcfg']['tc_id']);
                            //清除uid为0的数据
                            Db::execute('delete from dq_game_bets_temp where gb_uid=0 and gb_tcid=' . $gcfg['trdcfg']['tc_id']);*/
                            $this->transferBets($gcfg['trdcfg']['tc_id'], 'all');

                        }
                    } else {//如果三方平台是按游戏获取注单的，则单个游戏进行处理，此模式下 gb_gid自动处理
                        //获取所有游戏列表
                        $games = Games::where('g_tcid', $this->trdid)->where('g_state', 1)->select();
                        if ($games) {
                            $games = $games->toArray();
                            for ($j = 0; $j < count($games); $j++) {
                                $intime = time();
                                //处理每一个游戏的记录
                                //处理API配置值
                                $rltKeyFieldCfg = json_decode($apis[$i]['ga_keyfield'], true);
                                $gcfg = Games::getConfigs($games[$j]['g_id']);
                                $acfg = $this->getApiCfgvalues($apis[$i]['ga_func'], $games[$j]['g_tag']);
                                //读取接口数据及写入数据
                                //判断频率  TODO 对于有频率限制的API一次只能读取一页数据，后续可以分解此步，实现单独的获取逻辑
                                /*if ($acfg['curtime']+$apis[$i]['ga_rate']>time()){
                                    continue;//如果低于三方设定频率，跳过
                                }*/
                                if ($apis[$i]['ga_rate'] > 0) {
                                    Utils::L('test', '休眠：' . $apis[$i]['ga_rate']);
                                    sleep($apis[$i]['ga_rate']);
                                }
                                $otherkvs = $this->getBetListStartFlag($games[$j]['g_tag'], $ts);
                                //Utils::L('taskfb',['otherkvs',$otherkvs]);
                                $rlt = Http::http($gcfg, $apis[$i]['ga_func'], $this->trdClass, $otherkvs);
                                //Utils::L('taskfb',['rlt',$rlt]);
                                //return $rlt;
                                if ($rlt) {
                                    $bds = Utils::getDataFromArrayByStrKey($rltKeyFieldCfg['r_list']['key'], $rlt);
                                    //Utils::L('taskfb',['游戏列表',$bds]);
                                    $wds = [];
                                    $bds = $this->procBetDataPre($gcfg, $bds, $rlt);
//                            Utils::L('taskfb',['处理后的游戏列表',$bds]);
                                    foreach ($bds as $bdk => $bdv) {
                                        //Utils::L('test',['处理',$bdv]);
                                        $bd = $this->procBetData($games[$j]['g_code'], $bdv);
                                        //Utils::L('test',['处理结果',$bdv]);
                                        $bd = array_merge($bd, [
                                            'gb_tcid' => $gcfg['trdcfg']['tc_id'],
                                            'gb_gid' => $games[$j]['g_id'],
                                            'gb_intime' => $intime,
                                            'gb_betinfo' => json_encode($bdv)
                                        ]);
                                        //Utils::L('test',['合并结果',$bd]);
                                        array_push($wds, $bd);
                                    }
                                    //记录数据
                                    (new GameBetsTemp())->saveAll($wds);
                                    //写回配置值
                                    $this->setBetListStartFlag($games[$j]['g_tag'], $ts, $rlt);
                                    //处理uid为0的数据，写上UID的值（某些或全部的第三方返回的是用户名，不是用户ID）
                                    self::procBetTempData($this->cfg['trdcfg']['tc_useridentify'], 'gid', $gcfg['trdcfg']['tc_id'], $games[$j]['g_id']);
                                    /*Db::execute("update dq_game_bets_temp left join dq_user on rec_code=gb_uname
    set gb_uid=dq_user.id
    where gb_uid=0 and gb_uname<>'' and dq_user.id is not null and gb_gid=" . $games[$j]['g_id']);
                                    //清除uid为0的数据
                                    Db::execute('delete from dq_game_bets_temp where gb_uid=0 and gb_gid=' . $games[$j]['g_id']);*/
                                    $this->transferBets($games[$j]['g_id'], 'bygame');

                                }


                            }

                        }
                    }
                } catch (\Exception $e) {
                    Utils::L('ThirdTaskErrLogs', [
                        'msg' => '任务获取注单错误',
                        'track' => [
                            'FUNCTION' => $apis[$i]['ga_func'],
                            'tcid' => $this->trdid,
                            'errormsg' => $e->getMessage(),
                            'trace'=>$e->getTraceAsString(),
                            'cfg' => $this->cfg['trdcfg'],
                            'wds'=>$wds
                        ]]);
                    continue;
                }
            }
        }


    }

    /** 执行三方更新数据有统计任务：更新统计中间表
     * @param $cfg
     * @param $lasttime
     * @author twt326
     * @date 2022-08-25 15:11
     */
    public function runStat($lasttime)
    {

    }







}
