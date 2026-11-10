<?php

namespace app\api\middleware;
use app\common\FastGame\Base\Params;
use think\facade\Lang;
use think\facade\Config;
use app\common\model\LangModel;

class SetLang {

    public function handle($request, \Closure $next) {
        $local = false;
        $lang = false;
        if ($request->has('lang')) {
            $local = strtolower($request->param('lang'));
        }elseif ($request->header('lang')) {
            $local = strtolower($request->header('lang'));
        }else{
            $lang = env('default_country', '');
            if(empty($lang)){
                $lang = LangModel::where('is_default', 1)->value('lang');
            }

            if ($lang) {
                $local = strtolower($lang);
                Config::set(['default_lang' => $local], 'lang');
            }
        }
        if ($local) {
            Lang::setLangSet($local);
        }
        if (!cookie('?think_lang')) {
            if ($lang) {
                cookie('think_lang', $local);
            } else {
                cookie('think_lang', null);
            }
        }
        Params::$lang=$local;

        return $next($request);
    }

}
