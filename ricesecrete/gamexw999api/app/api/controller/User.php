<?php
namespace app\api\controller;
use app\api\factory\UserVerifyFactory;
use app\common\lib\Respons;
use app\api\factory\UserFactory;
use think\Request;
class User
{
    //个人资料
    public function info(Request $request){
        $rsps=(new UserFactory($request->token->uid))->info_run();
        return Respons::run($rsps);
    }

    //个人资料
    public function info_v5(Request $request){
        $rsps=(new UserFactory($request->token->uid))->info_v5_run();
        return Respons::run($rsps);
    }

    //快捷金额
    public function quick(Request $request){
        $rsps=(new UserFactory($request->token->uid))->quick_run();
        return Respons::run($rsps);
    }

    //快捷金额保存
    public function quick_save(Request $request){
        $rsps=(new UserFactory($request->token->uid))->quick_save_run();
        return Respons::run($rsps);
    }

    //修改用户资料
    public function editinfo(Request $request){
        $rsps=(new UserFactory($request->token->uid))->editinfo_run();
        return Respons::run($rsps);
    }

    //支付密码设置、修改
    public function pay_password(Request $request){
        $rsps=(new UserFactory($request->token->uid))->pay_password_run();
        return Respons::run($rsps);
    }

    //验证手机号
    public function check_phone(Request $request){
        $rsps=(new UserFactory($request->token->uid))->check_phone_run();
        return Respons::run($rsps);
    }


    //发送验证码
    public function send_code(Request $request){
        $rsps=(new UserFactory($request->token->uid))->send_code_run();
        return Respons::run($rsps);
    }

    //流水记录
    public function water_log(Request $request){
        $rsps=(new UserFactory($request->token->uid))->water_log_run();
        return Respons::run($rsps);
    }

     //忘记密码
    public function forget(Request $request){
        $rsps=(new UserFactory())->forget_run();
        return Respons::run($rsps);
    }

    //邮箱忘记密码
    public function forget_mail(Request $request){
        $rsps=(new UserFactory())->forget_mail_run();
        return Respons::run($rsps);
    }

    //登出
    public function logout(Request $request){
        $rsps=(new UserFactory($request->token->uid))->logout_run();
        return Respons::run($rsps);
    }

    //vip信息
    public function vip(Request $request){
        $rsps=(new UserFactory($request->token->uid))->vip_run();
        return Respons::run($rsps);
    }

//    public function refresh(Request $request){
//        $rsps=(new UserFactory($request->token->uid))->refresh_run();
//        return Respons::run($rsps);
//    }

    public function troni(Request $request){
        $rsps=(new UserFactory($request->token->uid))->troni_run();
        return Respons::run($rsps);
    }

    public function get_balance(Request $request){
        $rsps=(new UserFactory($request->token->uid))->get_balance_run();
        return Respons::run($rsps);
    }

    public function login(Request $request){
        $rsps=(new UserFactory($request->token->uid))->login_run();
        return Respons::run($rsps);
    }

    //用户谷歌二维码
    public function user_google(Request $request){
        $rsps=(new UserFactory($request->token->uid))->user_google_run();
        return Respons::run($rsps);
    }

    //用户绑定谷歌验证
    public function bind_user_google(Request $request){
        $rsps=(new UserFactory($request->token->uid))->bind_user_google_run();
        return Respons::run($rsps);
    }

    //验证设置
    public function google_check(Request $request){
        $rsps=(new UserFactory($request->token->uid))->google_check_run();
        return Respons::run($rsps);
    }

    //vip信息不需要登录
    public function vip2(Request $request){
        $rsps=(new UserFactory())->vip2_run();
        return Respons::run($rsps);
    }

    //日志
    public function log(Request $request){
        $rsps=(new UserFactory($request->token->uid))->log_run();
        return Respons::run($rsps);
    }

    //领取等级奖励
    public function receiveLevelReward(Request $request){
        $result = (new UserFactory($request->token->uid))->receiveLevelReward_run(\think\facade\Request::param());
        return Respons::run($result);
    }
    //个人资料
    public function getWsToken(Request $request){
        $rsps = (new UserFactory($request->token->uid))->getWsToken();
        return Respons::run($rsps);
    }

    //赠金领取
    public function give(Request $request){
        $rsps = (new UserFactory($request->token->uid))->give_run();
        return Respons::run($rsps);
    }

    //余额明细
    public function moneyRecord(Request $request){
        $rsps = (new UserFactory($request->token->uid))->moneyRecord();
        return Respons::run($rsps);
    }

    //用户邀请链接
    public function shareCode(){
        (new UserFactory(0))->inviteUrl();
    }

    //用户默认头像列表
    public function getDefaultHeadImg(){
        $rsps = (new UserFactory())->getDefaultHeadImg();
        return Respons::run($rsps);
    }

    /**
     * 添加实名制认证数据
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function addVerify(Request $request){
        $rsps = (new UserVerifyFactory($request->token->uid))->addVerify();
        return Respons::run($rsps);

    }

    /**
     * 获取需要上传的数据
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getVerifyConfig(Request $request){
        $rsps = (new UserVerifyFactory($request->token->uid))->getVerifyConfig();
        return Respons::run($rsps);
    }

    /**
     * 获取用户流水统计
     * @param Request $request
     * @return \think\response\Json
     */
    public function getUserWaterStatistics(Request $request){
        $rsps = (new UserFactory($request->token->uid))->getUserWaterStatistics();
        return Respons::run($rsps);
    }

    /**
     * 修改用户邮件通知状态
     * @param Request $request
     * @return \think\response\Json
     */
    public function check_email_notify(Request $request){
        $rsps = (new UserFactory($request->token->uid))->check_email_notify_status();
        return Respons::run($rsps);
    }

}