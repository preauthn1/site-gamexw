<?php
namespace app\manage\controller;

use app\common\model\LangModel;
use app\common\redis\CommomRedis;
use app\common\lib\{Respons, Util, Authenticator};
use think\facade\Config;

class WebConfig
{
    protected $middleware = ["Language"];

    public function getI18n(){
        $CommomRedis = new CommomRedis();
        $langs = $CommomRedis->getLangsData();
        $nation = $CommomRedis->getNation();
        $locale = "CN";
        $locale_flag = "";
        $idc_arr = $lang_arr = [];
        $currency_name = "";
        foreach($langs as $val){
            if($val['is_default'] == 1){
//                $currency_name = $val['title'];
                $locale = $val['lang'];
                $locale_flag = $val['flag'];
            }
            $lang_arr[] = ['icon' => $val['flag'], 'value' => strtolower($val['lang']), 'label' => $val['lang']];
        }
        $curr_zone = Config::get("custom.timezone");
        $curr = Config::get("custom.country");
        $timezone = env('DEFAULT_TIMEZONE', 'Asia/Shanghai');
        $new_zone = array_flip($curr_zone);
        $currency_name = $curr[$new_zone[$timezone] ?? 'CN'];
        $date_format = 'Y-m-d';
        $time_format = 'H:i:s';
//        $date_format = Config::get("custom.date")[$locale] ?? 'Y-m-d';
//        $time_format = Config::get("custom.time")[$locale] ?? 'H:i:s';
        $date_time = date("{$date_format} {$time_format}");
//        halt($date_time);
        $is_google = env('is_google', 0);
        $data = [
            'locale' => strtolower($locale),
            'fallbackLocale' => strtolower($locale),
            "currency_name" => $currency_name,
            "currency_date" => $date_time,
            'timezone' => $timezone,
            'langs' => $lang_arr,
            'messages' => $this->getI18nMessages($langs),
            'google_auth' => $is_google === 0 ? 1 : 0,
            'config' => [
                "Version" => "4.5.0",
                "Title" => lang("后台管理"),
                "FixedHeader" => true,
                "HiddenSideBar" => false,
                "MultiTagsCache" => false,
                "KeepAlive" => true,
                "Locale" => "en",
                "Layout" => "vertical",
                "Theme" => "default",
                "DarkMode" => false,
                "Grey" => false,
                "Weak" => false,
                "HideTabs" => false,
                "SidebarStatus" => true,
                "EpThemeColor" => "#409EFF",
                "ShowLogo" => true,
                "ShowModel" => "smart",
                "MenuArrowIconNoTransition" => true,
                "CachingAsyncRoutes" => false,
                "TooltipEffect" => "light",
                "ResponsiveStorageNameSpace" => "responsive-"
            ]
        ];
        return Respons::run(1, $data);
    }

    private function getI18nMessages($langs){
        $lang = [];
        foreach($langs as $key => $val){
            $data = [];
            $lang_file = base_path("lang") . strtolower($val['lang']) . ".php";
            $lang_str = strtolower($val['lang']);
            if(file_exists($lang_file)){
                $lang[$lang_str] = require($lang_file);
            }
        }
        return $lang;
    }
}