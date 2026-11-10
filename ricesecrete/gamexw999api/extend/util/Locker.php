<?php

namespace util;

use think\facade\Cache;
use think\helper\Str;

class Locker {

    protected static $instance;
    private $redis;
    private $key;
    private $relese;

    public function __construct($key = null) {
        $this->redis = Cache::store('redis')->handler();
        $this->key = $key ?? md5(Str::random(15));
        $this->relese = false;
    }
    
    public function lock($timeout = 5) {
        $redis = $this->redis;
        $key = $this->key;
        if ($redis->setnx($key, 1) == true) {
            $redis->setex($key, $timeout, 1);
            $this->relese = true;
            return true;
        } else {
            if ($redis->ttl($key) == -1) {
                $redis->expire($key, $timeout);
            }
            return false;
        }
    }

    public function unLock() {
        $this->redis->del($this->key);
    }
    

    public function __destruct() {
        if ($this->relese) {
            $this->unLock();
        }
    }

}

