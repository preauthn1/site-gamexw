<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\model\MusicModel;
use app\common\model\UrlModel;

class MusicFactory{

    //获取音乐列表
    public function getMusicList(){
        $condition = [];
        $condition['status'] = 1;
        $apiRedis = new ApiRedis();
        $key = 'music_list_v1';
        if( $apiRedis->has($key) ){
            $list = json_decode($apiRedis->get($key));
        }else{
            $list = MusicModel::where($condition)->field('id,name,size,file')->order('sort desc,id desc')->select();
            //设置一天缓存
            $apiRedis->set($key, json_encode($list), 86400);
        }
        return ['data' => $list, 'code' => 1];
    }
}