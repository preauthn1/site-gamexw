<?php 
namespace app\api\redis;
use think\facade\Request;
use think\facade\Config;
use Firebase\JWT\JWT;
use app\common\redis\CommomRedis;
class ApiRedis extends CommomRedis
{	
    //重写属性
    protected $lock_key="api_lock_0s";//redis锁key-默认通用
    protected $default_prefix="api_0s";//-前缀
    /**
     * 创建redis连接
     * @return Link
     */
    public function __construct($name = 'redis', $lock_key="api_lock"){
    	parent::__construct($name);        
    	$this->lock_key=$lock_key;
    }
}