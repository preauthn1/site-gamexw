<?php


namespace app\model;


use app\common\FastGame\Base\Redis;
use think\Model;

class ThirdConfigs extends Model {

    protected $name = 'third_configs';
    protected $pk = 'tc_id';


    //三方平台配置缓存清除
    public static function clearPlatformConfigCache($thirdCode){
        Redis::hdel('trdcfgs',[$thirdCode]);
    }
}
