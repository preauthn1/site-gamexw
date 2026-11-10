<?php

namespace app\common\service;
use app\api\redis\ApiRedis;
use app\common\FastGame\Base\Utils;
use app\common\FastGame\Socket\SockPlugin;
use app\common\lib\Util;
use GuzzleHttp\Client;
use phpseclib3\Crypt\Random;

class MessageService {

    // 管理后台用户组名称
    const MANAGE_GROUP = 'manage';
    // 前端用户组名称
    const USER_GROUP = 'user';

    /**
     * 发送json格式消息给用户
     * @param array $msg
     * @param int $uid
     * @param string $mode
     * @param int $resend 失败重发(分钟)
     * @return void
     */
    public static function sendJsonMsg(array $msg, int $uid, string $mode = 'json', $showtime = 0, $title = '消息通知',$resend=0) {
        if( isset($msg['amount']) && is_numeric($msg['amount']) && $msg['amount'] == 0 ){
            if( $msg['amount'] == 0 ){
                //判断金额或者积分为零的情况不发送消息
                return true;
            }else{
                $msg['amount'] = round($msg['amount'], 2);
            }
        }
        $msg['current_symbol'] = Util::i18n_currency_no_amount();
        self::sendMsg(json_encode($msg, JSON_UNESCAPED_UNICODE), $uid, $mode, $showtime, $title,$resend);
    }
    /**
     * 发送充值到账消息
     * @param $uid 用户ID
     * @param $amount 充值金额
     * @param $mode 消息模式
     * @param int $resend 失败重发（分钟）
     * @return void
     */
    public static function sendRechargeMsg($uid, $amount, $mode = 'recharge',$resend=0) {
        $msg = "您的充值已到账,到账金额:";
        self::sendJsonMsg(['msg' => $msg, 'amount' => $amount], $uid, $mode, 5, '消息通知',$resend);
    }

    /**
     * 发送提现审核消息
     * @param $uid 用户ID
     * @param $status 提现状态 2 失败，3成功
     * @param $mode 消息模式
     * @param $msg 自定义消息
     * @return void
     */
    public static function sendWithdrawMsg($uid, $status, $mode = 'withdraw', $msg = '',$resend=0) {
        if( !$msg ){
            if ($status == 3) {
                $msg = "您的提现申请被驳回,如有疑问请及时联系客服";
            } else {
                $msg = "您的提现申请已经处理成功,请及时查收";
            }
        }
        self::sendMsg($msg, $uid, $mode, 5, '消息通知',$resend);
    }

    /**
     * 发送广告消息
     * @param $uid 用户ID
     * @param $data 活动信息
     * @param $mode 消息模式
     * @param int $resend 重发分钟数
     * @return void
     */
    public static function sendAdvertisingMsg($uid, $data, $mode = 'advertising', $resend=0) {
        self::sendModeMsg('广告消息',$uid,$data,$mode, $resend);
    }

    /**
     * 通用消息通知
     * 通过 $data 的内容（查询条件）向接口请求数据
     * @param $msg
     * @param $uid
     * @param $data
     * @param string $mode
     * @param int $resend
     */
    public static function sendModeMsg($msg,$uid, $data, $mode = 'advertising',$resend=0){
        $data['mode'] = $mode;
        $data = ['msg' => $msg, 'data' => $data];
        self::sendMsg(json_encode($data, JSON_UNESCAPED_UNICODE), $uid, $mode, 5,$resend);
    }

    /**
     * 发送用户消息
     * @param $msg 消息内容
     * @param $uid 用户ID
     * @param $mode 消息模式
     * @param $showtime 显示几秒
     * @param $title 消息标题
     * @param int $resend 失败重发(分钟)
     * @return void
     */
    public static function sendMsg($msg, $uid=null, $mode='toast', $showtime=0, $title='消息通知',$resend=0) {
        //请求主服务器写消息信息
        if( env('redis_message.status', 0) == 0 ){
            //不开启直接写入消息服务器redis
            self::publicSendMsg($msg, $uid, $mode, $showtime, $title, $resend);
        }else{
            try {
                //开启则将参数发给主服务器，由主服务器来链接消息服务器redis写入发送消息
                $client = new Client();
                // 构造 POST 请求的数据，值都需要转成string类型，否则接收时候签名验证错误
                $data = [
                    'msg' => (string)$msg,
                    'uid' => (string)$uid,
                    'mode' => (string)$mode,
                    'showtime' => (string)$showtime,
                    'title' => (string)$title,
                    'resend' => (string)$resend,
                    'random_str' => strtoupper(get_rand_char(32)),
                ];
                //签名
                ksort($data);
                $data['sign'] = strtoupper(md5(json_encode($data).env('redis_message.sign_key').$data['random_str']));
                //获取请求域名
                $domain = env('redis_message.domain_url');
                if( !$domain ){
                    $apiRedis = new ApiRedis();
                    $sys = $apiRedis->systemInfo();
                    $domain = $sys['domain'];
                }
                // 发送 POST 请求
                $response = $client->post($domain.'/redis_message/send', [
                    'form_params' => $data,
                    'timeout' => env('redis_message.timeout'),
                ]);
                // 获取响应内容
                $content = $response->getBody()->getContents();
                $res = json_decode($content, true);
                if( !isset($res['code']) || $res['code'] != 1 ){
                    Utils::L("socket", [$res['msg']??'消息处理错误'], false, true);
                }
            }catch (\Exception $e){
                Utils::L("socket", $e->getMessage(), false, true);
            }
        }

    }



