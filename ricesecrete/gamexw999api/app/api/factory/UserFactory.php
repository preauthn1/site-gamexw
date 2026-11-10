<?php

namespace app\api\factory;

use app\api\redis\ApiRedis;
use app\common\lib\Authenticator;
use app\common\lib\SmsCode;
use app\common\lib\Util;
use app\common\model\DefaultHeadModel;
use app\common\model\UserBalanceMxModel;
use app\common\model\UserCjBalanceMxModel;
use app\common\model\UserDistributionModel;
use app\common\model\UserExtraModel;
use app\common\model\UserGoogleModel;
use app\common\model\UserLevelModel;
use app\common\model\UserLevelRewardModel;
use app\common\model\UserLoginModel;
use app\common\model\UserModel;
use app\common\model\UserMsgModel;
use app\common\model\UserOfflineModel;
use app\common\model\UserQuickModel;
use app\common\model\UserThirdModel;
use app\common\redis\CommomRedis;
use app\common\service\BetService;
use app\common\service\MemberService;
use app\common\service\MessageService;
use app\common\service\UserBalanceMxService;
use app\common\validate\UserLevelValidate;
use Carbon\Carbon;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Lang;
use think\facade\Request;
use function foo\func;

class UserFactory
{
    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->uid = $uid;
    }

    //用户基本信息
    public function base_info_run()
    {
        $data['user'] = UserModel::uinfo($this->uid);
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //快捷金额
    public function quick_run()
    {
        $quick = UserQuickModel::where("uid", $this->uid)->find();
        if (!isset($quick['id'])) {
            $ApiRedis = new ApiRedis();
            $sys = $ApiRedis->systemInfo();
            $sys['quick_bet'] = isset($sys['quick_bet']) ? $sys['quick_bet'] : '';
            $quick_bet = explode(",", $sys['quick_bet']);
            if (count($quick_bet) == 3) {
                $quick['quick1'] = intval($quick_bet[0]);
                $quick['quick2'] = intval($quick_bet[1]);
                $quick['quick3'] = intval($quick_bet[2]);
            } else {
                $quick['quick1'] = 1000;
                $quick['quick2'] = 5000;
                $quick['quick3'] = 10000;
            }
        }
        $rsps['data'] = $quick;
        $rsps['code'] = 1;
        return $rsps;
    }

    //快捷金额保存
    public function quick_save_run()
    {
        $quick1 = $this->param['quick1'] ?? 100;
        $quick2 = $this->param['quick2'] ?? 200;
        $quick3 = $this->param['quick3'] ?? 500;

        $quick = UserQuickModel::where("uid", $this->uid)->find();
        if (!isset($quick['id'])) {
            $add['uid'] = $this->uid;
            $add['quick1'] = $quick1;
            $add['quick2'] = $quick2;
            $add['quick3'] = $quick3;
            UserQuickModel::insert($add);
        } else {
            $upd['quick1'] = $quick1;
            $upd['quick2'] = $quick2;
            $upd['quick3'] = $quick3;
            UserQuickModel::where("uid", $this->uid)->update($upd);
        }
        $rsps['code'] = 1;
        return $rsps;
    }

    public function logout_run()
    {
        Db::startTrans();
        try {
            $upd = [];
            $upd['is_ol'] = 0;
            UserModel::where("id", $this->uid)->update($upd);
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        return $rsps;
    }

    public function forget_run()
    {
        $phone = $this->param['phone'] ?? '';
        $password = $this->param['password'] ?? '';
        $vcode = $this->param['code'] ?? '';
        $qh = $this->param['qh'] ?? '86';
        if (empty($phone) || empty($password) || empty($vcode)) {
            ajax_return("信息不全，请补充", 0);
        }
        if (strlen($password) < 6) {
            ajax_return("密码长度不能低于6位字符", 0);
        }

        if (!checkString($password)) {
            ajax_return("密码只能数字和字母组合", 0);
        }

        $user = UserModel::where("phone", $phone)->find();
        if (empty($user)) {
            ajax_return("手机号不存在", 0);
        }

        $ApiRedis = new ApiRedis();
        $redisCode = $ApiRedis->get($phone . "_code");
        if ($vcode != $redisCode) {
            ajax_return("验证码错误", 0);
        }
        Db::startTrans();
        try {
            $upd['password'] = Util::userMd5Password($password);
            UserModel::where("id", $user['id'])->update($upd);
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        $ApiRedis->delete($phone . "_code");
        return $rsps;
    }

    public function forget_mail_run()
    {
        $mail = $this->param['mail'] ?? '';
        $mail_code = $this->param['mail_code'] ?? '';
        $password = $this->param['password'] ?? '';

        if (empty($mail) || empty($password) || empty($mail_code)) {
            ajax_return("信息不全，请补充", 0);
        }

        if (strlen($password) < 6) {
            ajax_return("密码长度不能低于6位字符", 0);
        }

        if (!checkString($password)) {
            ajax_return("密码只能数字和字母组合", 0);
        }

        $user = UserModel::where("mail", $mail)->find();
        if (empty($user)) {
            ajax_return("邮箱不存在", 0);
        }

        $ApiRedis = new ApiRedis();
        $redisCode = $ApiRedis->get($mail . "_code");
        if ($mail_code != $redisCode) {
            ajax_return("验证码错误", 0);
        }
        Db::startTrans();
        try {
            $upd['password'] = Util::userMd5Password($password);
            UserModel::where("id", $user['id'])->update($upd);
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        $ApiRedis->delete($mail . "_code");
        return $rsps;
    }

    //修改资料
    public function editinfo_run()
    {
        $this->param = Util::common_validation_param($this->param);
        $real_name = isset($this->param['real_name']) ? $this->param['real_name'] : '';
        $birthday = isset($this->param['birthday']) ? $this->param['birthday'] : '';
        $head_img = isset($this->param['head_img']) ? $this->param['head_img'] : '';
        $qh = isset($this->param['qh']) ? $this->param['qh'] : '';
        $phone = isset($this->param['phone']) ? $this->param['phone'] : '';
        $password = isset($this->param['password']) ? $this->param['password'] : '';
        $opassword = isset($this->param['old_password']) ? $this->param['old_password'] : '';
        $wpassword = isset($this->param['withdrawal_psd']) ? $this->param['withdrawal_psd'] : '';
        $mail = isset($this->param['mail']) ? $this->param['mail'] : '';
        $code = isset($this->param['code']) ? $this->param['code'] : '';
        $verify_code = isset($this->param['verify_code']) ? $this->param['verify_code'] : '';
        $google_code = isset($this->param['google_code']) ? $this->param['google_code'] : null;

        //额外信息
        $userExtra = [];
        if (isset($this->param['whatsapp'])) {
            $userExtra['whatsapp'] = $this->param['whatsapp'];
        }
        if (isset($this->param['telegram'])) {
            $userExtra['telegram'] = $this->param['telegram'];
        }
        if (isset($this->param['twitter'])) {
            $userExtra['twitter'] = $this->param['twitter'];
        }
        if (isset($this->param['facebook'])) {
            $userExtra['facebook'] = $this->param['facebook'];
        }


        if (empty($head_img) && empty($real_name) && empty($password) && empty($phone) && empty($mail) &&
            empty($userExtra['whatsapp']) && empty($userExtra['telegram']) &&
            empty($userExtra['twitter']) && empty($userExtra['facebook'])
        ) {
            ajax_return("请完善信息", 0);
        }

        if ($real_name && mb_strlen($real_name) > 10) {
            ajax_return('名称长度长度不能超过10');
        }

        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $upd = [];
        $user = UserModel::where(['id' => $this->uid])->find();

        if ($phone) {
            if ($verify_code) {
                //手机号验证过更改验证状态
                $redisPhoneCode = $ApiRedis->get($phone . "_code");
                if ($verify_code != $redisPhoneCode) {
                    ajax_return("手机验证码错误", 0);
                }
                $upd['phone_bind_status'] = 1;
            }
            if (UserModel::where('phone', $phone)->where('id', '<>', $this->uid)->value('id') > 0) {
                ajax_return("手机号已存在", 0);
            }
            //设置了需要绑定手机 并且要验证手机号码 验证码必传
            if ($sys['phone_check'] == 1) {
                //手机验证的
                $redisCode = $ApiRedis->get($phone . "_code");
                if (empty($verify_code)) {
                    ajax_return("请输入手机验证码", 0);
                }

                if ($verify_code != $redisCode) {
                    ajax_return("手机验证码错误", 0);
                }
                $ApiRedis->delete($phone . "_code");
            }

        }

        if ($mail) {
            if ($verify_code) {
                //邮箱验证过更改验证状态
                $redisMailCode = $ApiRedis->get($mail . "_code");
                if ($verify_code != $redisMailCode) {
                    ajax_return("邮箱验证码错误", 0);
                }
                $upd['mail_bind_status'] = 1;
            }
            //邮箱验证的
            if (UserModel::where('mail', $mail)->where('id', '<>', $this->uid)->value('id') > 0) {
                ajax_return("邮箱已存在", 0);
            }
            if ($sys['phone_check'] == 1) { //设置了需要绑定邮箱 并且要验证邮箱 验证码必传
                //邮箱验证  等待对接邮箱发送验证码
                $redisMailCode = $ApiRedis->get($mail . "_code");
                if (empty($verify_code)) {
                    ajax_return("请输入邮箱验证码", 0);
                }

                if ($verify_code != $redisMailCode) {
                    ajax_return("邮箱验证码错误", 0);
                }
                $ApiRedis->delete($mail . "_code");
            }
        }

        if (!empty($password) && $user['password']) {
            if ($user['password'] != Util::userMd5Password($opassword)) {
                ajax_return("旧密码错误", 0);
            }
        }

        if (!empty($password)) {
            if (strlen($password) < 6) {
                ajax_return("密码长度不能低于6位字符", 0);
            }
            if (!checkString($password)) {
                ajax_return("密码只能数字和字母组合", 0);
            }

            $verify_code = $verify_code ?? null;
            //开启验证码/谷歌验证后才需要验证
            if ($sys['phone_check'] == 1 || (isset($sys['user_google_status']) && $sys['user_google_status'] == 1)) { //设置了需要绑定邮箱 并且要验证邮箱 验证码必传
                MemberService::checkCode($user, $verify_code, 'login_password_check');
            }


//            //用户开启谷歌验证
//            if(isset($sys['user_google_status']) && $sys['user_google_status'] == 1) {
//                $verify_code = $google_code;
//                MemberService::checkCode($user, $verify_code, 'login_password_check');
//            }
        }

        $msg_text = '会员修改|IP:' . Util::get_ip();
        if (!empty($real_name)) {
            $upd['real_name'] = $real_name;
            $msg_text .= "|旧姓名：" . $user['real_name'] . ",新姓名：" . $real_name;
        }
        if (!empty($qh) && $user['qh'] != $qh) {
            $upd['qh'] = $qh;
            $msg_text .= "|旧区号：" . $user['qh'] . ",新区号：" . $qh;
        }
        if (!empty($phone) && $user['phone'] != $phone) {
            $upd['phone'] = $phone;
            $msg_text .= "|旧手机号：" . $user['phone'] . ",新手机号：" . $phone;
        }
        if (!empty($mail) && $user['mail'] != $mail) {
            $upd['mail'] = $mail;
            $msg_text .= "|旧邮箱：" . $user['mail'] . ",新邮箱：" . $mail;
        }
        if (!empty($birthday) && $user['birthday'] != $birthday) {
            $upd['birthday'] = $birthday;
            $msg_text .= "|旧生日：" . $user['birthday'] . ",旧生日：" . $birthday;
        }
        if (!empty($password)) {
            $upd['password'] = Util::userMd5Password($password);
            //$msg_text .= "|新登录密码：".$password;
            $msg_text .= "|新登录密码：******";
        }

        if (!empty($head_img)) {
            if (!filter_var($head_img, FILTER_VALIDATE_URL)) {
                ajax_return("头像错误", 0);
            }
            $upd['head_img'] = $head_img;
        }

        Db::startTrans();
        try {
            UserModel::where("id", $this->uid)->update($upd);
            //修改额外信息表
            $msg_text = self::updateUserExtra($this->uid, $userExtra, $msg_text);
            \app\common\model\UserAdminLogModel::add_log($this->uid, $msg_text, "会员修改");
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        return $rsps;
    }

    //修改用户额外信息表
    private static function updateUserExtra($uid, $update, $msg_text = '')
    {
        if (!$update) {
            return $msg_text;
        }
        $update['uid'] = $uid;
        //查询该用户而外表信息
        $userExtra = UserExtraModel::where('uid', '=', $uid)->find();
        $oldData = $userExtra;
        $updateLog = [];
        if ($oldData) {
            $userExtra->save($update);
        } else {
            $userExtra = UserExtraModel::create($update);
            $updateLog = $update;
        }
        if ($msg_text) {
            foreach ($update as $k => $v) {
                if ($updateLog || (!empty($v) && isset($oldData[$k]) && isset($userExtra[$k]) && $oldData[$k] != $userExtra[$k])) {
                    $msg_text .= "|旧数据（{$k}）：" . ($oldData[$k] ?? '无') . ",新数据：" . $v;
                }
            }
        }
        return $msg_text;
    }

    /**
     * 设置支付密码/修改支付密码
     * mod_paypasswd_phone_check 是否需要手机验证码 1需要 0不需要
     * @return array
     * @throws \app\common\exception\MessageException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function pay_password_run()
    {
        $this->param = Util::common_validation_param($this->param);
        $password = $this->param['password'] ?? '';
        $opassword = $this->param['opassword'] ?? '';
        $verify_code = $this->param['verify_code'] ?? '';
        $type = $this->param['type'] ?? 1;  //修改类型 1修改密码  2 忘记密码 直接重置
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $user_user = UserModel::where('id', '=', $this->uid)->find();
        if (!$password && $type == 1) {
            ajax_return('请输入6位数字密码');
        }
        if ((!is_numeric($password) || strlen($password) != 6) && $type == 1) {
            ajax_return('密码格式为6位数字');
        }
        //开启支付密码验证或者谷歌验证
        if ($sys['paypasswd_phone_check'] == 1 || (isset($sys['user_google_status']) && $sys['user_google_status'] == 1)) {
            MemberService::checkCode($user_user, $verify_code, 'withdrawal_password_check');
        }
        //如果提现原密码是空无需验证旧密码
        if (empty($user_user['withdrawal_psd'])) {
            $upd['withdrawal_psd'] = Util::winMd5Password($password);
            UserModel::where("id", $this->uid)->update($upd);
            $msg_text = "新支付密码：******";
            \app\common\model\UserAdminLogModel::add_log($this->uid, $msg_text, "会员修改");
            $rsps['code'] = 1;
            $rsps['msg'] = "操作成功";
            return $rsps;
        } else {
            if ($type == 1) {
                if ($user_user['withdrawal_psd'] != Util::winMd5Password($opassword)) {
                    ajax_return("原密码错误", 0);
                }
                $upd['withdrawal_psd'] = Util::winMd5Password($password);
                $msg_text = "支付密码修改：******";
            } else {
                //忘记密码的时候 通过验证码直接重置为空
                $upd['withdrawal_psd'] = '';
                $msg_text = "支付密码重置";
            }
            UserModel::where("id", $this->uid)->update($upd);
            \app\common\model\UserAdminLogModel::add_log($this->uid, $msg_text, "会员修改");
            $rsps['code'] = 1;
            $rsps['msg'] = "操作成功";
            return $rsps;
        }
    }

    //流水记录
    public function water_log_run()
    {
        $pagenow = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $start_time = $param['start_time'] ?? '';
        $end_time = $param['end_time'] ?? '';
        $where = [];
        $where[] = ["uid", '=', $this->uid];
        if (!empty($start_time) || !empty($end_time)) {
            if (!empty($start_time)) $where[] = ['add_time', ">=", strtotime($start_time . " 00:00:00")];
            if (!empty($end_time)) $where[] = ['add_time', "<=", strtotime($end_time . " 23:59:59")];
        }

        $data = UserBalanceMxModel::where($where)->limit((($pagenow - 1) * $limit), $limit)->field("add_time,price,type,msg")->order("id desc")->select();
        foreach ($data as $key => $value) {
            $data[$key]['add_time'] = Util::i18n_datetime2str($value['add_time']);
            $data[$key]['price'] = Util::i18n_number_format($value['price']);
            $data[$key]['type'] = $value['type'] == 1 ? '+' : '-';
            $data[$key]['msg'] = empty($value['msg']) ? '' : $this->langContent($value['msg']);
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        $rsps['total'] = UserBalanceMxModel::where($where)->count();
        return $rsps;
    }

    public function langContent($content)
    {
        $content = lang($content);
        $content = strtr($content, array(
            "充值到三方平台" => lang('充值到三方平台'),
            "下注" => lang('下注'),
            "从三方平台" => lang('从三方平台'),
            "提现" => lang('提现'),
            "平台出金" => " " . lang('平台出金'),
            "体育出金" => " " . lang('体育出金'),
            "体育入金" => " " . lang('体育入金'),
            "注单" => lang('注单') . " ",
            "结算" => " " . lang('结算'),
            "游戏结算" => lang("游戏结算"),
            "游戏" => lang("游戏"),
            "类返水" => lang("类返水"),
            "三方平台" => lang("三方平台"),
            '取消下注' => lang("取消下注"),
            '取消结算补扣' => lang("取消结算补扣"),
            '重新结算' => lang("重新结算"),
        ));
        return $content;
    }

    //用户基本信息
    public function info_run()
    {
        // 自动清理提现彩金流水限制,自动将彩金余额转入余额上方执行一次，还有流水但余额低于时不能
        MemberService::clearUserCjsavings(null, $this->uid);
        //自动将彩金转入余额，放在获取余额上方
        MemberService::updateCjUserList($this->uid);
        $user = UserModel::uinfo($this->uid, ",a.id,a.real_name,a.rec_code,a.password,a.qh,a.phone,a.phone_status,a.birthday,a.withdrawal_psd,a.mail,a.is_check_phone,a.live_fund_money,a.sc_date,a.mail_bind_status,a.phone_bind_status,c.whatsapp,c.telegram,c.twitter,c.facebook");

        $user['mail'] = $user['mail'] == 'undefined' || $user['mail'] == null ? '' : $user['mail'];
        $user['phone'] = $user['phone'] == 'undefined' || $user['phone'] == null ? '' : $user['phone'];

        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $is_withdrawal_usdt = env('is_withdrawal_usdt', 0);

        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();
        $currency_type = $currency['defa_data']['type']['val'] ?? 2; //默认货币 默认为法币

        if ($is_withdrawal_usdt == 1 && $currency_type == 2) {
            $user['usdt'] = round($user['balance'] * $currency['defa_data']['withdrawal_rate'], 2);
        } else {
            $user['usdt'] = '';
        }

        $user['valid_money'] = 0;
        $user['invalid_money'] = 0;
        $user['recharge'] = Util::i18n_number_format(UserOfflineModel::getRechargeUserAmount([['a.uid', '=', $this->uid]]));
        $user['accumulate'] = UserLevelModel::where([['id', ">", $user['level_id']]])->order('id asc')->value('recharge_amount');
        $user['withdrawal_psd'] = empty($user['withdrawal_psd']) ? false : true;
        //是否需要验证支付密码
        $user['pay_passwd_verify'] = isset($sys['pay_passwd_verify']) ? $sys['pay_passwd_verify'] : 0;
        //是否需要验证手机号修改支付密码
        $user['paypasswd_phone_check'] = isset($sys['paypasswd_phone_check']) ? $sys['paypasswd_phone_check'] : 0;
        $user['balance1'] = Util::i18n_number_format($user['balance']);
        $user['yphone'] = $user['phone'];
        //系统设置开启验证后绑定后才算已验证共三个状态（0未绑定，1已验证，2未验证）
        if (isset($sys['is_open_email_or_phone_check']) && $sys['is_open_email_or_phone_check'] == 1) {
            //按照原有状态判断
            $user['phone_bind_status'] = $user['phone_bind_status'] ?? 0;
            $user['mail_bind_status'] = $user['mail_bind_status'] ?? 0;
        } else {
            //按照原有状态判断，兼容原来的模式
            $user['phone_bind_status'] = empty($user['phone']) ? 0 : 1;
            $user['mail_bind_status'] = empty($user['mail']) ? 0 : 1;
        }
        $user['phone'] = Util::phone_sub($user['phone']);
        $user['ispass'] = empty($user['password']) ? 0 : 1;
        $today = strtotime(date("Y-m-d"), time()); //当天0点日期
        $user['terday'] = 0;
        $user['count_interest'] = 0;
        $user['mail2'] = filter_var($user['mail'], FILTER_VALIDATE_EMAIL) ? substr_replace($user['mail'], '****', 2, 4) : $user['mail'];
        $user['water'] = MemberService::getUserFinishWater($this->uid); // 游戏流水
        //判断是不是第三方注册用户
//        if ($user['lebal'] == 3) {
//
//            //第三方注册用户获取登录平台
//            $third_sys_sign = UserThirdModel::where("uid", $this->uid)->field('third_sys_sign')->find();
//            $user['third_sys_sign'] = $third_sys_sign['third_sys_sign'];
//        } else {
//            $user['third_sys_sign'] = 'sys';
//        }
        $user['third_sys_sign'] = UserThirdModel::where("uid", $this->uid)->value('id') ?? 0;
        $user['invite_url'] = isset($user['rec_code']) ? MemberService::createShareUrl($user['rec_code']) : '';
        //实名制认证状态
        $user['user_verify_status'] = UserVerifyFactory::userVerifyStatus($this->uid);
        $rsps['data'] = $user;
        $rsps['code'] = 1;
        return $rsps;
    }

    //用户基本信息
    public function info_v5_run()
    {
        // 自动清理提现彩金流水限制,自动将彩金余额转入余额上方执行一次，还有流水但余额低于时不能
        MemberService::clearUserCjsavings(null, $this->uid);
        //自动将彩金转入余额，放在获取余额上方
        MemberService::updateCjUserList($this->uid);

        $user = UserModel::uinfo($this->uid, ",a.id,a.real_name,a.rec_code,a.password,a.qh,a.phone,a.phone_status,a.birthday,a.withdrawal_psd,a.mail,a.is_check_phone,a.live_fund_money,a.sc_date,a.qh,a.phone_bind_status,a.mail_bind_status,c.whatsapp,c.telegram,c.twitter,c.facebook, a.auto_mail_notify_sta, a.admin_mail_notify_sta");

        $user['mail'] = $user['mail'] == 'undefined' || $user['mail'] == null ? '' : $user['mail'];
        $user['phone'] = $user['phone'] == 'undefined' || $user['phone'] == null ? '' : $user['phone'];

        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $is_withdrawal_usdt = env('is_withdrawal_usdt', 0);

        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();
        $currency_type = $currency['defa_data']['type']['val'] ?? 2; //默认货币 默认为法币

        if ($is_withdrawal_usdt == 1 && $currency_type == 2 && $currency) {
            $user['usdt'] = round($user['balance'] * $currency['defa_data']['withdrawal_rate'], 2);
        } else {
            $user['usdt'] = '';
        }
        $next_level = UserLevelModel::where('id', '>', $user['level_id'])->order('id asc')->find();
        $user['next_level_id'] = '';
        $user['next_level_name'] = '';
        $user['next_level_rate'] = '';
        $user['next_level_water'] = '';
        $user['next_level_recharge'] = '';
        if ($next_level) {
            $user['next_level_id'] = $next_level['id'];
            $user['next_level_name'] = $next_level['level_name'];
            $user['next_level_rate'] = UserLevelModel::getnext_level_rate($this->uid, $user['level_id'], $next_level);
            $user['next_level_water'] = $next_level['water'];
            $user['next_level_recharge'] = $next_level['recharge_amount'];
        }
        $user['valid_money'] = 0;
        $user['invalid_money'] = 0;
        $user['recharge1'] = UserOfflineModel::getRechargeUserAmount([['a.uid', '=', $this->uid]]);
        $user['recharge'] = Util::i18n_number_format($user['recharge1']);
        $user['accumulate'] = UserLevelModel::where([['id', ">", $user['level_id']]])->order('id asc')->value('recharge_amount');
//        $user['water'] = sprintf("%.2f",Db::name("third_stats_users")->where([['su_uid', '=', $this->uid]])->sum('su_bet')); // 游戏流水
        $user['water'] = MemberService::getUserTotalBet($this->uid); // 游戏总下注
        $user['withdrawal_psd'] = empty($user['withdrawal_psd']) ? false : true;
        //是否需要验证手机号修改支付密码
        //是否需要验证支付密码
        $user['pay_passwd_verify'] = isset($sys['pay_passwd_verify']) ? $sys['pay_passwd_verify'] : 0;
        //是否需要验证手机号修改支付密码
        $user['paypasswd_phone_check'] = isset($sys['paypasswd_phone_check']) ? $sys['paypasswd_phone_check'] : 0;
        $user['balance1'] = Util::i18n_number_format($user['balance']);
        $user['yphone'] = $user['phone'];
        //系统设置开启验证后绑定后才算已验证共三个状态（0未绑定，1已验证，2未验证）
        if (isset($sys['is_open_email_or_phone_check']) && $sys['is_open_email_or_phone_check'] == 1) {
            //按照原有状态判断
            $user['phone_bind_status'] = $user['phone_bind_status'] ?? 0;
            $user['mail_bind_status'] = $user['mail_bind_status'] ?? 0;
        } else {
            //按照原有状态判断，兼容原来的模式
            $user['phone_bind_status'] = empty($user['phone']) ? 0 : 1;
            $user['mail_bind_status'] = empty($user['mail']) ? 0 : 1;
        }
        $user['phone'] = Util::phone_sub($user['phone']);
        $user['ispass'] = empty($user['password']) ? 0 : 1;
        $today = strtotime(date("Y-m-d"), time()); //当天0点日期
        $user['terday'] = 0;
        $user['count_interest'] = 0;
        $user['mail2'] = hideMail($user['mail']);
        $google = UserGoogleModel::where(['uid' => $this->uid])->withoutField('google_secret')->find();
        if (!$google) $google = UserGoogleModel::inser($this->uid);
        $user['goodsle_bind'] = $google['goodsle_bind'];
        $user['login_check'] = $google['login_check'];
        $user['login_password_check'] = $google['login_password_check'];
        $user['withdrawal_password_check'] = $google['withdrawal_password_check'];
        $user['bankcard_check'] = $google['bankcard_check'];
        $user['withdrawal_check'] = $google['withdrawal_check'];
        $user['give_price'] = UserDistributionModel::where([['uid', '=', $this->uid], ['give_status', '=', 0], ['type', '=', 0], ['status', 'in', '1,2']])->sum('price');

        //邀请链接
//        $user['rec_code'] = $user['rec_code'];
        $user['invite_url'] = MemberService::createShareUrl($user['rec_code']);
        // 获取三方游戏中余额
        $user['third_balance'] = MemberService::getUserThirdsBalance($this->uid);

//        //判断是不是第三方注册用户
//        if ($user['lebal'] == 3) {
//            //第三方注册用户获取登录平台
//            $third_sys_sign = UserThirdModel::where("uid", $this->uid)->field('third_sys_sign')->find();
//            $user['third_sys_sign'] = $third_sys_sign['third_sys_sign'];
//        } else {
//            $user['third_sys_sign'] = 'sys';
//        }

        $user['third_sys_sign'] = UserThirdModel::where("uid", $this->uid)->value('id') ?? 0;

        // 自动清理提现流水限制
        MemberService::clearUserSavings($user->toArray(), $user['third_balance']);

        // 自动清理提现彩金流水限制
        MemberService::clearUserCjsavings($user->toArray());
        //实名制认证状态
        $user['user_verify_status'] = UserVerifyFactory::userVerifyStatus($this->uid);
        //流水加上指定流水
        //获取指定流水数据
        $appointSavingsData = MemberService::getUserAppointSavings($this->uid);

        $user['savings'] = $user['savings'] + ($appointSavingsData['water'] - $appointSavingsData['finish_water']);
        $rsps['data'] = $user;
        $rsps['code'] = 1;
        return $rsps;
    }

    //验证手机号
    public function check_phone_run()
    {
        $code = $this->param['code'] ?? '';
        if (empty($code)) {
            ajax_return("请输入验证码", 0);
        }
        $ApiRedis = new ApiRedis();
        $redisCode = $ApiRedis->get($this->uid . "phone_code");
        if ($code != $redisCode) {
            ajax_return("验证码错误", 0);
        }
        Db::startTrans();
        try {
            $upd['phone_status'] = 1;
            UserModel::where("id", $this->uid)->update($upd);
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        $ApiRedis->delete($this->uid . "_code");
        return $rsps;
    }

    //发送验证码 要登录
    public function send_code_run()
    {
        $phone = $this->param['phone'] ?? '';
        $qh = '86';
        if (isset($this->param['areacode'])) {
            $qh = $this->param['areacode'];
        }
        if (isset($this->param['qh'])) {
            $qh = $this->param['qh'];
        }
        if (empty($phone)) {
            ajax_return("请输入手机号", 0);
        }

        $ApiRedis = new ApiRedis();
        $sendCount = $ApiRedis->get("phoneSendCode" . $phone);
        if (empty($sendCount)) $sendCount = 0;
        $phone_ip_num = 10;
        //获取系统设置
        $sys = $ApiRedis->systemInfo();
        $mail_phone_ip_num = unserialize($sys['mail_phone_ip_num']);
        if (isset($mail_phone_ip_num['phone_open']) && $mail_phone_ip_num['phone_open'] == 1) {
            $phone_time = empty($mail_phone_ip_num['phone_time']) ? '60' : $mail_phone_ip_num['phone_time'];
            $phone_ip_num = empty($mail_phone_ip_num['phone_ip_num']) ? '5' : $mail_phone_ip_num['phone_ip_num'];
        } else {
            $phone_time = 60;
        }

        if ($sendCount >= $phone_ip_num) {
            ajax_return("该手机号码接收短信已达上限", 0);
        }

        $phone_code = $ApiRedis->get($phone . "_code");
        if (!empty($phone_code)) {
            ajax_return("验证码发送频繁，请稍后再试", 0);
        }

        $upd['phone'] = $phone;
        $upd['qh'] = $qh;//有加号就替换
        if (!SmsCode::run($qh, $phone, $phone, $phone_time)) {
            ajax_return("验证码发送失败", 0);
        }
        UserModel::where("id", $this->uid)->update($upd);
        $sendCount++;
        $ApiRedis->set("phoneSendCode" . $phone, $sendCount, strtotime(date("Y-m-d") . " 00:00:00") - time() + 86400);
        $rsps['code'] = 1;
        return $rsps;
    }

//    public function refresh_run(){
//        $ApiRedis=new ApiRedis();
//        $level_id = $ApiRedis->get("refresh_vip".$this->uid);
//        if(empty($level_id)){
//            Queue::push("User@upgrade", ['id' => $this->uid, 'money' => 0], 'upgrade');
//            $level_id = UserModel::where("id",$this->uid)->value("level_id");
//            $ApiRedis->set("refresh_vip".$this->uid, $level_id, 300);
//        } else {
//            $level_id = UserModel::where("id",$this->uid)->value("level_id");
//        }
//        $rsps['data'] = $level_id;
//        $rsps['code']=1;
//        return $rsps;
//    }

    public function vip_run()
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $type = isset($sys['vip_upgrade']) ? $sys['vip_upgrade'] : 0;
        $tg_condition = $sys['tg_condition'];
        $cache_key_prefix = "vip_condition4_{$lang}_";
        //TODO 修改排序要修改下面对应的VIP周和月领取时间
        $field = 'a.id,a.level_name,a.recharge_amount,a.image,a.min_amount,a.max_amount,a.day_limit,a.day_free_limit,a.level_gold,a.type,a.first_user,a.team_user,a.first_user_level,a.water,a.week_water,a.month_water,a.week_reward_amount,a.month_reward_amount';
        //VIP显示返回平台的游戏反水比例
        $showBackWaterTid = env('SHOW_BACK_WATER_RATE_TID', 0);
        $queryModel = (new UserLevelModel())->alias('a');
        if ($showBackWaterTid) {
            $queryModel->join('game_back_config b', 'b.gbc_viplvl=a.id');
            $field .= ',b.gbc_rate';
        }
        $vip = $queryModel->order("a.id asc")->field($field)->select();
//        $weekStartTime = mktime(0,0,0,date('m'),date('d')-date('w')+1,date('y'));
//        $weekEndTime = mktime(23,59,59,date('m'),date('d')-date('w')+7,date('y'));
//        $weekStartTime = strtotime('-1 week');
//        $weekEndTime = time();
        //月奖励类型
//        $monthStartTime = mktime(0,0,0,date('m'),1,date('Y'));
//        $monthEndTime = mktime(23,59,59,date('m'),date('t'),date('Y'));
//        $monthStartTime = strtotime('-1 month');
//        $monthEndTime = time();
        //获取用户数据
        $userDetail = UserModel::uinfo($this->uid);

        //获取最后领取的周月时间
        $weekIsReceiveVipReward = 0;
        $monthIsReceiveVipReward = 0;
        $weekRecordTime = 0;
        $monthRecordTime = 0;
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
//        $condition[] = ['level_id', '=', $userDetail['level_id']];
        $condition[] = ['reward_type', '=', UserLevelRewardModel::WEEK_REWARD_TYPE];
        $_weekRecordTime = UserLevelRewardModel::where($condition)->order('create_time desc')->value('create_time') ?: 0;
        if ($_weekRecordTime) $weekRecordTime = $_weekRecordTime;
        $condition = [];
        $condition[] = ['uid', '=', $this->uid];
//        $condition[] = ['level_id', '=', $userDetail['level_id']];
        $condition[] = ['reward_type', '=', UserLevelRewardModel::MONTH_REWARD_TYPE];
        $_monthRecordTime = UserLevelRewardModel::where($condition)->order('create_time desc')->value('create_time') ?: 0;
        if ($_monthRecordTime) $monthRecordTime = $_monthRecordTime;

        //本周时间
        $weekStartTime = Carbon::now()->startOfWeek()->timestamp;
        $weekEndTime = Carbon::now()->endOfWeek()->timestamp;
        //本月时间
        $monthStartTime = Carbon::now()->startOfMonth()->timestamp;
        $monthEndTime = Carbon::now()->endOfMonth()->timestamp;

        //获取VIP对应游戏分类反水比例
        $vipGameWaterAndRateData = UserLevelModel::getVipGameWaterAndBackRate();
        foreach ($vip as $key => $val) {
            //没有反水比例字段加上，防止前端报错
            if (!isset($val['gbc_rate'])) {
                $vip[$key]['gbc_rate'] = 0;
            }
            $vip_condition = $ApiRedis->get($cache_key_prefix . $key . '_' . $this->uid);
            if (empty($vip_condition)) {
//                $water = Db::name("third_stats_users")->where([['su_uid', '=', $this->uid]])->sum('su_bet'); // 流水
//                $water = Db::name("third_stats_users")->where([['su_uid', '=', $this->uid]])->sum('su_validwater'); // 有效流水
                //转化成时区表
                $table = \app\common\service\BetService::getThirdStatsTable();
                $water = Db::name($table['_stats_global'])->where([['su_uid', '=', $this->uid]])->sum('su_valid_bet');//及时有效流水
                $money = 0;
                //判断用户等级，如果是当前用户等级的话显示下一等级，否则显示该卡片的
                $oldLevelId = $val['id'];
                if ($userDetail->level_id == $val['id']) {
                    $val = $vip[$key + 1] ?? $val;
                }
                if ($type == 0) {
                    if ($val['type'] == 0) {
                        $money = UserOfflineModel::where([["status", 'in', "3,4"], ["uid", '=', $this->uid]])->sum("arrival_price");
                    } else {
                        $money = UserOfflineModel::where([["status", 'in', "3,4"], ["uid", '=', $this->uid]])->order("arrival_price desc")->limit(0, 1)->value("arrival_price");
                    }
                }
                if ($type == 3) {
                    $money = UserOfflineModel::where([["status", 'in', "3,4"], ["uid", '=', $this->uid]])->sum("arrival_price");
                }
                $vip_condition = [];
                if (in_array($type, [0, 3])) {
                    if ($val['recharge_amount'] > 0) {
                        if ($val['type'] == 0) {
                            $_name = $userDetail->level_id == $oldLevelId ? lang('累计充值/下一等级') : lang('累计充值');
                        } else {
                            $_name = $userDetail->level_id == $oldLevelId ? lang('单次充值/下一等级') : lang('单次充值');
                        }
                        $vip_condition[] = [
                            'key' => $_name,
                            'value' => $userDetail->level_id == $oldLevelId ? substr(Util::i18n_currency(intval($money)), 0, -3) . '/' . substr(Util::i18n_currency(intval($val['recharge_amount'])), 0, -3) : substr(Util::i18n_currency(intval($val['recharge_amount'])), 0, -3),
                            'is_finish' => $money < $val['recharge_amount'] ? false : true,
                        ];
                    }
                }
                if ($type > 0) {
                    if ($val['first_user_level'] > 0) {
                        $first_user = UserModel::where([['rec_id', '=', $this->uid], ['sc_balance', '>=', $tg_condition], ['level_id', '>=', $val['first_user_level']]])->count();
                    } else {
                        $first_user = UserModel::where([['rec_id', '=', $this->uid], ['sc_balance', '>=', $tg_condition]])->count();
                    }
                    $team_user = UserModel::where("(rec_id={$this->uid} or rec_id2={$this->uid} or rec_id3={$this->uid}) and sc_balance>={$tg_condition}")->count();
                    if ($val['first_user'] > 0) {
                        if ($val['first_user_level'] > 0) {
                            $_name = $userDetail->level_id == $oldLevelId ? lang('直推下属(VIP)/下一等级') : lang('直推下属(VIP)');
                        } else {
                            $_name = $userDetail->level_id == $oldLevelId ? lang('直推下属/下一等级') : lang('直推下属');
                        }
                        $vip_condition[] = [
                            'key' => $_name,
                            'value' => $userDetail->level_id == $oldLevelId ? $first_user . '/' . $val['first_user'] : $val['first_user'],
                            'is_finish' => $first_user < $val['first_user'] ? false : true,
                        ];
                    }
                    if ($val['team_user'] > 0) {
                        $vip_condition[] = [
                            'key' => $userDetail->level_id == $oldLevelId ? lang('团队人数/下一等级') : lang('团队人数'),
                            'value' => $userDetail->level_id == $oldLevelId ? $team_user . '/' . $val['team_user'] : $val['team_user'],
                            'is_finish' => $team_user < $val['team_user'] ? false : true,
                        ];
                    }
                }
                if ($val['water'] > 0) {
                    $vip_condition[] = [
                        'key' => $userDetail->level_id == $oldLevelId ? lang('游戏流水/下一等级') : lang('游戏流水'),
                        'value' => $userDetail->level_id == $oldLevelId ? substr(Util::i18n_currency(intval($water)), 0, -3) . '/' . substr(Util::i18n_currency(intval($val['water'])), 0, -3) : substr(Util::i18n_currency(intval($val['water'])), 0, -3),
                        'is_finish' => $water < $val['water'] ? false : true,
                    ];
                }
                $ApiRedis->set($cache_key_prefix . $key . '_' . $this->uid, json_encode($vip_condition), 600);
            } else {
                $vip_condition = json_decode($vip_condition, true);
            }
            $vip[$key]['vip_condition'] = $vip_condition;

            //判断VIP等级奖励是否领取
            $_weekIsReceiveVipReward = $this->judgeUserIsReceiveLevel($this->uid, $vip[$key], UserLevelRewardModel::WEEK_REWARD_TYPE);
            if ($_weekIsReceiveVipReward) $weekIsReceiveVipReward = $_weekIsReceiveVipReward;
//            $val['week_is_receive_vip_reward'] = $this->judgeUserIsReceiveLevel($this->uid, $val, UserLevelRewardModel::WEEK_REWARD_TYPE);
            $_monthIsReceiveVipReward = $this->judgeUserIsReceiveLevel($this->uid, $vip[$key], UserLevelRewardModel::MONTH_REWARD_TYPE);
            if ($_monthIsReceiveVipReward) $monthIsReceiveVipReward = $_monthIsReceiveVipReward;
//            $val['month_is_receive_vip_reward'] = $this->judgeUserIsReceiveLevel($this->uid, $val, UserLevelRewardModel::MONTH_REWARD_TYPE);
            //周最新领取时间
//            $condition['create_time'] = ['between', [$weekStartTime, $weekEndTime]];
//            $condition['uid'] = $this->uid;
//            $condition['level_id'] = $val['id'];
//            $condition['reward_type'] = UserLevelRewardModel::WEEK_REWARD_TYPE;

//            $condition[] = ['create_time', 'between', [$weekStartTime, $weekEndTime]];

            //判断只在当前会员等级显示（刚从VIP1升级到VIP2后，即使领取的VIP1也显示在VIP上面）
            $vip[$key]['week_is_receive_vip_reward'] = 0;
            $vip[$key]['month_is_receive_vip_reward'] = 0;
            $vip[$key]['week_last_receive_time'] = 0;
            $vip[$key]['month_last_receive_time'] = 0;
            if ($userDetail && $vip[$key]['id'] == $userDetail['level_id']) {
                $vip[$key]['week_is_receive_vip_reward'] = $weekIsReceiveVipReward;
                $vip[$key]['month_is_receive_vip_reward'] = $monthIsReceiveVipReward;
                $vip[$key]['week_last_receive_time'] = $weekRecordTime;
                $vip[$key]['month_last_receive_time'] = $monthRecordTime;
            }
            $table = BetService::getThirdStatsTable();
            //本周完成流水
            $vip[$key]['finish_week_water'] = Db::name($table["_stats_global"])
                ->where('su_dt', 'between', [date('Ymd', $weekStartTime), date('Ymd', $weekEndTime)])
                ->where([['su_uid', '=', $this->uid]])
                ->sum('su_valid_bet') ?: 0;//及时有效流水
            //本月完成流水
            $vip[$key]['finish_month_water'] = Db::name($table["_stats_global"])
                ->where('su_dt', 'between', [date('Ymd', $monthStartTime), date('Ymd', $monthEndTime)])
                ->where([['su_uid', '=', $this->uid]])
                ->sum('su_valid_bet') ?: 0;//及时有效流水

            //VIP对应游戏分类反水
            $vip[$key]['game_rate'] = $vipGameWaterAndRateData[$val['id']] ?? [];
        }


        $rsps['code'] = 1;
        $rsps['data'] = $vip;
        return $rsps;
    }

    public function vip2_run()
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $type = isset($sys['vip_upgrade']) ? $sys['vip_upgrade'] : 0;
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $tg_condition = $sys['tg_condition'];
        $cache_key_prefix = "vip_condition3_{$lang}_";
        $field = 'a.id,a.level_name,a.image,a.recharge_amount,a.min_amount,a.max_amount,a.day_limit,a.level_gold,a.type,a.first_user,a.team_user,a.first_user_level,a.water,a.week_water,a.month_water,a.week_reward_amount,a.month_reward_amount';
        //VIP显示返回平台的游戏反水比例
        $showBackWaterTid = env('SHOW_BACK_WATER_RATE_TID', 0);
        $queryModel = (new UserLevelModel())->alias('a');
        if ($showBackWaterTid) {
            $queryModel->join('game_back_config b', 'b.gbc_viplvl=a.id');
            $field .= ',b.gbc_rate';
        }
        $vip = $queryModel->order("a.id asc")->field($field)->select();

        //获取VIP对应游戏分类反水比例
        $vipGameWaterAndRateData = UserLevelModel::getVipGameWaterAndBackRate();
        foreach ($vip as $key => $val) {
            //没有反水比例字段加上，防止前端报错
            if (!isset($val['gbc_rate'])) {
                $vip[$key]['gbc_rate'] = 0;
            }
            $vip_condition = $ApiRedis->get($cache_key_prefix . $key);
            if (empty($vip_condition)) {
                $vip_condition = [];
                if (in_array($type, [0, 3])) {
                    if ($val['recharge_amount'] > 0) {
                        $vip_condition[] = [
                            'key' => $val['type'] == 0 ? lang('累计充值金额') : lang('单次充值金额'),
                            'value' => Util::i18n_currency($val['recharge_amount']),
                        ];
                    }
                }
                if ($type > 0) {
                    if ($val['first_user'] > 0) {
                        $vip_condition[] = [
                            'key' => $val['first_user_level'] > 0 ? lang('直推下属') . '(VIP)' : lang('直推下属'),
                            'value' => $val['first_user'],
                        ];
                    }
                    if ($val['team_user'] > 0) {
                        $vip_condition[] = [
                            'key' => lang('团队人数'),
                            'value' => $val['team_user'],
                        ];
                    }
                }
                if ($val['water'] > 0) {
                    $vip_condition[] = [
                        'key' => lang('游戏流水'),
                        'value' => Util::i18n_currency($val['water']),
                    ];
                }
                $ApiRedis->set($cache_key_prefix . $key, json_encode($vip_condition), 600);
            } else {
                $vip_condition = json_decode($vip_condition, true);
            }
            $val['vip_condition'] = $vip_condition;
            //本周完成流水
            $val['finish_week_water'] = 0;
            //本月完成流水
            $val['finish_month_water'] = 0;

            //VIP对应游戏分类反水
            $vip[$key]['game_rate'] = $vipGameWaterAndRateData[$val['id']] ?? [];
        }

        $rsps['code'] = 1;
        $rsps['data'] = $vip;
        return $rsps;
    }

    public function troni_run()
    {
        $param = $this->param;
        $address = isset($param['address']) ? $param['address'] : "";
        $money = isset($param['money']) ? $param['money'] : 0;
        if (!empty($address)) {
            $info = \app\common\model\TroniModel::where(['address' => $address, 'uid' => $this->uid])->find();
            if (empty($info)) {
                $add = [];
                $add['uid'] = $this->uid;
                $add['address'] = $address;
                $add['money'] = $money;
                $add['add_time'] = time();
                $add['update_time'] = time();
                \app\common\model\TroniModel::insertGetId($add);
            } else {
                \app\common\model\TroniModel::where(['id' => $info['id']])->update(['money' => $money, 'update_time' => time()]);
            }

        }
        $rsps['code'] = 1;
        return $rsps;
    }


    public function get_balance_run()
    {
        $data = UserModel::where(['id' => $this->uid])->field('balance')->find();
        $data['balance1'] = Util::i18n_number_format($data['balance']);
        $data['third_balance'] = Db::name("user_thirds")->where(['ut_uid' => $this->uid])->sum("ut_balance");
        $code = 1;
        return compact('data', 'code');
    }

    public function login_run()
    {
        $pagenow = $param['page'] ?? 1;
        $limit = $param['limit'] ?? 10;
        $list = UserLoginModel::where(['uid' => $this->uid])->limit((($pagenow - 1) * $limit), $limit)
            ->field("login_ip,login_time,device")
            ->order("id desc")
            ->select();

        foreach ($list as $key => &$value) {
            $value['login_time'] = Util::i18n_datetime3str($value['login_time']);
        }

        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function user_google_run()
    {
        $Authenticator = new Authenticator();
        $ApiRedis = new ApiRedis();
        $qrCodeUrl = $ApiRedis->get('user_google_url_' . $this->uid);
        $secret = $ApiRedis->get('user_google_' . $this->uid);
        if (!$qrCodeUrl) {
            $secret = $Authenticator->createSecret();
            $username = UserModel::where(['id' => $this->uid])->field('username,mail,phone,rec_code')->find();
            $name = $username['rec_code'];
            if ($username['phone']) {
                $name = $username['phone'];
            } else if ($username['mail']) {
                $name = $username['mail'];
            }
            $website_name = $ApiRedis->systemInfo("website_name");
            $qrCodeUrl = $Authenticator->getQRCodeGoogleUrl($name, $secret, $website_name);
            $ApiRedis->set('user_google_' . $this->uid, $secret, 600);
            $ApiRedis->set('user_google_url_' . $this->uid, $qrCodeUrl, 600);
        }
        return ['code' => 1, 'data' => $qrCodeUrl, 'secret' => $secret];
    }

    public function bind_user_google_run()
    {
        $param = Request::param();
        $code = $param['code'] ?? '';
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        if (!isset($sys['user_google_status']) || $sys['user_google_status'] == 0) {
            ajax_return("Google验证未开启", 0);
        }
        if (!$code) {
            ajax_return("请输入Google验证码", 0);
        }
        $google = UserGoogleModel::where(['uid' => $this->uid])->field('goodsle_bind,google_secret')->find();
        if (!$google) $google = UserGoogleModel::inser($this->uid);
        if ($google['goodsle_bind'] == 1 || !empty($google['google_secret'])) {
            ajax_return("Google验证已被绑定", 0);
        }
        $Authenticator = new Authenticator();
        $google_secret = $ApiRedis->get('user_google_' . $this->uid);
        $checkResult = $Authenticator->verifyCode($google_secret, $code, 1);
        if ($checkResult) {
            $data['google_secret'] = $google_secret;
            $data['goodsle_bind'] = 1;
            UserGoogleModel::where("uid", $this->uid)->update($data);
            $ApiRedis->delete('user_google_' . $this->uid);
            $ApiRedis->delete('user_google_url_' . $this->uid);
            return ['code' => 1, 'msg' => '绑定成功'];
        } else {
            ajax_return("验证失败", 0);
        }
    }

    public function google_check_run()
    {
        $param = Request::param();
        $checks = $param['checks'] ?? [];
        $code = $param['code'] ?? '';
        if (empty($checks) || empty($code)) {
            ajax_return("数据格式错误", 0);
        }
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        if (!isset($sys['user_google_status']) || $sys['user_google_status'] == 0) {
            ajax_return("Google验证未开启", 0);
        }
        $fields = [
            'login_check', 'login_password_check', 'withdrawal_password_check',
            'bankcard_check', 'withdrawal_check'
        ];
        $update = [];
        foreach ($fields as $field) {
            if (isset($checks[$field])) {
                $update[$field] = $checks[$field] == 1 ? 1 : 0;
            }
        }
        if (empty($update)) {
            ajax_return("数据格式错误", 0);
        }
        $userGoogleInfo = UserGoogleModel::where('uid', $this->uid)->find();
        if (empty($userGoogleInfo)) {
            ajax_return("请先开启Google验证", 0);
        }

        $verify = UserGoogleModel::verifyCode($userGoogleInfo['google_secret'], $code);
        if (!$verify) {
            ajax_return('Google验证失败', 0);
        }
        $res = UserGoogleModel::where('uid', $this->uid)->update($update);
        if ($res !== false) {
            return ['code' => 1, 'msg' => '操作成功'];
        } else {
            return ['code' => 0, 'msg' => '操作失败'];
        }
    }

    //日志
    public function log_run()
    {
        $infos = $this->param;
        foreach ($infos as $info) {
            if (!isset($info['type']) || !isset($info['data']) || !isset($info['datetime'])) {
                continue;
            }
            $userLogListLogKey = 'user_log_list';#用户日志列表缓存key
            if (
                !isset($info['type']) || !$info['type'] ||
                !isset($info['data']) || !$info['data'] ||
                !isset($info['datetime']) || !$info['datetime']
            ) {
                continue;
            }
            //记录到队列中
//            Cache::store('redis')->rpush($userLogListLogKey, json_encode([
//                'uid' => $this->uid,
//                'type' => $info['type'] ?? 'none',
//                'data' => $info['data'] ?? '',
//                'datetime' => $info['datetime']
//            ]));
        }

        return ['code' => 1, 'msg' => '操作成功'];
    }

    //获取用户推广人数
    public static function getUserInvitePerson($userId, $startTime = 0, $endTime = 0)
    {
        $ApiRedis = new ApiRedis();
        $sys = $ApiRedis->systemInfo();
        $validCondition = false;#有效流水条件
        if (isset($sys['tg_condition_valid_bet']) && $sys['tg_condition_valid_bet'] > 0) {
            $validCondition = true;
        }
        $people = 0;
        if ($sys['tg_condition'] > 0) {
            $sql = UserModel::where([['rec_id', "=", $userId]])->field("id")->buildSql();
            $query = UserModel::alias("a")->join("dq_user_offline b", "a.id=b.uid");
            $ls_users = [];
            $field = 'a.id,sum(arrival_price) as sum';
            if ($validCondition) {
                //设置有效流水条件
                $fieldls = 'a.id,sum(su.su_valid_bet) as valid_bet';
                //转化成时区表
                $table = \app\common\service\BetService::getThirdStatsTable();
                $queryls = UserModel::alias("a")->join($table['stats_global'] . " su", "a.id=su.su_uid");
                $queryls = $queryls->field($fieldls)->where("a.id in (" . $sql . ")");

                //开始时间
                if ($startTime > 0) {
                    $queryls = $queryls->where("a.add_time", ">=", $startTime);
                }
                //结束时间
                if ($endTime > 0) {
                    $queryls = $queryls->where("a.add_time", "<=", $endTime);
                }
                $recLs = $queryls->group('su.su_uid')->select();
                foreach ($recLs as $val) {
                    $ls_users[$val['id']] = $val['valid_bet'];
                }
            }
            $query = $query->field($field)->where("a.id in (" . $sql . ")")->where("b.status in(3,4)");
            //开始时间
            if ($startTime > 0) {
                $query = $query->where("a.add_time", ">=", $startTime);
            }
            //结束时间
            if ($endTime > 0) {
                $query = $query->where("a.add_time", "<=", $endTime);
            }
            $recU = $query->group('b.uid')->select();
            if ($validCondition) {
                foreach ($recU as $v) {
                    if ($v['sum'] >= $sys['tg_condition'] && (isset($ls_users[$v['id']]) && $ls_users[$v['id']] >= $sys['tg_condition_valid_bet'])) {
                        $people++;
                    }
                }
            } else {
                foreach ($recU as $v) {
                    if ($v['sum'] >= $sys['tg_condition']) {
                        $people++;
                    }
                }
            }

        } else {
            $query = UserModel::alias("a");
            $field = 'a.id';
            if ($validCondition) {
                //设置有效流水条件
                $field = $field . ',sum(su.su_valid_bet) as valid_bet';
                $table = \app\common\service\BetService::getThirdStatsTable();
                $query = $query->join($table['stats_global'] . " su", "a.id=su.su_uid");
            }

            $recU = $query->field($field)->where(["a.rec_id" => $userId])->select();
            if ($validCondition) {
                foreach ($recU as $v) {
                    if ($v['valid_bet'] >= $sys['tg_condition_valid_bet']) {
                        $people++;
                    }
                }
            } else {
                foreach ($recU as $v) {
                    $people++;
                }
            }
        }
        return $people;
    }

    //领取用户等级奖励
    public function receiveLevelReward_run($params, $uid = 0)
    {
        if (!$uid) {
            $uid = $this->uid;
        }
        try {
            validate(UserLevelValidate::class)->scene('receive')->check($params);
        } catch (ValidateException $e) {
            return ['code' => 0, 'msg' => $e->getError()];
        }
        //获取用户数据
        $userDetail = UserModel::uinfo($uid, ',b.week_water,b.month_water');
        if ($userDetail->level_id != $params['level_id']) {
            return ['code' => 0, 'msg' => '等级不足，无法领取'];
        }
        //获取等级数据
        $levelDetail = UserLevelModel::where('id', '=', $params['level_id'])->find();
        if (!$levelDetail) {
            return ['code' => 0, 'msg' => '该会员等级不存在'];
        }
        $table = \app\common\service\BetService::getThirdStatsTable();
        //判断奖励金额
        if ($params['reward_type'] == UserLevelRewardModel::WEEK_REWARD_TYPE) {
            $firstDay = date('Ymd', strtotime('monday this week'));
            $lastDay = date('Ymd');
            $water = Db::name($table["_stats_global"])->where('su_dt', 'between', [$firstDay, $lastDay])->where([['su_uid', '=', $uid]])->sum('su_valid_bet');//及时有效流水
            if ($water >= $userDetail->week_water) {
                //符合流水要求
                $amount = $levelDetail->week_reward_amount;
            } else {
                $amount = 0;
            }
        } elseif ($params['reward_type'] == UserLevelRewardModel::MONTH_REWARD_TYPE) {
            $firstDay = date('Ym' . "01");
            $lastDay = date('Ymd');
            $water = Db::name($table["_stats_global"])->where('su_dt', 'between', [$firstDay, $lastDay])->where([['su_uid', '=', $uid]])->sum('su_valid_bet');//及时有效流水
            if ($water >= $userDetail->month_water) {
                //符合流水要求
                $amount = $levelDetail->month_reward_amount;
            } else {
                $amount = 0;
            }
        } else {
            $amount = 0;
        }
        if ($amount <= 0) {
            return ['code' => 0, 'msg' => '无奖金金额，无需领取'];
        }
        //判断本次奖励是否领取
        if ($this->judgeUserIsReceiveLevel($uid, $levelDetail, $params['reward_type'])) {
            return ['code' => 0, 'msg' => '该奖励已领取，无需重复领取'];
        }

        //执行领取奖励
        return $this->handleLevelReceiveReward($userDetail, $levelDetail, $params['reward_type']);
    }

    //判断会员是否领取当前等级
    public function judgeUserIsReceiveLevel($uid, $levelDetail, $rewardType = UserLevelRewardModel::WEEK_REWARD_TYPE)
    {
        $levelId = $levelDetail['id'];
        $apiRedis = new ApiRedis();
        $redisKey = 'judge_user_is_receive_level_reward_v2_' . $rewardType . '_' . $uid . '_' . $levelId;
        //从缓存判断是否存在
        if ($apiRedis->has($redisKey)) {
//            return $apiRedis->get($redisKey);
        }
        $time = time();
        //判断当前类型是什么 TODO 与前端约定周为加7天，月为加30天
        if ($rewardType == UserLevelRewardModel::WEEK_REWARD_TYPE) {
            //周奖励类型
            #判断是否是本周内领取的
            $startTime = $time - (86400 * 7);
            $endTime = $time;
        } elseif ($rewardType == UserLevelRewardModel::MONTH_REWARD_TYPE) {
            //月奖励类型
            $startTime = $time - (86400 * 30);
            $endTime = $time;
        } else {
            ajax_return('奖励类型错误');
        }
        //缓存中没有就从数据库中最新的一条判断
        $condition = [];
        $condition[] = ['level_id', '=', $levelId];
        $condition[] = ['uid', '=', $uid];
        $condition[] = ['create_time', 'between', [$startTime, $endTime]];
        $condition[] = ['reward_type', '=', $rewardType];
        $levelRewardDetail = UserLevelRewardModel::where($condition)->order('create_time desc')->find();
        if (!$levelRewardDetail) {
            //设置未领取缓存
            $apiRedis->set($redisKey, 0, 86400 * 3);
            return 0;
        }
        //设置缓存时间为当前时间到本周/本月结束时间
        $expireTime = $endTime - $startTime - 5;
        $apiRedis->set($redisKey, 1, $expireTime);
        return 1;
    }

    //执行等级领取奖励
    public function handleLevelReceiveReward($userDetail, $levelDetail, $rewardType = UserLevelRewardModel::WEEK_REWARD_TYPE)
    {
        //获取默认币种
        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();
        $currency = $currency['defa_data']['currency_code'];
        $amount = 0;
        $time = time();
        if ($rewardType == UserLevelRewardModel::WEEK_REWARD_TYPE) {
            $msg = 'VIP周权益已到账';
            $endTime = $time + (7 * 86400);
            $amount = $levelDetail->week_reward_amount;
        } elseif ($rewardType == UserLevelRewardModel::MONTH_REWARD_TYPE) {
            $msg = 'VIP月权益已到账';
            $endTime = $time + (30 * 86400);
            $amount = $levelDetail->month_reward_amount;
        } else {
            ajax_return('奖励类型不存在');
        }
        Db::startTrans();
        try {
            //将之前的的记录禁止领取
            UserLevelRewardModel::update([
                'status' => 0,
            ], [
                'level_id' => $levelDetail->id,
                'uid' => $userDetail->id
            ]);
            //生成领取记录
            $result = UserLevelRewardModel::create([
                'level_id' => $levelDetail->id,
                'level_data' => json_encode($levelDetail),
                'uid' => $userDetail->id,
                'reward_type' => $rewardType,
                'reward_amount' => $amount,
                'status' => 1,
            ]);
            if (!$result) {
                Db::rollback();
                ajax_return('系统错误');
            }
            //余额奖励
            UserModel::cz_balance($userDetail->id, $amount, "+", true, "等级奖励", 12, $levelDetail->savings, 1, $result->id, true, 101);
            $msgContent = lang($msg . '. 奖励金额: {:price} {:currency}');
            $msgData = ['price' => $amount, 'currency' => $currency];
            $lang = Lang::getLangSet();
            UserMsgModel::add_msg($this->uid, '等级奖励', $msgContent, 17, $result->id, $msgData, $lang);

            //设置已领取缓存
            $apiRedis = new ApiRedis();
            $redisKey = 'judge_user_is_receive_level_reward_v2_' . $rewardType . '_' . $userDetail->id . '_' . $levelDetail->id;
            $apiRedis->set($redisKey, 1, $endTime - time());
            Db::commit();
            return ['code' => 1, 'msg' => '领取成功'];
        } catch (\Exception $e) {
            Db::rollback();
            ajax_return($e->getMessage() . '系统错误');
        }
    }

    public function getWsToken()
    {
        $user = UserModel::where('id', $this->uid)->field("id,usertokenkey")->find();
        if (!empty($user)) {
            $uid = MessageService::registerMsgUser($user['id'], $user['usertokenkey'], MessageService::USER_GROUP);
        } else {
            ajax_return("用户信息错误");
        }

        return [
            'code' => 1,
            'data' => [
                'token' => $user['usertokenkey'],
                'uid' => $uid,
                'WebSocket' => env('websocket_url', '')
            ]
        ];
    }

    public function give_run()
    {
        $ApiRedis = new ApiRedis();
        if (!$ApiRedis->lock("give_run" . $this->uid)) {
            ajax_return("请勿频繁访问", 0);
        }
        $info = UserDistributionModel::where([['uid', '=', $this->uid], ['give_status', '=', 0], ['type', '=', 0], ['status', 'in', '1,2']])->field('id,uid,price,water')->find();
        if (!$info) {
            ajax_return("暂无赠金可领取", 0);
        }
        $water = $info['price'] * $info['water'];
        $price = $info['price'];
        if ($price <= 0) {
            ajax_return("暂无赠金可领取", 0);
        }
        Db::startTrans();
        try {
            UserModel::cz_balance($this->uid, $price, "+", true, '领取赠金', 37, $water, 1, $info['id'], true, 101);
            $info->give_status = 1;
            $info->give_time = time();
            $info->save();
            // 提交事务
            Db::commit();
            $rsps['code'] = 1;
        } catch (\Throwable $e) {
            Db::rollback();
            $rsps['code'] = 0;
            $rsps['msg'] = $e->getMessage();
        }
        $ApiRedis->unlock($this->uid);//释放锁
        return $rsps;
    }

    //余额明细
    public function moneyRecord()
    {
        $page = $this->param['page'] ?? 1;
        $limit = $this->param['limit'] ?? 10;
        $type = !isset($this->param['type']) || $this->param['type'] === '' ? 'all' : $this->param['type'];
        $startTime = $this->param['start_time'] ?? '';
        $endTime = $this->param['end_time'] ?? '';
        $id = $this->param['id'] ?? '';
        $walletType = $this->param['wallet_type'] ?? '';#钱包类型（1彩金钱包，0真金钱包）
        $condition = [];
        if ($startTime && $endTime) {
            //又开始和结束时间
            $condition[] = ['add_time', 'between', [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')]];
        } elseif ($startTime) {
            //只有开始时间
            $condition[] = ['add_time', '>=', strtotime($startTime . ' 00:00:00')];
        } elseif ($endTime) {
            //只有结束时间
            $condition[] = ['add_time', '<=', strtotime($endTime . ' 23:59:59')];
        }
        //id筛选,为性能考虑，第二页开始多加一个id筛选
        if ($id > 0) {
            $condition[] = ['id', '<', $id];
        }
        $condition[] = ['uid', '=', $this->uid];
        //筛选大于0的金额
        $condition[] = ['price', '>', 0];
        //筛选类型
        $condition1 = UserBalanceMxService::getTypeConditionByKey($type);
        //根据钱包类型来控制查询哪张表
        $model = new UserBalanceMxModel();
        if ($walletType == 1) {
            $model = new UserCjBalanceMxModel();
        }
        $field = 'id,new_price,price,type,add_time,msg';
        $list = $model->where($condition)
            ->where($condition1)->field($field)
            ->page($page, $limit)
            ->order('id desc')->select();
        $total = $model->where($condition)
            ->where($condition1)->count();
        foreach ($list as &$v) {
            $v['operation'] = $v['type'];
            $v['type'] = UserBalanceMxService::getTypeByRemarks($v['msg']);
            $v['child_type'] = UserBalanceMxService::getChildTypeByRemarks($v['msg']);
            $v['add_time'] = Util::i18n_datetime2str($v['add_time']);
            $v['new_price'] = Util::i18n_currency($v['new_price']);
            $v['price'] = Util::i18n_currency($v['price']);
            //删除备注
            unset($v['msg']);
        }
        $rsps['data'] = $list;
        $rsps['total'] = $total;
        $rsps['code'] = 1;
        $rsps['type'] = UserBalanceMxService::getTypeList();
        return $rsps;
    }

    //邀请链接
    public function inviteUrl()
    {
        $shareCode = $this->param['share_code'] ?? '';

        //是否自动域名
        $autoDomain = env('AUTO_SHARE_URL', 0);
        $domain = getAppUrl(false);
        if ($autoDomain == 1) {
            $protocol = env('AUTO_SHARE_URL_PROTOCOL', 'https');
            $host = $_SERVER['HTTP_HOST'];
            $webDomain = $protocol . '://' . $host;#https://testweb.lottogf110.com
            $webDomainArr = explode('.', $webDomain);
            if (count($webDomainArr) == 2) {
                //顶级域名
                $_agentDomain = $webDomainArr[0] . '.' . $webDomainArr[1];
                $_agentDomain = str_replace('https://', '', $_agentDomain);
                $_agentDomain = str_replace('http://', '', $_agentDomain);
            } elseif (count($webDomainArr) == 3) {
                //子域名
                $_agentDomain = $webDomainArr[1] . '.' . $webDomainArr[2];
            } else {
                $_agentDomain = -1;
            }
            if ($_agentDomain != -1) {
                $prefix = env('AUTO_API_URL_PREFIX', '');
                if ($prefix) {
                    $domain = $protocol . '://' . $prefix . '.' . $_agentDomain;
                } else {
                    $domain = $protocol . '://' . $autoDomain;
                }
            }
        }
        $url = $domain . "/#/?share_code=" . $shareCode . '&modal=LoginRegistration&data=tab-1';
        // 进行页面跳转到新的 URL
        header("Location: " . $url);
        exit; // 确保在跳转后停止脚本执行
    }

    //获取用户默认头像列表
    public function getDefaultHeadImg()
    {
//        $list = Utils::getDefaultHeadImg();
        $map = [
            'status' => 1
        ];
        $list = DefaultHeadModel::where($map)->field('id,name,url')->select();
        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }

    //流水统计
    public function getUserWaterStatistics()
    {
        $startDate = $this->param['start_date'] ?? Carbon::now()->addDay('-7')->toDateString();
        $endDate = $this->param['end_date'] ?? Carbon::now()->toDateString();
        $startTime = strtotime($startDate);
        $endTime = strtotime($endDate);
        $arr = [];
        for ($i = $endTime; $i > $startTime; $i -= 86400) {
            $arr[] = MemberService::getDayWaterStatistics($this->uid, $i);
        }
        return ['data' => $arr, 'code' => 1];
    }


    //用户邮件通知状态调整
    public function check_email_notify_status()
    {
        $param = Request::param();
        $type = $param['type'] ?? ''; //修改哪个类型的状态
        $status = $param['status'] ?? '';// 1开启  2关闭
//        auto_mail_notify_sta  admin_mail_notify_sta
        if (empty($type) || empty($status)) {
            ajax_return("数据格式错误", 0);
        }
        $arr = ['auto_mail_notify_sta', 'admin_mail_notify_sta'];
        if (!in_array($type, $arr)) {
            ajax_return("数据格式错误", 0);
        }
        $upd = [];
        $upd[$type] = $status;
        $r = UserModel::where(['id' => $this->uid])->update($upd);
        return ['data' => $r, 'code' => 1];
    }
}