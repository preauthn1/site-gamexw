<?php


namespace app\model;


use think\Model;

class Games extends Model {

    protected $name = 'games';
    protected $pk = 'g_id';

    private static $configs=[];


    /** 获取游戏配置
     * @param $gid 游戏ID
     * @param $tcid 三方平台ID
     * @param $refresh 是否刷新缓存
     * @return Games|array|mixed|Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author twt326
     * @date 2022-08-23 21:33
     */
    public static function getConfigs($gid=null,$tcid=null,$refresh=false){
        $cfg=null;
        //避免一次业务中重复读取配置 TODO 如遇瓶颈，可以尝试将配置缓存入REDIS，但要慎重，因为数据量较大，或是可以做PHP文件缓存
        if ($gid!=null && isset(self::$configs['g_'.$gid]) && !$refresh){
            $cfg=self::$configs['g_'.$gid];
        }
        if ($tcid!=null && isset(self::$configs['t_'.$tcid]) && !$refresh){
            $cfg=self::$configs['t_'.$tcid];
        }
        if ($cfg !=null){
            return $cfg;
        }
        if ($gid!=null){
            $cfg=self::find($gid)->toArray();
            $tcid=$cfg['g_tcid'];
            $cfg['cfg']=GameConfigs::find($gid)->toArray();
        }else{
            $cfg=['g_id'=>0];
        }
        $cfg['trdcfg']=ThirdConfigs::find($tcid)->toArray();
        if ($gid==null){
            $apis = GameApis::whereRaw('ga_tcid=' . $tcid )->select()->toArray();
        }else {
            $apis = GameApis::whereRaw('ga_tcid=' . $cfg['g_tcid'] . ' and (ga_gid=0 or ga_gid=' . $gid . ')')->select()->toArray();
        }
        $cfg['apis']=array_column($apis,null,'ga_func');
        $cfg['spcapis']=[];
        $cfg['taskapis']=[];
        foreach ($apis as $v){
            if (in_array($v['ga_type'],['reg','first','getauthtoken','getusertoken'])){
                $cfg['spcapis'][$v['ga_type']]=$v;
            }
            if ($v['ga_type']=='task'){
                array_push($cfg['taskapis'],$v);
            }
        }
        if ($cfg['g_id']==0){
            self::$configs['t_'.$cfg['trdcfg']['tc_id']]=$cfg;
        }else{
            self::$configs['g_'.$cfg['g_id']]=$cfg;
        }
        return $cfg;
    }

}
