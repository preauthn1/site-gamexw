<?php

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动
    'default' => env('cache.driver', 'file'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => '',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],
        // redis缓存
        'redis'   =>  [
            // 驱动方式
            'type'   => 'redis',
            // 服务器地址
            'host'   => env('redis.host', ''),
            //  端口号
            'port'      => env('redis.port', ''),
            //  密码
            'password'=> env('redis.password', ''),
            //是否长连接
            'persistent' => true,
            //  默认缓存时间
            'timeout' => 3600
        ],
        'redis_read' => [
            // 驱动方式
            'type'   => 'redis',
            // 服务器地址
            'host'   => env('redis2.host', '127.0.0.1'),
            //  端口号
            'port'      => env('redis2.port', '6379'),
            //  密码
            'password'=> env('redis2.password', ''),
            //是否长连接
            'persistent' => true,
            //  默认缓存时间
            'timeout' => 3600
        ],
        // redis 集群
        'cluster' => [
            'type'   => \app\common\cache\RedisClusterDriver::class,
            'cluster' => 'redis',
            'host' => explode(",", env('redis3.host', ''))
        ],
        'redis_queue'   =>  [
            // 驱动方式
            'type'   => 'redis',
            // 服务器地址
            'host'   => env('redis.host', '127.0.0.1'),
            //  端口号
            'port'      => env('redis.port', '6379'),
            //  密码
            'password'=> env('redis.password', ''),
            //  选择库
            'select' => 8,
            //  默认缓存时间
            'timeout' => 3600
        ],
        'redis_order'   =>  [
            // 驱动方式
            'type'   => 'redis',
            // 服务器地址
            'host'   => env('redis.host', ''),
            //  端口号
            'port'      => env('redis.port', ''),
            //  密码
            'password'=> env('redis.password', ''),
            //是否长连接
            'persistent' => true,
            //  选择库
            'select' => 0,
            //  默认缓存时间
            'timeout' => 3600
        ],
    ],
];
