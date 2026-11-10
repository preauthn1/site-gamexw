<?php
namespace app\common\model;

use think\facade\Session;
use think\Model;

class AdminLogModel extends Model {

    protected $name = 'admin_log';
    protected $schema = [
        'id' => 'int',
        'add_time' => 'bigint',
        'admin_name' => 'varchar',
        'log_url' => 'varchar',
        'handle_name' => 'varchar',
        'admin_id' => 'int',
        'forward_data' => 'text',
        'rear_data' => 'text',
        'action' => 'varchar',
        'admin_account' => 'varchar',
        'table' => 'varchar',
        'ip' => 'varchar',
    ];

    //添加日志
    public static function addLog($forward_data = '',$rear_data = '',$table = '',$handle_name = '') {
        $url = Session::get("adminLog.log_url");
        if (empty($url) || self::ignore($url)) {
            return true;
        } else {
            $add = [];
            $add['add_time'] = time();
            $add['admin_name'] = Session::get("adminLog.admin_name");
            $add['log_url'] = Session::get("adminLog.log_url");
            $add['handle_name'] = $handle_name ? $handle_name:Session::get("adminLog.handle_name");
            $add['admin_id'] = Session::get("adminLog.admin_id");
            $add['admin_account'] = Session::get("adminLog.username");
            $add['forward_data'] = is_array($forward_data) ? json_encode($forward_data, JSON_UNESCAPED_UNICODE) : $forward_data;
            $add['rear_data'] = is_array($rear_data) ? json_encode($rear_data, JSON_UNESCAPED_UNICODE) : $rear_data;
            $add['table'] = $table;
            $add['ip'] = \app\common\lib\Util::get_ip();
            self::insertGetId($add);
        }
    }

    //不验证路径
    public static function ignore($url) {
        $urls = [
            '/admin/index/get_data',
            '/admin/index/get_match',
            '/admin/index/cjl_set',
            '/admin/index/cjl_ignore',
//            '/admin/index/cancel_match',
            '/admin/agent_report/user_data_view',
            '/admin/agent_report/user_data',
            '/admin/user/user_top',
            '/admin/user/get_top_user',
//            '/admin/artificial_balance/import',
            '/admin/index/bind_google',
            '/admin/report/query_data',
        ];
        if (in_array($url, $urls)) {
            return true;
        } else {
            return false;
        }
    }

}
