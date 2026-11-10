<?php


namespace app\common\FastGame\Base;


use think\facade\Cache;
use app\common\Utils;

class Redis
{
    /** @var \think\cache\driver\Redis null  */
    private static $r = null;
    /** @var string KEY前缀，默认为无 */
    public static $keyPre='';
    /** @var int 操作库编号 */
    public static $dbIdx=0;

    private static function init($dbidx = null,$keypre=null)
    {
        if (self::$r == null) {
            self::$r = Cache::store('redis')->handler();
        }
        if ($keypre !== null){
            self::$keyPre=$keypre;
        }
        if ($dbidx !== null){
            self::$r->select($dbidx);
        }
    }

    /**
     * redis支持多商户，按商户前缀来定义key
     * @param $key 支持数组
     * @param bool $add true添加前缀，false 去掉前缀
     * @return string|array
     * @author twt326
     * @date 2022-05-20 15:48
     */
    public static function transKey($key,$add=true){
        return $key;//不需要转换
    }

    /**
     * 用于全局的获取KEY，不带前缀的
     * @param $key
     * @param int $dbidx
     * @return mixed
     * @author twt326
     * @date 2022-05-28 17:24
     */
    public static function Gget($key,$dbidx=0){
        self::init($dbidx);
        return self::$r->get($key);
    }

    public static function get($key, $dbidx = 0)
    {
        self::init($dbidx);
        return self::$r->get(self::transKey($key));
    }

    /**
     * 用于全局的设置KEY，不带前缀的
     * @param $key
     * @param $v
     * @param int $dbidx
     * @return bool
     * @author twt326
     * @date 2022-05-28 17:25
     */
    public static function Gset($key, $v, $dbidx = 0)
    {
        self::init($dbidx);
        if (self::$r->set($key, $v)) {
            return $v;
        } else {
            return false;
        }
    }

    public static function set($key, $v, $dbidx = 0)
    {
        self::init($dbidx);
        if (self::$r->set(self::transKey($key), $v)) {
            return $v;
        } else {
            return false;
        }
    }

    /**
     * 设置带过期时间的key
     * @param type $key
     * @param type $v
     * @param type $seconds 有效期
     * @param type $dbidx
     * @return boolean
     */
    public static function setex($key, $v, $seconds = 60, $dbidx = 0)
    {
        self::init($dbidx);
        if (self::$r->setex(self::transKey($key), $seconds, $v)) {
            return $v;
        } else {
            return false;
        }
    }

    /**
     * 判断KEY是否存在
     * @param type $key
     * @param type $dbidx
     * @return type
     */
    public static function has($key, $dbidx = 0)
    {
        self::init($dbidx);
        return self::$r->exists(self::transKey($key));
    }

    public static function inc($key, $v = 1, $dbidx = 0)
    {
        self::init($dbidx);
        if (self::$r->incrBy(self::transKey($key), $v)) {
            return $v;
        } else {
            return false;
        }
    }

    public static function incf($key, $v = 1.0, $dbidx = 0)
    {
        self::init($dbidx);
        if (self::$r->incrByFloat(self::transKey($key), $v)) {
            return $v;
        } else {
            return false;
        }
    }

    public static function keys($keypre, $dbidx = 0)
    {
        self::init($dbidx);
        $arr = self::$r->keys(self::transKey($keypre) . "*");
        return $arr;
    }


    /** 删除REDIS 键，特别注意，如果pflag=true 时，请自带KEY的前缀（数据库名+_）
     * @param $keys 空格分出的字符串或数组，可以带正则
     * @param bool $pflag 是否按正则解析，默认false
     */
    public static function del($keys, $pflag = false, $dbidx = 0)
    {
        self::init($dbidx);
        if (!is_array($keys)) $keys = explode(" ", $keys);
        if ($pflag) {
            for ($i = 0; $i < count($keys); $i++) {
                $x = self::$r->keys($keys[$i]);
                if ($x && count($x) > 0) self::$r->del($x);
            }
        } else {
            self::$r->del(self::transKey($keys));
        }
    }

