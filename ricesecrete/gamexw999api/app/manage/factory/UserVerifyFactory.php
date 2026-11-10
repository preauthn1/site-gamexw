<?php

namespace app\manage\factory;

use app\common\lib\Util;
use app\common\model\UserAdminLogModel;
use app\common\model\UserMsgModel;
use app\common\service\MemberService;
use app\common\service\MessageService;
use think\facade\Session;

class UserVerifyFactory{

    //实名认证状态处理
    public static function handleVerifyStatus($uid, $status, $remarks = ''){
        //认证状态  0未认证 1认证中  2认证通过 3认证失败
        if( $status == 2 ){
            //认证通过
            //触发活动
            MemberService::activityDiscountTrigger($uid);
            //发送站内信和消息通知
            $msg = "恭喜您，您的实名认证已通过!";
            $statusStr = "已通过";
            Util::email_notify('verify', $uid);
        }else if( $status == 3 ){
            //认证失败
            $msg = "很抱歉，您的实名认证未通过，请重新提交认证申请。原因: {:remarks}";
            $statusStr = "未通过";
        }else{
            return true;
        }
        //发送站内信
        UserMsgModel::add_msg($uid, '实名认证审核', $msg, 26, $uid, ['remarks' => $remarks]);
        //发送新的通知
        MessageService::sendMsg("您有新的消息!", $uid, 'pop');
        //写入会员日志
        UserAdminLogModel::add_log($uid, '实名认证审核,状态:'. $statusStr.($remarks? ',原因:'.$remarks : ''),Session::get("adminInfo.name"));

    }


}