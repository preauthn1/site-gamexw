<?php
namespace app\promote\controller;

use app\common\redis\CommomRedis;
use app\common\lib\{Respons, Util, Authenticator};
use think\facade\Config;

class WebConfig
{

    public function getI18n(){
        $CommomRedis = new CommomRedis();
        $langs = $CommomRedis->getLangsData();
        $nation = $CommomRedis->getNation();
        $locale = "CN";
        $locale_flag = "";
        $idc_arr = $lang_arr = [];
        foreach($langs as $val){
            if($val['is_default'] == 1){
                $locale = $val['lang'];
                $locale_flag = $val['flag'];
            }
            $lang_arr[] = ['icon' => $val['flag'], 'value' => strtolower($val['lang']), 'label' => $val['lang']];
        }

        $is_google = env('is_google', 0);
        $data = [
            'locale' => strtolower($locale),
            'fallbackLocale' => strtolower($locale),
            'langs' => $lang_arr,
            'messages' => $this->getI18nMessages($langs),
            'google_auth' => $is_google === 0 ? 1 : 0,
            'config' => [
                "Version" => "3.6.4",
                "Title" => lang("代理后台管理"),
                "FixedHeader" => true,
                "HiddenSideBar" => false,
                "MultiTagsCache" => false,
                "KeepAlive" => true,
                "Locale" => "tw",
                "Layout" => "vertical",
                "Theme" => "default",
                "DarkMode" => false,
                "Grey" => false,
                "Weak" => false,
                "HideTabs" => false,
                "SidebarStatus" => true,
                "EpThemeColor" => "#409EFF",
                "ShowLogo" => true,
                "ShowModel" => "smart"
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