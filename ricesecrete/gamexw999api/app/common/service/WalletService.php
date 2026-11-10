<?php

namespace app\common\service;

use app\api\redis\ApiRedis;
use app\common\model\UserModel;
use app\common\model\WalletSystemModel;

class WalletService{

    const WALLET_DATA_CACHE_KEY = 'get_wallet_data';

    //获取钱包数据
    public static function getWalletData($isDatabase = false){
        $apiRedis=new ApiRedis();
        $key = self::WALLET_DATA_CACHE_KEY;
        if($apiRedis->has($key) && !$isDatabase){
            return $apiRedis->get($key);
        }
        //从数据库获取
        $list = WalletSystemModel::select();
        $data = [];
        foreach ($list as $v){
            $data[$v['wallet_field']] = $v['wallet_value'];
        }
        //设置一天缓存
        $apiRedis->set($key, $data, 86400);
        return $data;
    }

    //判断用户是否可以重复领取优惠
    public static function judgeUserIsRePeatReceive($userId,$userDetail = null){
        if( !$userDetail ){
            $userDetail = UserModel::where('id', '=', $userId)->find();
        }
        if( !$userDetail ){
            return false;
        }
        //获取双钱包配置
        $walletConfig = self::getWalletData();
        if( $walletConfig['wallet_type'] != 1 || !isset($walletConfig['discount_can_repeat']) || $walletConfig['discount_can_repeat'] == 1 ){
            //没有设置双钱包或者开启重复领取，直接返回可领取
            return true;
        }
        $discountCanRepeatConditionAmount = $walletConfig['discount_can_repeat_condition_amount'] ?? 0;
        if( $userDetail['cjbalance'] > $discountCanRepeatConditionAmount ){
            return false;
        }
        return true;
    }

}