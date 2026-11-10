<?php

namespace app\common\lib;

use app\common\FastGame\Base\Utils;
use think\facade\Request;
use app\common\model\AdminLogModel;

class Respons {

    private static $codes = [
        0 => '操作错误',
        1 => '操作成功',
        101 => '账号已封停',
        102 => '请验证手机号',
        1000 => '未登录',
        991 => '未登录',
    ];

    /**
     * 数据返回
     * $code 状态
     * $data 返回数据
     * $msg  提示语
     * $forward_data 操作前数据 【数组格式】
     * $rear_data 操作后数据 【数组格式】
     * $model 表model
     * $handle_name 操作动作
     */
    public static function run($code = 0, $data = [], $msg = '',$forward_data = '',$rear_data = '',$model = '',$handle_name='') {
        if (is_array($code)) {
            //数组
            $data = $code;
            $rsps['code'] = isset($data['code']) ? $data['code'] : 0;
            $rsps['data'] = isset($data['data']) ? $data['data'] : [];
            $rsps['msg'] = (isset($data['msg']) && $data['msg'] != '') ? $data['msg'] : (isset(self::$codes[$data['code']]) ? self::$codes[$data['code']] : $msg);

            foreach ($data as $key => $value) {
                if ($key != 'code' || $key != 'data' || $key != 'msg') {
                    $rsps[$key] = $value;
                }
            }
        } else {
            //不是数组
            $rsps['code'] = $code;
            $rsps['data'] = $data;
            $rsps['msg'] = $msg != '' ? $msg : (isset(self::$codes[$code]) ? self::$codes[$code] : '');
        }
        //后台模块操作，添加日志
//        if ((app('http')->getName() == "admin" || app('http')->getName() == "manage") && $rsps['code'] == 1 && empty($rsps['data'])) {
        if ($rsps['code'] == 1 && empty($rsps['data'])) {
                $log = updata($model,$forward_data,$rear_data);  //日志数据
                if($handle_name){
                    AdminLogModel::addLog($log['forward_data'],$log['rear_data'],$log['table'],$handle_name); //记录日志
                }
        }
        if (is_string($rsps['msg']) && $rsps['msg'] != '') {
            $rsps['msg'] = lang($rsps['msg']);
        }
        if (env('APP_DEBUG',false) && env('APP_TRACE',false)) {
            $rsps['debugtraces']=Utils::$debuginfos;
        }
        return json($rsps);
    }

}
