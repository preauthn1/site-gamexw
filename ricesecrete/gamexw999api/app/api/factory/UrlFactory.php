<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\model\UrlModel;

class UrlFactory{

    //获取菜单节点
    public function getMenu(){
        $condition = [];
        $condition['is_menu'] = 1;
        //从ENV文件判断是否开启
        $isOnCustomMenu = env('IS_ON_CUSTOM_MENU', 0);
        if( $isOnCustomMenu == 1 ){
            $apiRedis = new ApiRedis();
            $key = 'custom_menu_list';
            if( $apiRedis->has($key) ){
                $list = json_decode($apiRedis->get($key));
            }else{
                $list = UrlModel::where($condition)->order('sort desc,id desc')->select();
                //设置一天缓存
                $apiRedis->set($key, json_encode($list), 86400);
            }
        }else{
            $list = [];
        }
        return ['data' => $list, 'is_on_custom_menu' => $isOnCustomMenu, 'code' => 1];
    }
}