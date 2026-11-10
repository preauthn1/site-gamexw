<?php

namespace app\common\service;

use app\api\redis\ApiRedis;
use app\common\model\ServiceUrlModel;
use app\model\SysSets;

class SystemService{

    public static function getSiteConfigData(){
        $apiRedis=new ApiRedis();
        $websiteKey = 'website_key';
        if( $apiRedis->has($websiteKey) ){
            $data = $apiRedis->get($websiteKey);
        }else {
            $data = SysSets::where('set_label', '=', '站点配置')->column('set_value', 'set_code');
            $apiRedis->set($websiteKey, $data, 86400);
        }
        return $data;
    }

    //获取站点数据
    public static function getSiteDataByUid($uid = 0){
        $apiRedis=new ApiRedis();
        $data = self::getSiteConfigData();
        if( $uid ){
            //用户登录情况2小时换一次
            $changeServiceUrlKey = 'change_system_service_url_'.$uid;
            if( $apiRedis->has($changeServiceUrlKey) ){
                $data = json_decode($apiRedis->get($changeServiceUrlKey), true);
            }else {
                foreach ($data as $k => $v) {
                    if ($k == 'pc_service_url' || $k == 'mobile_service_url' || $k == 'whatsapp_public_url' ) {
                        //将链接转成数组随机取出一个
                        if( $v ){
                            $data[$k] = handleManyUrl($v);
                        }
                    }
                }
                //设置两小时缓存
                $apiRedis->set($changeServiceUrlKey, json_encode($data), 60*60*2);
            }
        }else{
            //用户没登陆情况每次都随机
            foreach ($data as $k => $v) {
                if ($k == 'pc_service_url' || $k == 'mobile_service_url' || $k == 'whatsapp_public_url' ) {
                    //将链接转成数组随机取出一个
                    if( $v ) {
                        $data[$k] = handleManyUrl($v);
                    }
                }
            }
        }
//        foreach ($data as $k => $v) {
//            if( $k == 'pc_service_url' ){
//                $data[$k] = self::getShareUrlByType(ServiceUrlModel::TELEGRAM_TYPE, ServiceUrlModel::PC_MODE).'?u='.$uid;
//            }elseif ( $k == 'mobile_service_url' ){
//                $data[$k] = self::getShareUrlByType(ServiceUrlModel::TELEGRAM_TYPE, ServiceUrlModel::PHONE_MODE).'?u='.$uid;
//            }elseif( $k == 'whatsapp_public_url' ){
//                $data[$k] = self::getShareUrlByType(ServiceUrlModel::WHATSAPP_TYPE).'?u='.$uid;
//            }
//        }
        return $data;
    }

    //根据类型和型号获取分享链接
    public static function getShareUrlByType($type = 0, $mode = 0){
        $domain = getShareDomain();
        if( $type == ServiceUrlModel::TELEGRAM_TYPE ){
            //telegram
            if( $mode == ServiceUrlModel::PC_MODE ){
                //pc
                $url = $domain.'/tc';
            }else{
                //phone
                $url = $domain.'/te';
            }
        }else{
            //whatsapp
            $url = $domain.'/wa';
        }
        return $url;
    }


}