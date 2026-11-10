<?php
// 全局中间件定义文件
return [
    // 全局请求缓存
//     \think\middleware\CheckRequestCache::class,
    // 多语言加载
    // \think\middleware\LoadLangPack::class,
    // Session初始化
    \think\middleware\SessionInit::class,
    // 后台白名单限制
    app\manage\middleware\WhiteIp::class,
    // 多语言
    app\manage\middleware\Language::class,
];
