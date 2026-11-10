<?php


namespace app\common\redis;

use think\Exception;

/**
 * Class redis
 * @package pp\common\redis
 * redis数据库索引功能表
 * 0 默认 1 -
 */
class redis
{
    private static $r = null;

    private static function init($dbidx = 0)
    {
        if (self::$r == null) {
            self::$r = new \Redis();
            self::$r->connect(env('REDIS_HOST'), env('REDIS_PORT'));
            try {
                self::$r->auth(env('REDIS_PASSWORD'));
            }catch (\Exception $ex){
                //用于不需要密码
            }
            self::$r->select($dbidx);
        } else {
            self::$r->select($dbidx);
        }
    }

    public static function get($key, $dbidx = 0)
    {
        self::init($dbidx);
        return self::$r->get($key);
    }

    public static function set($key, $v, $dbidx = 0)
    {
        self::init($dbidx);
        if (self::$r->set($key, $v)) {
            return $v;
        } else {
            return false;
        }
    }

    public static function inc($key, $v = 1, $dbidx = 0)
    {
        self::init($dbidx);
        if (self::$r->incrBy($key, $v)) {
            return $v;
        } else {
            return false;
        }
    }

    public static function incf($key, $v = 1.0, $dbidx = 0)
    {
        self::init($dbidx);
        if (self::$r->incrByFloat($key, $v)) {
            return $v;
        } else {
            return false;
        }
    }

    public static function keys($keypre, $dbidx = 0)
    {
        self::init($dbidx);
        $arr = self::$r->keys($keypre . "*");
        return $arr;
    }

    /*public static function gets($keypre = "push_", $dbidx = 0)
    {
        self::init($dbidx);
        $arr = self::$r->keys($keypre . "*");
        $ra = [];
        for ($i = 0; $i < count($arr); $i++) {
            try {
                $t = json_decode(self::$r->get($arr[$i]), true);
                $t["rediskey"] = $arr[$i];
                array_push($ra, $t);
            } catch (Exception $ex) {

            }
        }
        return $ra;
    }*/

    /**
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
        $arr = self::$r->hget($field, $key);
        //$arr = json_decode($arr, true);
        return $arr;
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
        $x = self::$r->hset($field,$key,  $data);
        return $x;
    }

    public static function hinc($field, $key, $v = 1, $dbidx = 0)
    {
        self::init($dbidx);
        if (self::$r->hIncrBy($field,$key,  $v)) {
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
    public static function hscan($field, $it = null, $pattern = '*', $num, $dbidx = 0)
    {
        self::init($dbidx);
        $arr = self::$r->hScan($field, $it, $pattern, $num);
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
        try {
            self::init($dbidx);
            self::$r->multi();
            foreach ($delkey as $k => $v) {
                self::$r->hDel($field, $v);
            }
            $arr = self::$r->exec();//没有数据时会自动删除key
            /*$arr = self::$r->hDel($yday,$delkey);*/
        } catch (Exception $ex) {

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
        $num = self::$r->hlen($field);
        return $num;
    }

    /** 时限缓存
     * @param $field 缓存字段名
     * @param $key 缓存key名
     * @param null $v 缓存值，默认null表示读取值
     * @param int $time 缓存效时间，超出则表示过期 $v有值，且传入负数则表示清除缓存
     * @param int $dbidx 数据库，默认6
     * @return null｜mixed
     */
    public static function limitCache($field,$key,$v=null,$time=60,$dbidx=6){
        $rv=null;
        if ($time==0) return $rv;//传入值为0时表示不使用缓存，是用于方便快速在应用场景中关闭使用redis缓存
        try{
            self::init($dbidx);
            if ($v===null){
                $v=self::hget($field,$key);
                if ($v){
                    $v=json_decode($v,true);
                    if ($v['st']+$time>=time()) return $v['v'];
                }
            }else{
                $rv=v;
                self::hset($field,$key,json_encode([
                    'v'=>$v,
                    'st'=>$time>=0?time():0
                ]));
            }
        }catch (Exception $ex){

        }
        return $rv;
    }
}
