<?php

namespace app\common\FastGame\Base;


use app\model\GameInfos;
use app\model\Games;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\Exception;

/**
 * 第三方游戏被动接口
 * 非公共方法或不能通过公共方法实现的业务功能继承此类，类名为： Notify{g_code} 全小写
 * Class Notify
 * @package app\common\FastGame\Base
 * @author twt326
 * @date 2022-08-23 15:48
 */

abstract class Notify
{
    /** @var Notify null  */
    public static $extClass=null;
    public static $extClassName=null;

    public $logdir='notify';
    public $logerrdir='notify_error';

    public function __construct()
    {
        $dir=explode('_',self::$extClassName);
        $dir=$dir[count($dir)-1];
        $this->logdir=$dir.'/notify';
        $this->logerrdir=$dir.'/notify_error';
    }

    public function loginfo($msg,$data=null){
        try {
            $action = debug_backtrace()[1]['function'];
        }catch (Exception $e){
            $action=self::$extClassName;
        }
        Utils::L($this->logdir,['method'=>$action,'msg'=>$msg,'data'=>$data]);
    }

    public function logerr($msg,$data=null,$ex=null){
        try {
            $action = debug_backtrace()[1]['function'];
        }catch (Exception $e){
            $action=self::$extClassName;
        }
        Utils::L($this->logerrdir,['method'=>$action,'msg'=>$msg,'data'=>$data,'error'=>$ex?$ex->getMessage():'','trace'=>$ex?$ex->getTraceAsString():'']);
    }

    /**
     * 如果在本静态类初始化前需要使用继承类时调用
     * @param $tccode
     * @return Notify|bool
     * @author twt326
     * @date 2022-09-21 21:27
     */
    public static function initExtClass($tccode){
        $exclass=getc('Notify_'.strtolower($tccode));
        if (self::$extClass==null || $exclass !=self::$extClassName){
            if ($exclass) {
                self::$extClassName=$exclass;
                self::$extClass = new $exclass();
            }else{
                return false;
            }
        }
        return self::$extClass;
    }

    /** 执行三方接口方法
     * TODO 因提取了initExtClass，并根据参数识别是否需要重建第三方继承类的对象，这里将来可以尝试将 apicfgs也进行缓存，以提高代码可阅读性和降低耦合
     * @param $tcid
     * @param $datas 三方传来的数据
     * @param $flag 执行标志，如果为null则执行继承类的通用方法 notify
     * @return string|null
     * @author twt326
     * @date 2022-09-21 21:32
     */
    public static function run($tcid,$datas,$flag=null){
        //Utils::L('test',['Notify:run',$tcid,$flag,$datas]);
        $cfg=Games::getConfigs(null,$tcid);
        //如果是集成三方的则使用集成三方类名初始化
        $tccode=(isset($cfg['trdcfg']['tc_set_code']) && !empty($cfg['trdcfg']['tc_set_code']))?$cfg['trdcfg']['tc_set_code']:$cfg['trdcfg']['tc_code'];
        if (self::initExtClass($tccode)===false){
            Utils::L('ThirdErrLogs',[
                'msg'=>'三方平台Notify继承类不存在',
                'suggest'=>'继承Notify类并实现相应方法',
                'track'=>[
                    'tcid'=>$tcid,
                    'datas'=>$datas,
                    'flag'=>$flag,
                    'cfg'=>$cfg
                ]]);
            return false;
        }
        //$trdobj=Third::initExtClass(['trdcfg'=>['tc_code'=>$tccode]]);
        $trdobj=Third::initExtClass($cfg);
        if (method_exists(self::$extClass, $flag)){
            return self::$extClass->$flag($datas,$trdobj,$cfg);
        }else{
            return self::$extClass->notify($datas,$trdobj,$cfg);
        }
    }

    /** 默认的第三方被动接口执行方法，如无需要直接返回值
     * @param $datas
     * @param $trdobj
     * @param $cfg
     * @return mixed
     * @author twt326
     * @date 2022-10-04 18:33
     */
    abstract function notify($datas,$trdobj,$cfg);

}

