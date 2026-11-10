<?php

namespace app\api\middleware;

class Cores {

    public function handle($request, \Closure $next) {
        //OPTIONS请求返回204请求
        if ($request->method(true) === 'OPTIONS') {
            return response();
        }
        $response = $next($request);
        $origin = $request->header('Origin', '');

        $response->header([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET,POST,PUT',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Headers' => 'x-requested-with,Content-Type,Authorization,x-xsrf-token,usertokenkey,usertoken,lang,origin,noToken',
        ]);
        return $response;
    }

}
