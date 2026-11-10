<?php

namespace app\manage\middleware;

use app\common\model\IpLimitModel;
use app\common\lib\Util;

class WhiteIp {

    public function handle($request, \Closure $next) {

        //是否关闭白名单功能（1关闭，0开启）
        $is_closed_white_list = env('is_closed_white_list', 0);
        if($is_closed_white_list){
           return $next($request);
        }
        $model = new IpLimitModel();
        $allow_ips = $model->where('admin_status', 0)->where('api_status', -1)->column('ip');
        $ban_ips = $model->where('admin_status', 1)->where('api_status', -1)->column('ip');
        $ip = Util::get_ip();
        if (isArrayAndNotEmpty($allow_ips) && !in_array($ip, $allow_ips)) {
            return json([
                'code' => 403,
                'msg' => "ip:{$ip},Not whitelisted!"
            ]);
        }
        if (isArrayAndNotEmpty($ban_ips) && in_array($ip, $ban_ips)) {
            return json([
                'code' => 403,
                'msg' => "ip:{$ip},Blocked!"
            ]);
        }
        return $next($request);
    }

}
