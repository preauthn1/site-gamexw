<?php
 
declare(strict_types=1);
 
namespace app\common\cache;
 
use think\cache\driver\Redis;
 
class RedisClusterDriver extends Redis
{
    protected $handler;
 
    /**
     * 重写 TP 原 Redis 驱动的构造方法
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        try {
            $options       = [
                'cluster'    => 'redis',
                'parameters' => [
                    'password' => $this->options['password'],
                ]
            ];
            $this->handler = new \Predis\Client($this->options['host'], $options);
        } catch (\Exception $e) {
            throw new \BadFunctionCallException($e->getMessage());
        }
    }
}