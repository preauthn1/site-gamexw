<?php

namespace app\common\service;

use app\common\lib\Util;
use app\common\model\AdsChannelModel;
use app\common\model\UserModel;
use app\common\pixel\Pixel;
use Exception;
use think\facade\Log;

class PixelService
{

    public static $aid = 0;

    /**
     * 发送首次页事件
     * @param int $aid 推广id
     * @return false|null
     */
    public static function page($aid)
    {
        try {
            $userData = [
                'client_ip_address' => Util::get_ip(),
            ];
            self::$aid = $aid;
            return self::trace('page', '', [], $userData);
        } catch (Exception $e) {
            Log::error([$e->getMessage(), $e->getTraceAsString()]);
            return false;
        }
    }

    /**
     * 发送注册事件
     * @param int $uid 用户ID
     * @return false|null
     */
    public static function reg($uid)
    {
        try {
            $userData = self::getUserData($uid);
            return self::trace('reg', $uid, [], $userData);
        } catch (Exception $e) {
            Log::error(['PixelService', $e->getMessage(), $e->getTraceAsString()]);
            return false;
        }
    }

    /**
     * 发送首充事件
     * @param int $uid
     * @param string $orderId
     * @param string $currency
     * @param float $amount
     * @return false|null
     */
    public static function firstRecharge($uid, $orderId, $currency, $amount) {
        $currency = Util::i18n_currency_code($currency);
        try {
            $userData = self::getUserData($uid);
            return self::trace('first_recharge', $orderId, [
                'currency' => strtoupper($currency),
                'value' => sprintf4f($amount, 2, false)
            ], $userData);
        } catch (Exception $e) {
            Log::error(['PixelService', $e->getMessage(), $e->getTraceAsString()]);
            return false;
        }
    }

    /**
     * 发送充值事件
     * @param int $uid
     * @param string $orderId
     * @param string $currency
     * @param float $amount
     * @return false|null
     */
    public static function recharge($uid, $orderId, $currency, $amount) {
        $currency = Util::i18n_currency_code($currency);
        try {
            $userData = self::getUserData($uid);
            return self::trace('recharge', $orderId, [
                'currency' => strtoupper($currency),
                'value' => sprintf4f($amount, 2, false)
            ], $userData);
        } catch (Exception $e) {
            Log::error(['PixelService', $e->getMessage(), $e->getTraceAsString()]);
            return false;
        }
    }
    /**
     * 发送提现事件
     * @param int $uid
     * @param string $orderId
     * @param string $currency
     * @param float $amount
     * @return false|null
     */
    public static function withdraw($uid, $orderId, $currency, $amount) {
        $currency = Util::i18n_currency_code($currency);
        try {
            $userData = self::getUserData($uid);
            return self::trace('withdraw', $orderId, [
                'currency' => strtoupper($currency),
                'value' => sprintf4f($amount, 2, false)
            ], $userData);
        } catch (Exception $e) {
            Log::error(['PixelService', $e->getMessage(), $e->getTraceAsString()]);
            return false;
        }
    }
    public static function trace($eventName, $eventId, $eventData, $userData) {
        $channel = self::getAdChannel();
        if (empty($channel)) {
            return false;
        }
        $config = [
            'code' => $channel['ac_code'],
            'token' => $channel['ac_token'],
        ];

        if (!empty($channel['ac_test_event_code'])) {
            $config['test_event_code'] = $channel['ac_test_event_code'];
        }

        $pixel = Pixel::init($channel['ac_adtype'], $config);
        return $pixel->send($eventName, $eventData, $eventId, $userData);
    }

    public static function setAid($aid) {
        static::$aid = $aid;
    }

    private static function getAdChannel() {
        return AdsChannelModel::where('ac_id', self::$aid)->find();
    }

    private static function getUserData($uid) {
        $user = UserModel::alias("a")
            ->join("dq_user_extra c","a.id=c.uid", 'left')
            ->where("a.id",$uid)
            ->field("a.id,a.username,a.ac_id,a.reg_ip,a.phone,a.mail,c.click_id")
            ->find();
        if (empty($user)) {
            return [];
        }
        $userData = [
            'client_ip_address' => $user['reg_ip'],
            'uid' => $uid
        ];
        self::$aid = $user['ac_id'];

        if (isset($user['phone']) && $user['phone']) {
            $userData['phone'] = $user['phone'];
        }
        if (isset($user['mail']) && $user['mail']) {
            $userData['email'] = $user['mail'];
        }
        if (isset($user['click_id']) && $user['click_id']) {
            $userData['click_id'] = $user['click_id'];
        }

        return $userData;
    }
}