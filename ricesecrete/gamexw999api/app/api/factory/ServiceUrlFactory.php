<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Util;
use app\common\model\ServiceUrlModel;
use app\common\service\ServiceUrlService;
use app\common\service\SystemService;
use think\facade\Cache;

class ServiceUrlFactory{

    //获取telegramPc链接
    public function telegramPc(){
        $uid = request()->param()['u'] ?? 0;
        $apiRedis=new ApiRedis();
        $key = 'telegram_url_pc_key_'.$uid;
        if( $apiRedis->has($key) && $uid != 0 ){
            $url = $apiRedis->get($key);
        }else{
            //获取配置
            $data = ServiceUrlService::getTelegramUrl(ServiceUrlModel::TELEGRAM_TYPE)['pc'];
            $url = $this->getUrl($data);
            if( $uid ){
                $apiRedis->set($key, $url, 60*60*2);
            }
        }
        //将链接放入缓存,定时任务执行
        $this->handleUrl($url, $uid);
        // 进行页面跳转到新的 URL
        header("Location: " . $url);
        exit; // 确保在跳转后停止脚本执行
    }

    //获取telegramPhone链接
    public function telegramPhone(){
        $uid = request()->param()['u'] ?? 0;
        $apiRedis=new ApiRedis();
        $key = 'telegram_url_phone_key_'.$uid;
        if( $apiRedis->has($key) && $uid != 0 ){
            $url = $apiRedis->get($key);
        }else{
            //获取配置
            $data = ServiceUrlService::getTelegramUrl(ServiceUrlModel::TELEGRAM_TYPE)['phone'];
            $url = $this->getUrl($data);
            if( $uid ){
                $apiRedis->set($key, $url, 60*60*2);
            }
        }
        //将链接放入缓存,定时任务执行
        $this->handleUrl($url, $uid);
        // 进行页面跳转到新的 URL
        header("Location: " . $url);
        exit; // 确保在跳转后停止脚本执行
    }

    //获取WhatsApp
    public function whatsapp(){
        $uid = request()->param()['u'] ?? 0;
        $apiRedis=new ApiRedis();
        $key = 'whatsapp_url_key_'.$uid;
        if( $apiRedis->has($key) && $uid != 0 ){
            $url = $apiRedis->get($key);
        }else{
            //获取配置
            $data = ServiceUrlService::getTelegramUrl(ServiceUrlModel::WHATSAPP_TYPE);
            $data = array_merge($data['pc'], $data['phone']);
            $url = $this->getUrl($data);
            if( $uid ){
                $apiRedis->set($key, $url, 60*60*2);
            }
        }
        //将链接放入缓存,定时任务执行
        $this->handleUrl($url, $uid);
        // 进行页面跳转到新的 URL
        header("Location: " . $url);
        exit; // 确保在跳转后停止脚本执行
    }

    //处理链接
    private function handleUrl($url, $uid = 0){
        $urlCacheUrlKey = 'handle_url';
        //记录到队列中
        $ip = Util::get_ip();
        Cache::store('redis')->rpush($urlCacheUrlKey, json_encode([
            'uid' => $uid,
            'url' => $url,
            'ip' => $ip,
            'address' => ip2location($ip),
            'create_time' => time(),
            'update_time' => time()
        ]));
        return true;
    }

    //获取url
    private function getUrl($data){
        $allUrlArr = [];
        if( $data ){
            $allUrlArr = $data;
        }else{
            //没有从后台站点设置获取
            $systemData = SystemService::getSiteConfigData();
            foreach ($systemData as $k => $v){
                if( $k == 'pc_service_url' ){
                    $link = str_replace('，', ',', $v);
                    $urlArr = explode(',', $link);
                    $allUrlArr = $urlArr;
                    break;
                }
            }
        }
        if( $allUrlArr ){
            $_key = rand(0, count($allUrlArr) - 1);
            $url = $allUrlArr[$_key];
        }else{
            $url = '';
        }
        return $url;
    }

}