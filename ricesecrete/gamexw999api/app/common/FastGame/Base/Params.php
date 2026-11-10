<?php


namespace app\common\FastGame\Base;


use think\App;

class Params
{

    public static $App=null;
    /**
     * Request实例
     * @var \think\Request
     */
    protected static $Request=null;
    /**
     * @var \think\Cookie
     */
    public static $Cookies=null;
    public static $Header=null;
    public static $Session=null;

    public static $upid=null;
    public static $uid=null;
    public static $tree=null;
    public static $page=1;
    public static $psize=10;
    public static $start=null;
    public static $end=null;
    public static $utoken=null;
    public static $key=null;
    public static $gid=null;
    public static $lang=null;
    /**
     * @var string 推荐人CODE
     */
    public static $pcode=null;

    /**
     * @param App $app
     * @author twt326
     * @date 2022-08-21 18:54
     */
    public static function init(App $app){
        if (self::$App) return;
        self::$App=$app;
        self::$Request=$app->request;
        self::$Cookies=$app->cookie;
        self::$Header=$app->session;
        self::$upid=intval(self::$Request->get('upid'))??null;
        self::$uid=intval(self::$Request->get('uid'))??null;
        self::$tree=self::$Request->get('tree')??null;
        self::$page=intval(self::$Request->get('page'))>0?intval(self::$Request->get('page')):1;
        self::$psize=intval(self::$Request->get('psize'))>0?intval(self::$Request->get('psize')):10;
        self::$start=self::$Request->get('start')??null;
        self::$end=self::$Request->get('end')??null;
        self::$utoken=self::$Request->get('utoken')??null;
        self::$key=self::$Request->get('key')??null;
        self::$gid=intval(self::$Request->get('gid'))??null;
        self::$lang=self::$Request->param('lang') ?? (self::$Request->header('lang') ?? null);
        if (!self::$lang) self::$lang = env('DEFAULT_LANG',null);
        if (self::$Cookies->get('pcode')){
            self::$pcode=self::$Cookies->get('pcode');
        }else {
            self::$pcode = self::$Request->get('pcode') ?? null;
            if (self::$pcode) self::$Cookies->set('pcode',self::$pcode);
        }
    }

    public function __get($name)
    {
        // TODO: Implement __get() method.
        if (isset(self::$$name)) return self::$$name;
        if (self::$Request && self::$Request->get($name)) return self::$Request->get($name);
        return null;
    }


}
