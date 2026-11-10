<?php

namespace app\common\service;

use app\common\model\UserOnlineModel;
use app\common\redis\CommomRedis;

class UserOnlineService{

    //获取在线用户
    public static function getOnlineUserNum($where = []){
        $CommomRedis=new CommomRedis();
        $redis_key = 'get_online_user_num'.json_encode($where);
        if (!$redis_key || !$CommomRedis->has($redis_key)) {
            $num = UserOnlineModel::where($where)->count();
            $CommomRedis->set($redis_key, $num, 10);
        }else{
            $num = $CommomRedis->get($redis_key);
        }
        return $num;
    }

}