    /**
     * 发送用户消息
     * @param $msg 消息内容
     * @param $uid 用户ID
     * @param $mode 消息模式
     * @param $showtime 显示几秒
     * @param $title 消息标题
     * @param int $resend 失败重发(分钟)
     * @return void
     */
    public static function publicSendMsg($msg, $uid=null, $mode='toast', $showtime=0, $title='消息通知',$resend=0) {
        if (!self::isOpen()) {
            return;
        }
        try {
            SockPlugin::writeStandardMsg($msg, Utils::getSocketUid($uid), $mode, $showtime, $title,$resend);
        } catch (\Exception $e) {
            Utils::L("socket", [$e->getMessage(), $e->getTraceAsString()], false, true);
        }
    }

    /**
     * 发送toast消息
     * @param $msg 消息内容
     * @param $uid 用户ID
     * @param $title 消息标题
     * @param $showtime
     * @return void
     */
    public static function sendUserToastMsg($msg, $uid=null, $title='消息通知', $showtime=0) {
        self::sendMsg($msg, $uid, 'toast', $showtime, $title);
    }

    /**
     * 发送pop消息
     * @param $msg 消息内容
     * @param $uid 用户ID
     * @param $title 消息标题
     * @param $showtime
     * @return void
     */
    public static function sendUserPopMsg($msg, $uid=null, $title='消息通知', $showtime=0) {
        self::sendMsg($msg, $uid, 'pop', $showtime, $title);
    }

    /**
     * 发送用户组消息
     * @param $msg 消息内容
     * @param $mode 消息模式
     * @param $showtime
     * @param $title 消息标题
     * @param int $resend 失败重发(分钟)
     * @return void
     */
    public static function sendUserGroupMsg($msg, $mode='toast', $showtime=0, $title='消息通知',$resend=0) {
        self::sendGroupMsg($msg, self::USER_GROUP, $mode, $showtime, $title,$resend);
    }

    public static function sendAdminMsg($msg, $uid=null, $mode='toast', $showtime=0, $title='消息通知') {
        self::sendMsg($msg, Utils::getSocketUid($uid, self::MANAGE_GROUP), $mode, $showtime, $title);
    }


    /**
     * 发送管理后台组别消息
     * @param $msg 消息内容
     * @param $mode 消息模式
     * @param $showtime 显示几秒
     * @param $title 消息标题
     * @param int $resend 失败重发(分钟)
     * @param int $type 0其他 1 线下充值 2线上充值  3提现 4实名认证
     * @return void
     */
    public static function sendAdminGroupMsg($msg, $mode='toast', $showtime=0, $title='消息通知',$resend=0, $type = 0) {
        self::sendGroupMsg($msg, self::MANAGE_GROUP, $mode, $showtime, $title, $resend, $type);
    }

    /**
     * 发送组别消息
     * @param $msg 消息内容
     * @param $groups 组别
     * @param $mode 消息模式
     * @param $showtime 显示几秒
     * @param $title 消息标题
     * @param $resend 失败重发分钟数
     * @param int $type 0其他 1 线下充值 2线上充值  3提现 4实名认证
     * @return void
     */
    public static function sendGroupMsg($msg, $groups=null, $mode='toast', $showtime=0, $title='消息通知',$resend=0, $type = 0) {
        if (!self::isOpen()) {
            return;
        }
        try {
            SockPlugin::writeGroupMsg($msg, Utils::getSocketUid($groups), $mode, $showtime, $title,$resend, $type);
        } catch (\Exception $e) {
            Utils::L("socket", [$e->getMessage(), $e->getTraceAsString()], false, true);
        }
    }

    /**
     * 注册普通用户
     * @param $uid
     * @param $token
     * @param $group
     * @return void
     */
    public static function registerMsgUser($uid, $token, $group = null) {
        if (!self::isOpen()) {
            return;
        }
        try {
            SockPlugin::registerMsgUser(Utils::getSocketUid($uid), $token, Utils::getSocketUid($group));
        } catch (\Exception $e) {
            Utils::L("socket", [$e->getMessage(), $e->getTraceAsString()], false, true);
        }
        return Utils::getSocketUid($uid);
    }

    /**
     * 注册管理后台用户
     * @param $uid
     * @param $token
     * @return string
     */
    public static function registerMsgAdmin($uid, $token) {
        $uid = Utils::getSocketUid($uid, self::MANAGE_GROUP);
        self::registerMsgUser($uid, $token, self::MANAGE_GROUP);
        return Utils::getSocketUid($uid);
    }

    /**
     * 是否开启消息服务
     * @return bool
     */
    public static function isOpen() {
        return !!Utils::ini('open', 0);
    }

    /**
     * 发送任务完成通知
     * @param $uid 用户ID
     * @param $type 消息类型 task 任务 sign 签到， action 活动，pdd PDD转盘， invite 邀请，vip_week VIP周礼金，vip_month VIP月礼金
     * @param $finishTimes
     * @param $title
     * @param $msg
     * @return true
     */
    public static function sendTaskFinishMessageNotify($uid, $type = 'task', $finishTimes = 1, $title = '消息通知', $msg = '您有新的任务完成'){
        //发送任务完成通知
        $data = [
            'finish_times' => $finishTimes,
            'msg' => $msg,
            'type' => $type
        ];
        MessageService::sendMsg(json_encode($data), $uid, 'task', 5, $title, env('message_reset_time', 0));
        return true;
    }
}