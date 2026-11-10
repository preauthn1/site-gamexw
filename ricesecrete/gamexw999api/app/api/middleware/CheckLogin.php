<?php

namespace app\api\middleware;

use app\api\redis\ApiRedis;
use app\common\model\IpLimitModel;
use app\common\lib\Util;
use app\common\lib\TokenCheck;
use app\common\model\UserLogModel;
use Exception;
use think\facade\Cache;

class CheckLogin {

    /**
     * @param $codes 自定义返回code

     */
    public function handle($request, \Closure $next, $codes = []) {
        $rsps = [];
        //是否关闭白名单功能（1关闭，0开启）
        $is_closed_white_list = env('is_closed_white_list', 0);
        if(!$is_closed_white_list){
            $ip = IpLimitModel::where('ip', Util::get_ip())->where("admin_status", 1)->find(); //禁用！
            if (isset($ip['id'])) {
                throw new \app\common\exception\MessageException("forbidden ip：" . Util::get_ip(), 403);
            }
        }
        $token = null;
        $header = $request->header();
        try {
            $token = TokenCheck::run($header, $codes);
        } catch (Exception $e) {
            // 是否强制登陆， 存在$codes[is_optional_login]时为不强制登陆状态
            if (!isset($codes['is_optional_login'])) {
                throw new \app\common\exception\MessageException(lang($e->getMessage()), $e->getCode());
            }
        }
        if (!empty($token)) {
            //处理在线时长
//            $this->handleOnlineTime($token);
        }
        $request->token = $token;

        return $next($request);
    }

    private function handleOnlineTime($token){
        $time = time();#当前时间
        $loginExpireTime = 60 * 5;#首次登录过期时间
        $recordListSecond = 60 * 3;#记录到队列中的秒数设置(该时间必须要比定时任务执行时间长，不然会出现数据重复情况)
        $apiRedis = new ApiRedis();
        $startLoginTimeKey = 'start_login_time_'.$token->uid;#用户登录开始缓存key
        $userTimeLogListKey = 'today_login_time_list';#用户时长日志列表缓存key
        if( !$apiRedis->has($startLoginTimeKey) ){
            //第一次记录缓存，超过设置过期时间则重新计算在线时长
            LOGIN_START_TIME:
            $apiRedis->set($startLoginTimeKey, $time, $loginExpireTime);
        }else{
            $userLoginTime = $apiRedis->get($startLoginTimeKey);
            //重新设置登录开始缓存时间
            $apiRedis->set($startLoginTimeKey, $userLoginTime, $loginExpireTime);
            //判断如果超过设置的时长后记录到队列中，定时处理
            if( $time - $userLoginTime > $recordListSecond ){
                //记录到队列中
                Cache::store('redis')->rpush($userTimeLogListKey, json_encode([
                    'uid' => $token->uid,
                    'type' => UserLogModel::ONLINE_TIME_TYPE,
                    'data' => $time - $userLoginTime,
                    'datetime' => strtotime('today')
                ]));
                //重新设置用户登录开始缓存值
                goto LOGIN_START_TIME;
            }
        }
    }

}
