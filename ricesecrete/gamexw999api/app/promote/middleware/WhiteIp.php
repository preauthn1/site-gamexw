<?php

namespace app\promote\middleware;

use app\common\model\IpLimitModel;
use app\common\lib\Util;

class WhiteIp {

    public function handle($request, \Closure $next) {
//        $model = new IpLimitModel();
//        $allow_ips = $model->where('admin_status', 0)->where('api_status', -1)->column('ip');
//        $ban_ips = $model->where('admin_status', 1)->where('api_status', -1)->column('ip');
//        $ip = Util::get_ip();
//        if (isArrayAndNotEmpty($allow_ips) && !in_array($ip, $allow_ips)) {
//            echo "ip:{$ip},Not whitelisted!";
//            die;
//        }
//        if (isArrayAndNotEmpty($ban_ips) && in_array($ip, $ban_ips)) {
//            echo "ip:{$ip},Blocked!";
//            die;
//        }
        return $next($request);
    }

}