    public static function Gdel($keys, $pflag = false, $dbidx = 0)
    {
        self::init($dbidx);
        if (!is_array($keys)) $keys = explode(" ", $keys);
        if ($pflag) {
            for ($i = 0; $i < count($keys); $i++) {
                $x = self::$r->keys($keys[$i]);
                if ($x && count($x) > 0) self::$r->del($x);
            }
        } else {
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
    public static function hget($field, $key, $dbidx = 0)
    {
        self::init($dbidx);
        $arr = self::$r->hget(self::transKey($field), $key);
        //$arr = json_decode($arr, true);
        if (is_array($arr) || is_object($arr)){
            return count((array)$arr)>0?$arr:null;
        }else {
            return $arr ? $arr : null;
        }
    }

    /**
     * 将哈希表 key 中的域 field 的值设为 value 。
     * @param $field
     * @param $key
     * @param $data
     * @param int $dbidx
     * @return mixed 1 成功 0 覆盖
     */
    public static function hset($field,$key,  $data, $dbidx = 0)
    {
        self::init($dbidx);
        $x = self::$r->hset(self::transKey($field),$key,  $data);
        return $x;
    }

    public static function hinc($field, $key, $v = 1, $dbidx = 0)
    {
        self::init($dbidx);
        if (self::$r->hIncrBy(self::transKey($field),$key,  $v)) {
            return $v;
        } else {
            return false;
        }
    }

    /**
     * 迭代获取$num条数据直至获取完
     * @param $field
     * @param $it
     * @param string $pattern
     * @param $num
     * @param int $dbidx
     * @return array
     */
    public static function hscan($field, $it = null, $pattern = '*', $num = 0, $dbidx = 0)
    {
        self::init($dbidx);
        $arr = self::$r->hScan(self::transKey($field), $it, $pattern, $num);
        if (!$arr) {
            $arr = [];
        } else {
            foreach ($arr as $k => $val) {
                $arr[$k] = json_decode($val, true);
            }
        }
        return $arr;
    }

    /**
     * 删除$delkey条数据
     * @param $field
     * @param $delkey
     * @param int $dbidx
     */
    public static function hdel($field, $delkey, $dbidx = 0)
    {
        $arr=0;
        try {
            self::init($dbidx);
            self::$r->multi();
            foreach ($delkey as $k => $v) {
                self::$r->hdel(self::transKey($field), $v);
            }
            $arr = self::$r->exec();//没有数据时会自动删除key
            /*$arr = self::$r->hDel($yday,$delkey);*/
        } catch (Exception $ex) {
            //Utils::L('test',$ex->getMessage());
        }
        return $arr;
    }

    /**
     * 返回哈希表 key 中域的数量
     * @param $field
     * @param int $dbidx
     * @return mixed
     */
    public static function hlen($field, $dbidx = 0)
    {
        self::init($dbidx);
        $num = self::$r->hlen(self::transKey($field));
        return $num;
    }


    /** 列表头部弹出
     * @param $field
     * @param $dbidx
     * @return mixed
     * @author twt326
     * @date 2022-07-08 16:05
     */
    public static function list_lpop($field,$dbidx=0){
        self::init($dbidx);
        $v = self::$r->lpop(self::transKey($field));
        return $v;
    }

    /** 列表尾部插入
     * @param $field
     * @param $v
     * @param $dbidx
     * @return mixed
     * @author twt326
     * @date 2022-07-08 16:06
     */
    public static function list_rpush($field,$v,$dbidx=0){
        self::init($dbidx);
        $v = self::$r->rpush(self::transKey($field),$v);
        return $v;
    }


    /** 获取HASH表中的所有键值
     * @param $field
     * @param int $dbidx
     * @return array
     * @author twt326
     * @date 2022-07-08 16:02
     */
    public static function hgetall($field,$dbidx=0){
        $arr=[];
        try{
            self::init($dbidx);
            $arr=self::$r->hgetall(self::transKey($field));
            /*return $tmp;
            $len=count($tmp);
            for($i=1;$i<$len;$i+=2){
                $arr[$tmp[$i]]=$tmp[$i+1];
            }*/
        }catch (Exception $ex){
            //return $ex;
        }
        return $arr;
    }

    /** 时限缓存
     * @param $field 缓存字段名
     * @param $key 缓存key名
     * @param null $v 缓存值，默认null表示读取值
     * @param bool $refresh 读取数据时是否刷新有效期
     * @param int $time 缓存效时间，超出则表示过期
     * @param int $dbidx 数据库，默认6
     * @return null｜mixed
     */
    public static function limitCache($field,$key,$v=null,$refresh=false,$time=60,$dbidx=6){
        $rv=null;
        if ($time==0) return $rv;//传入值为0时表示不使用缓存，是用于方便快速在应用场景中关闭使用redis缓存
        try{
            //self::init($dbidx);
            if ($v===null){
                $v=self::hget($field,$key,$dbidx);
                if ($v){
                    $v=json_decode($v,true);
                    if ($v['st']+$time>=time()) {
                        if ($refresh){
                            $v['st']=time();
                            self::hset($field,$key,json_encode($v));
                        }
                        return $v['v'];
                    }
                }
            }else{
                $rv=$v;
                self::hset($field,$key,json_encode([
                    'v'=>$v,
                    'st'=>time()
                ]),$dbidx);
            }
        }catch (Exception $ex){

        }
        return $rv;
    }

    /**
     * 返回句柄对象，可执行其它高级方法
     * @param type $dbidx
     * @return type
     */
    public static function handle($dbidx = 0)
    {
        self::init($dbidx);
        return self::$r;
    }

    public static function close(){
        try{
        if (self::$r) {
            self::$r->close();
            self::$r = null;
        }
        }catch (Exceptipn $ex){
            Utils::L('rediserr', $ex->getMessage());
        }
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

    //list_rpush
    public static function rpush($key,$v,$dbidx=0){
        self::init($dbidx);
        if (self::$r->rPush($key,$v)){
            return $v;
        }else{
            return false;
        }
    }

    //list_lpop
    public static function lpop($key,$dbidx=0){
        self::init($dbidx);
        return self::$r->lPop($key);
    }
}
