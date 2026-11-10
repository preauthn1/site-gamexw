<?php

namespace app\common\FastGame\Socket;
use app\common\FastGame\Base\Utils;

/**
 * Class R
 * @package socketbase
 * redis数据库索引功能表
 * 0 默认 1 缓存类 9 频率限制类
 */
class R
{
    /**
     * @var \Redis null
     */
    private static $r=null;

    private static function init($dbidx=0){
        if (self::$r==null) {
            self::$r = new \Redis();
            self::$r->connect(Utils::ini('redis.host'));
            if (Utils::ini('redis.password')) {
                self::$r->auth(Utils::ini('redis.password'));
            }
            self::$r->select($dbidx);
        }else{
            self::$r->select($dbidx);
        }
    }

    public static function get($key,$dbidx=0){
        self::init($dbidx);
        return self::$r->get($key);
    }

    public static function set($key,$v,$dbidx=0){
        self::init($dbidx);
        if (self::$r->set($key,$v)){
            return $v;
        }else{
            return false;
        }
    }

    public static function setex($key,$v,$sec=60,$dbidx=0){
        self::init($dbidx);
        if (self::$r->setex($key,$sec,$v)){
            return $v;
        }else{
            return false;
        }
    }

    //list_rpush
    public static function rpush($key,$v,$dbidx=0){
        self::init($dbidx);
        if (self::$r->rPush($key,$v)){
            return $v;
        }else{
            return false;
        }
    }

    //list_rpop
    public static function rpop($key,$dbidx=0){
        self::init($dbidx);
        return self::$r->rPop($key);
    }

    //list_lpop
    public static function lpop($key,$dbidx=0){
        self::init($dbidx);
        return self::$r->lPop($key);
    }

    //list_lpush
    public static function lpush($key,$v,$dbidx=0){
        self::init($dbidx);
        if (self::$r->lPush($key,$v)){
            return $v;
        }else{
            return false;
        }
    }

    /** 返回列表类型长度
     * @param $key
     * @param $dbidx
     * @return bool|int|\Redis
     * @throws \RedisException
     */
    public static function llen($key,$dbidx=0){
        self::init($dbidx);
        return self::$r->lLen($key);
    }

    /** 修减列表，保留start到end间的元素
     * @param $key
     * @param $start
     * @param $end
     * @param $dbidx
     * @return void
     * @throws \RedisException
     */
    public static function ltrim($key,$start,$end,$dbidx=0){
        self::init($dbidx);
        self::$r->lTrim($key,$start,$end);
    }

    public static function inc($key,$v=1,$dbidx=0){
        self::init($dbidx);
        if (self::$r->incrBy($key,$v)){
            return $v;
        }else{
            return false;
        }
    }

    public static function incf($key,$v=1.0,$dbidx=0){
        self::init($dbidx);
        if (self::$r->incrByFloat($key,$v)){
            return $v;
        }else{
            return false;
        }
    }

    public static function keys($keypre,$dbidx=0){
        self::init($dbidx);
        $arr=self::$r->keys($keypre."*");
        return $arr;
    }

    public static function gets($keypre="pushnoice_",$dbidx=0){
        self::init($dbidx);
        $arr=self::$r->keys($keypre."*");
        $ra=[];
        for($i=0;$i<count($arr);$i++){
            try {
                $t = json_decode(self::$r->get($arr[$i]), true);
                $t["rediskey"] = $arr[$i];
                array_push($ra, $t);
            }catch (Exception $ex){

            }
        }
        return $ra;
    }

    /**
     * @param $keys 空格分出的字符串或数组，可以带正则
     * @param bool $pflag 是否按正则解析，默认false
     */
    public static function del($keys,$pflag=false,$dbidx=0){
        self::init($dbidx);
        if (!is_array($keys)) $keys=explode(" ",$keys);
        if ($pflag) {
            for ($i = 0; $i < count($keys); $i++) {
                $x=self::$r->keys($keys[$i]);
                if ($x && count($x)>0) self::$r->del($x);
            }
        }else{
            self::$r->del($keys);
        }
    }

    /**
     * hget 返回哈希表 $field 中给定域 $key 的值。
     * @param $field //hash字段名
     * @param $key //hash KEY
     * @param int $dbidx 库
     * @return array
     */
    public static function hget($field,$key,$dbidx=0)
    {
        self::init($dbidx);
        $arr = self::$r->hget($field,$key);
        //$arr = json_decode($arr, true);
        return $arr;
    }

    /**
     * 将哈希表 key 中的域 field 的值设为 value 。
     * @param $tday
     * @param $uid
     * @param $data
     * @param int $dbidx
     * @return mixed 1 成功 0 覆盖
     */
    public static function hset($tday,$uid,$data,$dbidx=0)
    {
        self::init($dbidx);
        $x = self::$r->hset($tday,$uid,$data);
        return $x;
    }

    public static function hinc($tday,$uid,$v=1,$dbidx=0){
        self::init($dbidx);
        if (self::$r->hIncrBy($tday,$uid,$v)){
            return $v;
        }else{
            return false;
        }
    }

    /**
     * 迭代获取$num条数据直至获取完
     * @param $yday
     * @param $it
     * @param string $pattern
     * @param $num
     * @param int $dbidx
     * @return array
     */
    public static function hscan($yday,$it=null,$pattern='*',$num,$dbidx=0)
    {
        self::init($dbidx);
        $arr = self::$r->hScan($yday,$it,$pattern,$num);
        if (!$arr){
            $arr = [];
        }else{
            foreach ($arr as $k=>$val) {
                $arr[$k] = json_decode($val,true);
            }
        }
        return $arr;
    }

    /**
     * 删除$delkey条数据
     * @param $yday
     * @param $delkey
     * @param int $dbidx
     */
    public static function hdel($yday,$delkey,$dbidx=0)
    {
        try {
            self::init($dbidx);
            self::$r->multi();
            foreach ($delkey as $k=>$v) {
                self::$r->hDel($yday,$v);
            }
            $arr = self::$r->exec();//没有数据时会自动删除key
            /*$arr = self::$r->hDel($yday,$delkey);*/
        }catch (Exception $ex){
            comFuncs::L('redis_hdel',$ex->getMessage());
        }
        return $arr;
    }

    /**
     * 返回哈希表 key 中域的数量
     * @param $key
     * @param int $dbidx
     * @return mixed
     */
    public static function hlen($key,$dbidx=0)
    {
        self::init($dbidx);
        $num = self::$r->hlen($key);
        return $num;
    }
    
    


}
