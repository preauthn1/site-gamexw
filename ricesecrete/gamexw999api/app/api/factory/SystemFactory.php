<?php

namespace app\api\factory;

use app\common\lib\TokenCheck;
use app\common\lib\Util;
use app\common\model\GameSetModel;
use app\common\model\MirrorSiteModel;
use app\common\model\SystemLangModel;
use app\common\model\SystemUpdModel;
use app\common\model\BangdanModel;
use app\common\model\UserAgentConfigModel;
use app\common\model\UserModel;
use app\common\model\BannerModel;
use app\common\model\LangModel;
use app\common\model\LangItemsModel;
use app\common\model\LangItemDetailModel;
use app\common\model\AgentSettingModel;
use app\common\model\RuleModel;
use app\common\redis\CommomRedis;
use app\common\service\AgentService;
use app\common\service\CodeService;
use app\common\service\MemberService;
use app\common\service\SystemService;
use app\common\service\WalletService;
use app\common\service\WebSiteConfigService;
use app\model\SysSets;
use think\facade\Request;
use app\common\lib\SmsCode;
use app\common\lib\EmailCode;
use app\api\redis\ApiRedis;
use think\facade\Config;

class SystemFactory
{
    private $param;//请求参数
    private $uid;//用户id

    //验证
    public function __construct($uid = 0)
    {
        $this->param = Request::param();
        $this->uid = $uid;
    }

