<?php

namespace app\promote\middleware;

use app\common\model\LangModel;
use think\facade\Config;
use app\common\upgrade\App;

class Language {

    public function handle($request, \Closure $next) {
        $lang = LangModel::where('is_default', 1)->find();
        if ($lang) {
            Config::set(['default_lang' => strtolower($lang->lang)], 'lang');
        }
        if (!cookie('?think_lang')) {
            if ($lang) {
                cookie('think_lang', $lang->lang);
            } else {
                cookie('think_lang', null);
            }
        }

        return $next($request);
    }

}
