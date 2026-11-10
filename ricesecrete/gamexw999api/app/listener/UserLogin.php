<?php
declare (strict_types = 1);

namespace app\listener;
use app\api\factory\LoginFactory;
use app\common\model\UserLoginModel;
use app\common\model\UserModel;
use app\common\lib\Util;
class UserLogin
{
    /**
     * 事件监听处理
     *
     * @return mixed
     */
    public function handle(\app\event\UserLogin $event)
    {
        $user = $event->user;

        $ip = Util::get_ip();
        $up_user = [];
        $up_user['id'] = $user['id'];
        $up_user['usertokenkey'] = $user['usertokenkey'];
        $up_user['usertoken'] = $user['usertoken'];
        $up_user['login_time']=time();
        $up_user['end_login_ip']=$ip;
        $up_user['is_ol']=1;
        $up_user['ol_time']=time();
        $up_user['mail_bind_status']=$user['mail_bind_status'];
        $up_user['phone_bind_status']=$user['phone_bind_status'];

        UserModel::update($up_user);//修改token

        //计算用户相同ip数量
        (new LoginFactory())->handelSameIpCount($up_user);
        UserLoginModel::insertGetId([
            'uid'=>$user['id'],
            'login_time'=>time(),
            'login_ip'=>$ip,
            'device' => $_SERVER["HTTP_USER_AGENT"],
            'device_type' => $user['device_type']??'',
            'url' => getAppUrl()
        ]);
    }
}
