<?php

namespace app\api\factory_v2;

use app\common\lib\TokenCheck;
use app\common\lib\Util;
use app\common\model\GameSetModel;
use app\common\model\MirrorSiteModel;
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
use app\common\service\SystemService;
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
            'phone_check' => 0, 'code_check' => 0, 'fund_status' => 0,
            'transaction_charge' => 0, 'phone_bind' => 3, 'revoke_order' => 0,
            'quick_withdrawal' => [], 'usdt_quick_withdrawal' => [], 'quick_recharge' => [],
            'usdt_quick_recharge' => [], 'detail_notes' => [],
            'saba_check' => 0,
            'thirdLoginStatus' => env('is_third_open', 0),
            'shabaStatus' => env('is_shaba_open', 0),
            'fundStatus' => env('is_fund_open', 0),
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
            'is_reg_type' => 0,
            'start_page_status' => 0,#启动页状态
            'seo_keyword' => '',
            'seo_intro' => '',
            'monetary_type' => ''//基础货币配置
        ];
        foreach ($sys as $key => $val) {
            if (isset($data[$key])) {
                if (in_array($key, ['quick_withdrawal', 'usdt_quick_withdrawal', 'quick_recharge', 'usdt_quick_recharge'])) {
                    $val = quempty(explode(',', $val));
                } elseif (in_array($key, ['detail_notes'])) {
                    $val = unserialize($val);
                } elseif (in_array($key, ['pc_service_url', 'mobile_service_url', 'android_download_url', 'ios_download_url'])) {
                    $val = html_entity_decode($val);
                }
                $data[$key] = $val;
            }
        }
        if ($data['fundStatus'] <> 0) {
            $data['fundStatus'] = isset($sys['fund_status']) ? intval($sys['fund_status']) : 0;
        }
        if ($data['shabaStatus'] <> 0) {
            $data['shabaStatus'] = isset($sys['saba_check']) ? intval($sys['saba_check']) : 0;
        }
        //默认货币
        $data['monetary_type'] = Util::i18n_currency_no_amount();
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

        $data['favicon'] = lang('favicon');
        $data['web_name'] = lang('web_name');
        $data['register_first_account_type'] = env('REGISTER_FIRST_ACCOUNT_TYPE', 'email');
        $idc_arr = unique_array_key($idc_arr, 'value');
        $rsps['data'] = $data;
        $rsps['idc_default'] = $idc_default;
        $rsps['locale'] = $locale;
        $rsps['locale_flag'] = $locale_flag;
        $rsps['lang'] = self::getLangS($langs);
        $rsps['alllang'] = $lang_arr;
        $rsps['nation'] = $nation;
        $rsps['idc'] = array_values($idc_arr);
         //站点维护
        $temp = SystemUpdModel::where(['status' => 1])->find();
        $rsps['maintain'] = empty($temp) ? 0 : 1;//0未维护，1正在维护
        $rsps['code'] = 1;
        return $rsps;
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
}