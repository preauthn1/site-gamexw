<?php
namespace app\common\model;
use think\Model;
use think\facade\Db;
class UserAdminLogModel extends Model
{
	protected $name = 'user_admin_log';
	protected $schema = [
        'id'          => 'int',
        'add_time'          => 'bigint',
        'uid'          => 'int',
        'cz_msg'          => 'varchar',
        'admin_name'          => 'varchar',
    ];
    
    /**
     * @param $uid
     * @param $cz_msg
     * @param $admin_name
    **/ 
    public static function add_log($uid,$cz_msg,$admin_name){
        $add['add_time']=time();
        $add['uid']=$uid;
        $add['cz_msg']=$cz_msg;
        $add['admin_name']=$admin_name;
        return self::insertGetId($add);
    }

    public static function addLogs($uid, $cz_msg, $admin_name){
        if (is_array($uid)) {
            foreach ($uid as $id) {
                self::add_log($id, $cz_msg, $admin_name);
            }
            return true;
        } else {
            return self::add_log($uid, $cz_msg, $admin_name);
        }
    }
}