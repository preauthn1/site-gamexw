<?php

namespace app\common\service;

use app\api\redis\ApiRedis;
use app\common\model\ServiceUrlModel;

class ServiceUrlService{

    const TELEGRAM_CACHE_KEY = 'telegram_service_url';

    const WHATSAPP_CACHE_KEY = 'whatsapp_service_url';

    //获取telegram链接
    public static function getTelegramUrl($type){
        $apiRedis=new ApiRedis();
        if( $type == ServiceUrlModel::TELEGRAM_TYPE ){
            $key = self::TELEGRAM_CACHE_KEY;
        }else{
            $key = self::WHATSAPP_CACHE_KEY;
        }
        //从缓存获取
        if( $apiRedis->has($key) ){
            return json_decode($apiRedis->get($key), true);
        }
        //从数据库获取
        $condition = [];
        $condition[] = ['type', '=', $type];
        $condition[] = ['mode', '=', ServiceUrlModel::PC_MODE];
        $pcUrlArr = ServiceUrlModel::where($condition)->column('url');
        $condition = [];
        $condition[] = ['type', '=', $type];
        $condition[] = ['mode', '=', ServiceUrlModel::PHONE_MODE];
        $phoneUrlArr = ServiceUrlModel::where($condition)->column('url');
        $arr = [
            'pc' => $pcUrlArr,
            'phone' => $phoneUrlArr
        ];
        $apiRedis->set($key, json_encode($arr), 86400);
        return $arr;
    }


    //清除缓存
    public static function clearCache(){
        $apiRedis=new ApiRedis();
        $apiRedis->delete(self::TELEGRAM_CACHE_KEY);
        $apiRedis->delete(self::WHATSAPP_CACHE_KEY);
    }

}