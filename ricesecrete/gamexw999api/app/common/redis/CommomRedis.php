<?php

namespace app\common\redis;

use app\common\lib\Util;
use app\common\service\AgentService;
use app\model\SysSets;
use think\facade\Cache;

class CommomRedis { //通用缓存

    protected $redis = null; //redis对象
    protected $lock_key = "commom_lock_0s"; //redis锁key-默认通用
    protected $default_prefix = "commom_0s"; //-前缀
    protected $redis_read;
    protected $tag = null;
    /**
     * 创建redis连接
     * @return Link
     */
    public function __construct($name = 'redis', $lock_key = "commom_0s") {
        $this->redis_read = env('redis_read', 0);
        if($this->redis_read == 2){
            $this->redis = Cache::store("cluster");
        }else{
            $this->redis = Cache::store($name);
        }

        $this->lock_key = $lock_key;
    }

    /**
     * 设置缓存tag
     */
    public function tag($tag) {
        if(!empty($tag)){
            $this->tag = $tag;
        }
        return $this;
    }

    /**
     * @param $key 键
     * @param $value 值
     * @param $time 存储时间
     * 设置缓存
     */
    public function set($key, $value, $time = 3600) {
        if(is_object($value)){
            $value = object_array($value);
        }
        $redis = $this->redis;
        if ($this->tag) {
            $redis = $redis->tag($this->tag);
        }
        $redis->set($this->default_prefix . $key, $value, $time);
    }

    /**
     * @param $key 键
     * 获取缓存
     */
    public function get($key) {
        if(!$this->has($key)){
            return [];
        }else{
            if($this->redis_read == 1){
                $value = Cache::store('redis_read')->get($this->default_prefix . $key);
            }else{
                $value = $this->redis->get($this->default_prefix . $key);
            }
            return $value;
        }
    }

    /**
     * @param $key 键
     * 判断是否存在
     */
    public function has($key) {
        return $this->redis->has($this->default_prefix . $key);
    }

    /**
     * @param $key 键
     * 删除缓存
     */
    public function delete($key) {
        return $this->redis->delete($this->default_prefix . $key);
    }

    /**
     * 清除tag缓存
     * @param $tag
     * @return bool
     */
    public function clearTag($tag) {
        return $this->redis->tag($tag)->clear();
    }

    /**
     * 系统信息
     */
    public function systemInfo($code = null) {
        //获取代理站点配置信息
        $agentWebSiteConfig = AgentService::getAgentWebSiteConfig();
        if (is_null($code)) {
            $sys = SysSets::getSets();
            foreach ($agentWebSiteConfig as $k => $v){
                if( isset($sys[$k]) && $v !== ''){
                    $sys[$k] = $v;
                }
            }
            return $sys;
        } else {
            if( $agentWebSiteConfig && isset($agentWebSiteConfig[$code]) && $agentWebSiteConfig[$code] ){
                return $agentWebSiteConfig[$code];
            }else{
                return SysSets::getSetByCode($code);
            }
        }
    }

    /**
     * 获取锁
     * @param  $uid 是否用户id
     * @param  $expire 锁过期时间
     * @return Boolean
     */
    public function lock($uid = "", $expire = 10) {
        $key = "lock" . $this->lock_key . $uid;
        $redis = $this->redis->handler();
        if ($redis->setnx($key, 1) == true) {
            $redis->setex($key, $expire, 1);
            return true;
        } else {
            if ($redis->ttl($key) == -1) {
                $redis->expire($key, $expire);
            }
            return false;
        }
    }

    /**
     * 释放锁
     * @param  $uid 是否用户id
     * @return Boolean
     */
    public function unlock($uid = "") {
        return $this->redis->delete("lock" . $this->lock_key . $uid);
    }

    /**
     * 模糊查找KEY
     * @param  $uid 是否用户id
     * @return Boolean
     */
    public function keys($key = "") {
        if($this->redis_read == 1){
            return Cache::store('redis_read')->handler()->keys($this->default_prefix . $key."*");
        }else{
            return $this->redis->handler()->keys($this->default_prefix . $key."*");
        }
    }

    public function setOnline($uid){
        $this->redis->handler()->zadd('online',time(),$uid);
    }

    public function getOnline(){
        $expire = 300;
        $data = $this->redis->handler()->zcount('online',time()-$expire,time());//统计时间段内的人数
        $data = $data?$data:0;
        return $data;
    }


    public function getCurrency($isDeleteCache = false){
        if( $isDeleteCache ){
            $this->delete("currency");
        }
        if (!$this->has("currency")) {
            $this->set("currency", Util::getCurrency());
        }
        return $this->get("currency");
    }
    
    /**
     * @return array|mixed
     */
    public function getLangsData(){
        if (!$this->has("system_lang")) {
            $this->set("system_lang", Util::getLangs());
        }
        return $this->get("system_lang");
    }

    /**（前端）
     * @return array|mixed
     */
    public function getLangsBebData(){
        if (!$this->has("system_web_lang")) {
            $this->set("system_web_lang", Util::getLangsWeb());
        }
        return $this->get("system_web_lang");
    }

    /**
     * @return array|mixed
     */
    public function getNation(){
        if (!$this->has("system_nation")) {
            $this->set("system_nation", Util::getNation());
        }
        return $this->get("system_nation");
    }

    public function getBankList(){
        $key = "bank_list_v2";
        if (!$this->has($key)) {
            $this->set($key, Util::getBankList(), 60);
        }
        return $this->get($key);
    }


    public function getBankNames(){
        $banks = $this->getBankList();
        if (empty($banks)) {
            return [];
        }
        $names = [];
        foreach ($banks[0] AS $bank) {
            $names[] = $bank['bank_name'];
        }
        return array_unique($names);
    }

    /**
     * 返回redis对象
     * @return mixed|\think\cache\Driver|null
     */
    public function handle() {
        return $this->redis;
    }

    /**
     * 获取key
     * @param $key
     * @return string
     */
    public function getKey($key) {
        return $this->default_prefix . $key;
    }
}
