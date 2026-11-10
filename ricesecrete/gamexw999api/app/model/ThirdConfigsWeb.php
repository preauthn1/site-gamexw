<?php


namespace app\model;


use app\common\FastGame\Base\Redis;
use think\Model;

class ThirdConfigsWeb extends Model
{

    protected $name = 'third_configs_web';
    protected $pk = 'tc_id';


    //前端三方平台配置缓存清除
    public static function clearPlatformConfigCache($thirdCode)
    {
        Redis::hdel('trdcfgsweb', [$thirdCode]);
    }
}