    public function param_run()
    {
        $sys = (new ApiRedis())->systemInfo();
        $langs = LangModel::where(['status' => 1, 'is_default' => 1])->order('id asc')->find();
        $data = [
            'promotion_code' => $sys['promotion_code'],
            'phone_check' => $sys['phone_check'],
            'code_check' => $sys['code_check'],
            'lang' => $langs['lang'],
            'icon' => $langs['flag'],
            'phone_bind' => $sys['phone_bind'],
        ];

        $idc = Config::get('custom.idc');
        $idc_default = $idc[$langs['lang']];
        $idc_arr = [];
        $area_code = empty($sys['area_code']) ? [] : explode(',', $sys['area_code']);
        if (empty($area_code)) {
            foreach ($idc as $val) {
                $idc_arr[] = ['value' => $val, 'label' => $val];
            }
        } else {
            foreach ($area_code as $val2) {
                $idc_arr[] = ['value' => $val2, 'label' => $val2];
            }
        }

        $rsps['idc_default'] = $idc_default;
        $rsps['idc'] = $idc_arr;
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //用户信息
    public function info_run()
    {
        return self::info_static();
        return $rsps;
    }

    //静态化
    public static function info_static()
    {
        $ApiRedis = new ApiRedis();
        $CommomRedis = new CommomRedis();
        $sys = $ApiRedis->systemInfo();
        $data = [
            'android_download_url' => '', 'ios_download_url' => '',
            'pc_service_url' => '', 'mobile_service_url' => '', 'web_url' => '',
            'service_charge' => 0, 'app_version' => '0', 'promotion_code' => 0,
            'phone_check' => 0, 'code_check' => 0,
            'transaction_charge' => 0, 'phone_bind' => 3, 'revoke_order' => 0,
            'quick_withdrawal' => [], 'usdt_quick_withdrawal' => [], 'quick_recharge' => [],
            'usdt_quick_recharge' => [], 'detail_notes' => [],
            'saba_check' => 0,
            'thirdLoginStatus' => env('is_third_open', 0),
            'shabaStatus' => env('is_shaba_open', 0),
            'turntableStatus' => env('is_turntable_open', 0),
            'flyerStatus' => env('is_flyer_open', 0),
            //开启修改提现密码功能，默认1开启
            'update_wpassword' => env('is_update_wpassword_open', 1),
            'withdrawal_pwd_check' => 1,
            'withdrawal_check_type' => 4,
            'withdrawal_status' => 0,
            'bind_card_status' => 0,
            'card_number' => 0,
            'force_pwd' => 0,
            'test_withdraw' => 0,
            'user_google_status' => 0,
            'user_verify_status' => 0,//是否实名制 0否 1是
            'is_reg_type' => 0,
            'start_page_status' => 0,#启动页状态
            'mail_phone_ip_num' => [],#邮箱/号码验证码相关设置
            'tg_condition_valid_bet' => 0,#推广条件有效流水
            'tg_condition' => 0,#推广条件充值金额
            'account_first_check' => env('account_first_check', 'email'),#优先验证类型
        ];
        foreach ($sys as $key => $val) {
            if (isset($data[$key])) {
                if (in_array($key, ['quick_withdrawal', 'usdt_quick_withdrawal', 'quick_recharge', 'usdt_quick_recharge'])) {
                    $val = quempty(explode(',', $val));
                } elseif (in_array($key, ['detail_notes', 'mail_phone_ip_num'])) {
                    $val = unserialize($val);
                } elseif (in_array($key, ['pc_service_url', 'mobile_service_url', 'android_download_url', 'ios_download_url'])) {
                    $val = html_entity_decode($val);
                }
                $data[$key] = $val;
            }
        }
        if ($data['shabaStatus'] <> 0) {
            $data['shabaStatus'] = isset($sys['saba_check']) ? intval($sys['saba_check']) : 0;
        }
        $data['is_agent_reward'] = AgentSettingModel::where([['stratum_bonus', ">", 0]])->count();
        $langs = $CommomRedis->getLangsBebData();
        $nation = $CommomRedis->getNation();
        $locale = "CN";
        $locale_flag = "";
        $idc_arr = $lang_arr = [];
        $idc = Config::get('custom.idc');
        $idc_default = "86";
        foreach ($langs as $val) {
            if ($val['is_default'] == 1) {
                $locale = $val['lang'];
                $locale_flag = $val['flag'];
                $idc_default = $idc[$val['lang']];
            }
            $lang_arr[] = ['icon' => $val['flag'], 'value' => $val['lang'], 'label' => $val['lang']];
        }
        $area_code = empty($sys['area_code']) ? [] : explode(',', $sys['area_code']);
        if (empty($area_code)) {
            foreach ($idc as $val) {
                $idc_arr[] = ['value' => $val, 'label' => $val];
            }
        } else {
            foreach ($area_code as $val2) {
                $idc_arr[] = ['value' => $val2, 'label' => $val2];
            }
        }

        $data['currency'] = Util::getCurrencyDefaultSymbol();
        $data['favicon'] = lang('favicon');
        $data['web_name'] = lang('web_name');
        $data['register_first_account_type'] = env('REGISTER_FIRST_ACCOUNT_TYPE', 'email');
        $idc_arr = unique_array_key($idc_arr, 'value');
        $rsps['data'] = $data;
        if (!empty($idc_arr)) {
            //如果配置区号 取配置的第一个
            $idc_default = $idc_arr[0]['value'];
        }
        $rsps['idc_default'] = $idc_default;
        $rsps['locale'] = $locale;
        $rsps['locale_flag'] = $locale_flag;
        $rsps['lang'] = self::getLangS($langs);
        $rsps['alllang'] = $lang_arr;
        $rsps['nation'] = $nation;
        $rsps['time_zone'] = $sys['time_zone'] ?? env('DEFAULT_TIMEZONE', 'Asia/Shanghai');#时区
        $rsps['idc'] = array_values($idc_arr);
        $rsps['mail_phone_reg_rule'] = [
            'phone_rule' => $sys['phone_rule'],
            'phone_num_rule' => $sys['phone_num_rule']
        ];
        //当前国家对应的金额格式
        $rsps['amount_number_data'] = Util::i18n_number_format(100, true);
        $rsps['amount_currency_data'] = Util::i18n_currency(100, true);
        //代理后台地址
        $rsps['agent_admin_url'] = env('AGENT_ADMIN_URL');
        $rsps['amount_decimal_point'] = env('AMOUNT_DECIMAL_POINT', 2);
        $walletSystemData = WalletService::getWalletData();
        $rsps['wallet_type'] = $walletSystemData['wallet_type'] ?? 0;
        //站点维护
        $temp = SystemUpdModel::where(['status' => 1])->find();
        $rsps['maintain'] = empty($temp) ? 0 : 1;//0未维护，1正在维护
        $rsps['code'] = 1;
        return $rsps;
    }

    //获取新的配置类目
    public function get_new_info()
    {
        $CommomRedis = new CommomRedis();
        $langs = $CommomRedis->getLangsBebData();
        //根据域名获取翻译
        //如果登录就获取用户uid
        $uid = 0;
        $header = request()->header();
        try {
            $token = TokenCheck::run($header);
            if ($token) {
                $uid = $token->uid;
            }
        } catch (\Exception $e) {
        }
        //从代理后台获取站点配置和语言包
        $siteConfig = WebSiteConfigService::getWebsiteConfigSetLang($uid);
        //logo相关没有从总后台配置取
        $sys = (new ApiRedis())->systemInfo();
        foreach ($siteConfig as $k => $v) {
            if ($v === '' && isset($sys[$k]) && $sys[$k]) {
                $siteConfig[$k] = $sys[$k];
            }
        }

        $lang = WebSiteConfigService::getLangDomain($langs);
        //重新从后台获取前端开关设置
        $condition1 = AgentService::getAgentWhere('agent_ids');
        foreach (array_keys($lang) as $v) {
            $_lang = SystemLangModel::where($condition1)->where('lang', '=', $v)->select();
            foreach ($_lang as $_v) {
                if (is_array($lang[$v])) {
                    $lang[$v][$_v['key']] = $_v['text'];
                } else {
                    $_arr = [$_v['key'] => $_v['text']];
                    $lang[$v] = $_arr;
                }
            }
        }
        //获取
        $rsps['lang'] = $lang;
        $rsps['site'] = $siteConfig;
        $rsps['banner'] = $this->banner_run(true)['data'];
        $rsps['code'] = 1;
        return $rsps;
    }

    //用户信息
    public function doanload_run()
    {
        $type = $this->param['type'] ?? '';
        $sys = (new ApiRedis())->systemInfo();
        $rec_code = UserModel::where("id", $this->uid)->value('rec_code');//查看状态
        $web_url = getAppUrl();
        $data = [
            'android_download_url' => $sys['android_download_url'],
            'ios_download_url' => $sys['ios_download_url'],
            'pc_service_url' => html_entity_decode($sys['pc_service_url']),
            'mobile_service_url' => html_entity_decode($sys['mobile_service_url']),
            'web_url' => $web_url,
        ];

        $data['rec_code'] = $rec_code;
        $data['rec_url'] = $web_url . "/#/pages/register/index?share_code=" . $data['rec_code'];
        $data['qrcode_url'] = $sys['domain'] . "/team/qrcode?share_code=" . $data['rec_code'];
        $data['doanload_qrcode_url'] = $sys['domain'] . "/team/qrcode?share_code=" . $data['rec_code'] . "&type=" . $type;
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function rule_run()
    {
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $type = $this->param['type'] ?? '0';
        $where = [
            "lang" => $lang,
            "type" => $type,
            'game_id' => 0
        ];
        $data = RuleModel::where($where)->field("content")->find();
        if (empty($data)) {
            $data['content'] = '';
        }
        $rule_type = config('custom.rule_type');
        $data['title'] = isset($rule_type[$type]) ? lang($rule_type[$type]) : '';
        $rsps = [];
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function ranking_run()
    {
        $data = BangdanModel::order("id desc")->find();
        $rsps['data'] = json_decode($data['f_a_arr'], true);
        $rsps['code'] = 1;
        return $rsps;
    }

    public function banner_run($isAgent = false)
    {
        $lang = strtoupper(\think\facade\Lang::getLangSet());
        $where = [];
        $where[] = ["status", "=", 1];
        $where[] = ["start_time", "<", time()];
        $where[] = ["end_time", ">", time()];
        if (!empty($lang)) {
            $where[] = ["lang", "=", $lang];
        }
        $where1 = '';
        //如果登录就获取用户uid
        if (!$this->uid) {
            $header = request()->header();
            try {
                $token = TokenCheck::run($header);
                if ($token) {
                    $this->uid = $token->uid;
                }
            } catch (\Exception $e) {
            }
        }
        $isNeedZeroWhere = AgentService::judgeIsNeedZeroWhere($this->uid);
        $where2 = '';
        if ($isAgent) {
            //info_new中获取代理对应轮播图
            $where1 = AgentService::getAgentWhere('agent_id', true, '', true, '', false);
            //没有代理条件返回空
            if (!$where1) {
                $data['pc'] = [];
                $data['mobile'] = [];
                $rsps['data'] = $data;
                $rsps['code'] = 1;
                return $rsps;
            }
        } elseif (!$isNeedZeroWhere) {
            //不需要0条件的为分站，分站不查询任何数据
            $data['pc'] = [];
            $data['mobile'] = [];
            $rsps['data'] = $data;
            $rsps['code'] = 1;
            return $rsps;
        } else {
            //不是info获取不是代理的
//            $where[] = ['agent_id', '=', 0];
            $where2 = function ($query) {
                $query->where('agent_id', '=', 0)->whereOr('agent_id', '=', '');
            };
        }
        $banner = BannerModel::where($where)->where($where1)->where($where2)->field("img, mobile_img, url, jump_url, type, lang, show_position, sort, mobile_img")->order("sort desc")->select()->toArray();
        $data = ['pc' => [], 'mobile' => []];
        foreach ($banner as $key => $val) {
            $val['url'] = html_entity_decode($val['url']);
            if (!$val['mobile_img'] && !$val['img']) {
                continue;
            }
            if (!$val['mobile_img'] && $val['type'] == 1) {
                //老版本手机轮播图兼容
                $data['mobile'][] = $val;
                continue;
            }
            if ($val['img'] != '') {
                $data['pc'][] = $val;
            }
            if ($val['mobile_img'] != '') {
                $val['img'] = $val['mobile_img'];
                $data['mobile'][] = $val;
            }
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    public function banner_static()
    {
        $where = [];
        $where[] = ["status", "=", 1];
        $where[] = ["start_time", "<", time()];
        $where[] = ["end_time", ">", time()];

        //获取代理线0的，针对代理的冲info_new获取
        $banner = BannerModel::where($where)->where(function ($query) {
            $query->where('agent_id', '=', 0)->whereOr('agent_id', '=', '');
        })->field("img, url, jump_url, type, lang, show_position, sort, mobile_img")->order("sort desc")->select()->toArray();
        $data = [];
        foreach ($banner as $key => $val) {
            $val['url'] = html_entity_decode($val['url']);
            if (!$val['mobile_img'] && !$val['img']) {
                continue;
            }
            if ($val['mobile_img'] && $val['type'] == 1) {
                //老版本手机轮播图兼容
                $data['mobile'][] = $val;
                continue;
            }
            if ($val['img'] != '') {
                $data['pc'][] = $val;
            }
            if ($val['mobile_img'] != '') {
                $val['img'] = $val['mobile_img'];
                $data['mobile'][] = $val;
            }
        }
        if (!isset($data['pc'])) {
            $data['pc'] = [];
        }
        if (!isset($data['mobile'])) {
            $data['mobile'] = [];
        }
        $rsps['data'] = $data;
        $rsps['code'] = 1;
        return $rsps;
    }

    //发送邮箱验证码
    public function send_email_code_run()
    {
        $email = $this->param['email'] ?? '';
        $type = isset($this->param['type']) ? $this->param['type'] : 0;
        $lang = isset($this->param['lang']) ? $this->param['lang'] : 'TW';
        if (empty($email)) {
            ajax_return("请输入邮箱", 0);
        }
        $regex = "/([a-z0-9]*[-_.]?[a-z0-9]+)*@([a-z0-9]*[-_]?[a-z0-9]+)+[.][a-z]{2,3}([.][a-z]{2})?/i";
        if (!preg_match($regex, $email)) {
            ajax_return("邮箱格式错误", 0);
        }
        // register, login, forget, verify
        $scene = isset($this->param['scene']) ? $this->param['scene'] : 'register';
        if (in_array($scene, ['forget', 'login', 'verify'])) {
            $id = UserModel::where("mail", $email)->value('id');
            if (empty($id)) {
                ajax_return("邮箱地址有误", 0);
            }
        } elseif (in_array($scene, ['bind'])) {
            $emailCondition = [];
            $emailCondition[] = ['phone', '=', $email];
            //判断多代理
            $agentDomain = AgentService::judgeDomainIsBindAgent(getOriginalDomain());
            if (isset($sys['same_phone_register']) && $sys['same_phone_register'] == 1) {
                if ($agentDomain) {
                    $emailCondition[] = ['reg_url', 'in', $agentDomain];
                } else {
                    //没有值则为主站，查询没在分站的账号
                    $otherUrl = WebSiteConfigService::getAllWebsiteUrlArr();
                    $emailCondition[] = ['reg_url', 'not in', $otherUrl];
                }
            }
            $id = UserModel::where($emailCondition)->value('id');
            if (!empty($id)) {
                ajax_return("邮箱地址已绑定其它账号", 0);
            }
        }
        $ip = Util::get_ip();
        $ApiRedis = new ApiRedis();
        $sendCount = $ApiRedis->get("mailSendCode" . $ip);
        if (empty($sendCount)) $sendCount = 0;
        $mail_ip_num = 10;
        //获取系统设置
        $sys = $ApiRedis->systemInfo();
        $mail_phone_ip_num = unserialize($sys['mail_phone_ip_num']);
        if (isset($mail_phone_ip_num['mail_open']) && $mail_phone_ip_num['mail_open'] == 1) {
            $mail_time = empty($mail_phone_ip_num['mail_time']) ? '60' : $mail_phone_ip_num['mail_time'];
            $mail_ip_num = empty($mail_phone_ip_num['mail_ip_num']) ? '10' : $mail_phone_ip_num['mail_ip_num'];
        } else {
            $mail_time = 60;
        }

        if ($sendCount >= $mail_ip_num) {
            ajax_return("发送已达上限，请稍后再试", 0);
        }

        $email_code_effective = $ApiRedis->get($email . "_code_effective");
        if (!empty($email_code_effective)) {
            ajax_return("验证码发送频繁，请稍后再试", 0);
        }

        $email_code = $ApiRedis->get($email . "_code");
        if (!empty($email_code)) {
            $code = $email_code;
        } else {
            $code = rand(12345, 99999);
            $ApiRedis->set($email . "_code", $code, 600);
        }

        //判断ip发送次数
        $res = CodeService::verifyCodeCount('email');
        if ($res !== true) {
            ajax_return($res, 0);
        }

        $ret = EmailCode::run($email, $code, $type);
        if (!$ret) {
            //减去ip发送次数
            CodeService::sendCodeFailReduceCount('email');
            ajax_return("验证码发送失败", 0);
        }

        $sendCount++;
        $ApiRedis->set("mailSendCode" . $ip, $sendCount, strtotime(date("Y-m-d") . " 00:00:00") - time() + 86400);
        $ApiRedis->set($email . "_code_effective", $code, $mail_time);
        return [
            'code' => 1,
        ];
    }

    //发送验证码 免登陆
    public function send_code_run()
    {
        $phone1 = $phone = $this->param['phone'] ?? '';
        $type = isset($this->param['type']) ? $this->param['type'] : 0;
        $qh = '86';
        if (isset($this->param['areacode'])) {
            $qh = $this->param['areacode'];
        }
        if (isset($this->param['qh'])) {
            $qh = $this->param['qh'];
        }
        $qh = str_replace('+', '', $qh);
        if (empty($phone)) {
            ajax_return("请输入手机号", 0);
        }

        if (!checkPhone($phone)) {
            ajax_return("手机号码格式错误", 0);
        }

        // register, login, forget, verify
        $scene = isset($this->param['scene']) ? $this->param['scene'] : 'register';
        if (in_array($scene, ['forget', 'login', 'verify'])) {
            $id = UserModel::where("phone", $phone)->value('id');
            if (empty($id)) {
                ajax_return("手机号码有误", 0);
            }
        } elseif (in_array($scene, ['bind'])) {
            $phoneCondition = [];
            $phoneCondition[] = ['phone', '=', $phone];
            //判断多代理
            $agentDomain = AgentService::judgeDomainIsBindAgent(getOriginalDomain());
            if (isset($sys['same_phone_register']) && $sys['same_phone_register'] == 1) {
                if ($agentDomain) {
                    $phoneCondition[] = ['reg_url', 'in', $agentDomain];
                } else {
                    //没有值则为主站，查询没在分站的账号
                    $otherUrl = WebSiteConfigService::getAllWebsiteUrlArr();
                    $phoneCondition[] = ['reg_url', 'not in', $otherUrl];
                }
            }
            $id = UserModel::where($phoneCondition)->value('id');
            if (!empty($id)) {
                ajax_return("手机号码已绑定其它账号", 0);
            }
        }
        $ApiRedis = new ApiRedis();
        $sendCount = $ApiRedis->get("phoneSendCode" . $phone1);
        if (empty($sendCount)) $sendCount = 0;
        $phone_ip_num = 10;
        //获取系统设置
        $sys = $ApiRedis->systemInfo();
        $mail_phone_ip_num = unserialize($sys['mail_phone_ip_num']);
        if (isset($mail_phone_ip_num['phone_open']) && $mail_phone_ip_num['phone_open'] == 1) {
            $phone_time = empty($mail_phone_ip_num['phone_time']) ? '60' : $mail_phone_ip_num['phone_time'];
            $phone_ip_num = empty($mail_phone_ip_num['phone_ip_num']) ? '5' : $mail_phone_ip_num['phone_ip_num'];
        } else {
            $phone_time = 300;
        }

        if ($sendCount >= $phone_ip_num) {
            ajax_return("该手机号码接收短信已达上限", 0);
        }

        $phone_code = $ApiRedis->get($phone1 . "_code");
        if (!empty($phone_code)) {
            ajax_return("验证码发送频繁，请稍后再试", 0);
        }

        //判断ip发送次数
        $res = CodeService::verifyCodeCount('phone');
        if ($res !== true) {
            ajax_return($res, 0);
        }

        if (!SmsCode::run($qh, $phone, $phone1, $phone_time, $type)) {
            //减去ip发送次数
            CodeService::sendCodeFailReduceCount('phone');
            ajax_return("验证码发送失败", 0);
        }

        $sendCount++;
        $ApiRedis->set("phoneSendCode" . $phone1, $sendCount, strtotime(date("Y-m-d") . " 00:00:00") - time() + 86400);
        $phone_code = $ApiRedis->get($phone1 . "_code");
        return [
            'code' => 1,
        ];
    }

    public function getlang($langs)
    {
        return self::getLangS($langs);
    }

    public static function getLangS($langs)
    {
        $lang = [];
        foreach ($langs as $key => $val) {
            $data = [];
            $lang_file = base_path("lang") . strtolower($val['lang']) . ".php";
            if (file_exists($lang_file)) {
                $lang[$val['lang']] = require($lang_file);
            } else {
                $lang[$val['lang']] = '';
//                $lang = '';
            }
        }
        return $lang;
    }

    //获取系统维护信息
    public function get_upd_run()
    {
        $upd = SystemUpdModel::where(['status' => 1])->order('id desc')->find();
        $rsps['data'] = $upd;
        $rsps['code'] = 1;
        return $rsps;
    }

    //获取镜像站点
    public function get_mirror_site()
    {
        $list = MirrorSiteModel::field('url,status')->select();
        $rsps['data'] = $list;
        $rsps['code'] = 1;
        return $rsps;
    }
}