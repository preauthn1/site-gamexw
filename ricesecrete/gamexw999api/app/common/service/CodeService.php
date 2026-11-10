<?php

namespace app\common\service;

use app\api\redis\ApiRedis;
use app\common\lib\Util;

class CodeService{

    const IP_KEY = 'ip_code_day_count_limit_key_v2_';
    const IP_DOMAIN_KEY = 'ip_domain_code_day_count_limit_key_v1_';

    //获取IPkey
    public static function getIpKey($type = 'phone'){
        $ip = Util::get_ip();
        $currentDay = date('Ymd');
        $ipDomain = getIpDomain($ip);
        $ipCountKey = self::IP_KEY . $ip . '_' . $type . '_' . $currentDay;
        $ipDomainCountKey = self::IP_DOMAIN_KEY . $ipDomain . '_' . $type . '_' . $currentDay;
        return ['ip_key' => $ipCountKey, 'ip_domain_key' => $ipDomainCountKey];
    }

    /**
     * 验证验证码发送次数次数 TODO 该方法返回的判断成功需要用 ===true 而不是 ==true
     * @param $type phone手机，email邮箱
     * @return string|true
     */
    public static function verifyCodeCount($type = 'phone'){
        //获取ip每天发送验证码次数限制
        $ipDayCountLimit = env('ip_code_day_count_limit', 10);
        //获取ip域每天发送验证码次数限制
        $ipDomainDayCountLimit = env('ip_domain_code_day_count_limit', 100);

        $apiRedis = new ApiRedis();

        $expireTime = strtotime('tomorrow') - time();#设置缓存过期时间，计算现在时间到明天时间
        $ipKeyResult = self::getIpKey($type);
        $ipCountKey = $ipKeyResult['ip_key'];
        $ipDomainCountKey = $ipKeyResult['ip_domain_key'];

        if( !$apiRedis->has($ipCountKey) ){
            //设置缓存
            $apiRedis->set($ipCountKey, 1, $expireTime);
            $apiRedis->set($ipDomainCountKey, 1, $expireTime);
            return true;
        }
        $ipCurrentCount = $apiRedis->get($ipCountKey);
        if( $ipCurrentCount >= $ipDayCountLimit ){
            return '当前ip今日发送验证码次数已达到上限';
        }
        if( !$apiRedis->has($ipDomainCountKey) ) {
            return true;
        }
        $ipDomainCurrentCount = $apiRedis->get($ipDomainCountKey);
        if( $ipDomainCurrentCount >= $ipDomainDayCountLimit ){
            return '当前ip域今日发送验证码次数已达到上限';
        }
        //设置缓存
        $apiRedis->set($ipCountKey, $ipCurrentCount + 1, $expireTime);
        $apiRedis->set($ipDomainCountKey, $ipDomainCurrentCount + 1, $expireTime);
        return true;
    }

    //发送失败减少缓存次数
    public static function sendCodeFailReduceCount($type = 'phone'){
        $apiRedis = new ApiRedis();

        $ipKeyResult = self::getIpKey($type);
        $ipCountKey = $ipKeyResult['ip_key'];
        $ipDomainCountKey = $ipKeyResult['ip_domain_key'];

        $expireTime = strtotime('tomorrow') - time();#设置缓存过期时间，计算现在时间到明天时间

        if( $apiRedis->has($ipCountKey) ){
            $ipCurrentCount = $apiRedis->get($ipCountKey);
            if( $ipCurrentCount > 1 ){
                $apiRedis->set($ipCountKey, $ipCurrentCount - 1, $expireTime);
            }else{
                $apiRedis->delete($ipCountKey);
            }
        }
        if( $apiRedis->has($ipDomainCountKey) ){
            $ipDomainCurrentCount = $apiRedis->get($ipDomainCountKey);
            if( $ipDomainCurrentCount > 1 ){
                $apiRedis->set($ipDomainCountKey, $ipDomainCurrentCount - 1, $expireTime);
            }else{
                $apiRedis->delete($ipDomainCountKey);
            }
        }
        return true;
    }


}