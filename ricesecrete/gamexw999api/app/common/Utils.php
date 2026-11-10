<?php
namespace app\common;
use app\common\model\ActionTypeModel;
use app\common\model\AdminLogModel;
use app\common\model\AdminNodeModel;
use app\common\model\AdminRoleModel;
use app\common\model\GameTypeModel;
use app\common\model\UrlModel;
use app\model\SysSets;
use app\model\ThirdConfigs;
use think\facade\Db;
use think\facade\Session;

class Utils
{
    private static $sysinfo=false;

    /** 后端日志封装
     * @param $tag 日志标志，作为文件名的一部分
     * @param $msg 日志消息，任意类型值
     * @param $byDay 是否按日分割日志，默认为按月分割日志
     */
    public static function L($tag, $msg, $byDay = false, $debug = false){
        \app\common\FastGame\Base\Utils::L($tag, $msg, $byDay, $debug);
    }

    /**
     * 获取系统配置信息 --  用于不适合redis缓存的地方
     * @param null $fieldName 默认返回全部信息，有值返回对应的字段
     * @return bool
     * @author twt326
     * @date 2022-03-24 22:02
     */
    public static function sysInfo($fieldName=null){
        if (!self::$sysinfo){
            self::$sysinfo = SysSets::getSets(false);
        }
        return $fieldName?(isset(self::$sysinfo[$fieldName])?self::$sysinfo[$fieldName]:''):self::$sysinfo;
    }

    public static function writeToStaticFile($filename,$data){
        $dir=dirname(__FILE__) . DIRECTORY_SEPARATOR . './../../public/staticCaches/';
        @mkdir($dir,077,true);
        file_put_contents($dir.$filename,$data);
    }

    /**
     * 获取前端select,radio,checkbox的数据
     * @param string $table
     * @param array $map
     * @param array $fields
     * @param string $conn
     * @return array
     */
    public static function getOptions($name, array $fields = ['id', 'name'], $where = null, $order = '') {
        $return = [];
        if (empty($name)) {
            return $return;
        }
        $query = Db::name($name)->where($where);
        if( $order ){
            $query->order($order);
        }
        $result = $query->select();
        if(!empty($result)) {
            foreach ($result as $value) {
                $label = $value[$fields[1]];
                if (isset($fields[2]) && isset($value[$fields[2]]) && $value[$fields[2]] !== '') {
                    $label .= "({$value[$fields[2]]})";
                }
                $return[] = [ 'label' => $label, 'value' => $value[$fields[0]] ];
            }
        }
        return $return;
    }


    /**
     * 获取前端select,radio,checkbox的数据
     * @param string $table
     * @param array $map
     * @param array $fields
     * @param string $conn
     * @return array
     */
    public static function getOptionsByConfig($data = []) {
        $return = [];
        if (empty($data)) {
            return $return;
        }
        foreach ($data as $value => $label) {
            $return[] = [ 'label' => $label, 'value' => $value ];
        }
        return $return;
    }

    //获取前端等级数据
    public static function getlevel() {
        $result = Db::name('user_level')->select();
        if(!empty($result)) {
            foreach ($result as $value) {
                $return[] = [ 'level_name' => $value['level_name'],'level' => $value['id'], 'proportion' => 0 ];
            }
        }
        return $return;
    }

    /**
     * 获取查询时间戳
     * @param string $str 时间字符串，格式：2022-12-12 or 2012-12-12 00:22:00
     * @param $is_end 是否为结束，
     * @return int
     */
    public static function getQueryDateTime($str, $is_end = false) {
        if (strexists($str, ":")) {
            $time = strtotime($str);
        } else {
            $time = strtotime($str . " " . ($is_end ? '23:59:59' : '00:00:00'));
        }
        return $time === false ? time() : $time;
    }

    /**
     * 获取查询时间格式
     * @param string $str 时间字符串，格式：2022-12-12 or 2012-12-12 00:22:00
     * @param string $format 格式，
     * @return string
     */
    public static function getQueryDate($str, $format = "Y-m-d") {
        if (strexists($str, ":")) {
            $time = strtotime($str);
        } else {
            $time = strtotime($str);
        }
        return date($format, $time === false ? time() : $time);
    }

    /**
     * 添加管理员操作日志
     * @param $forward_data
     * @param $rear_data
     * @param $table
     * @param $handle_name
     * @return bool|void
     */
    public static function addOptLog($forward_data = '', $rear_data = '', $table = '', $handle_name = '', $model = null) {
        if ($model) {
            list($forward_data, $rear_data, $table) = array_values(updata($model, $forward_data, $rear_data));  //日志数据
        }
        $add = [];
        $add['add_time'] = time();
        $add['admin_name'] = Session::get("adminLog.admin_name");
        $add['log_url'] = Session::get("adminLog.log_url");
        $add['handle_name'] = $handle_name ? $handle_name:Session::get("adminLog.handle_name");
        $add['admin_id'] = Session::get("adminLog.admin_id") ?? 0;
        $add['admin_account'] = Session::get("adminLog.username") ?? '';
        $add['forward_data'] = is_array($forward_data) ? json_encode($forward_data) : $forward_data;
        $add['rear_data'] = is_array($rear_data) ? json_encode($rear_data) : $rear_data;
        $add['table'] = $table;
        $add['ip'] = \app\common\lib\Util::get_ip();
        AdminLogModel::create($add);
    }

    public static function addOptLogByHandle($handle_name) {
        self::addOptLog('', '', '', $handle_name);
    }

    /**
     * 生成订单号
     * @return string
     */
    public static function getOrderSN($order_id = '', $prefix = "") {
        return $prefix . date ( 'dmHis' ) . rand ( 10, 99 ) . $order_id;
    }

    /**
     * 判断角色是否不能看用户的敏感信息
     * @param $role_id
     * @return bool
     */
    public static function isHideAuthUser($role_id) {
        $is_auth_hide_user = Session::get("adminInfo.is_auth_hide_user");
        if (is_bool($is_auth_hide_user)) {
            return $is_auth_hide_user;
        }
        try {
            $auth_id = AdminNodeModel::where('node_url', 'hide')->value('id');
            $role = AdminRoleModel::where('id', $role_id)->find();
            if (empty($auth_id) || empty($role) || $role_id == 1) {
                return false;
            }
            $role_permission = explode(",", $role->role_permission);
            return in_array($auth_id, $role_permission);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 隐藏用户敏感信息
     * @param $row 用户信息数组
     * @return mixed
     */
    public static function hideUserInfo($row) {
//        $role_id = Session::get("adminInfo.role_id");
//        if (self::isHideAuthUser($role_id)) {
//            $field = ['phone', 'mail', 'rec_name', 'rec_user', 'rec_top_user', 'username'];
//            foreach ($field as $key) {
//                if (isset($row[$key]) && $row[$key]) {
//                    $row[$key] = hideStr($row[$key]);
//                }
//            }
//        }
        return $row;
    }

    //获取链接数组
    public static function getUrlList(){
        //获取链接列表
        $urlList = UrlModel::field('name,url')->select();
        $result = [];
        $result[] = ['label' => '无', 'value' => ''];
        foreach ($urlList as $v){
            $result[] = ['label' => $v['name'], 'value' => $v['url']];
        }

        $game_type = GameTypeModel::where('state', 1)->field('id,name')->select();
        $result[] = ['label' => "所有游戏", 'value' => "/all-game?tab=all"];
        foreach ($game_type as $v){
            $result[] = ['label' => "游戏分类-{$v['name']}", 'value' => "/all-game?tab={$v['id']}"];
        }


        $tcs = ThirdConfigs::where('tc_status', 1)->field('tc_id,tc_name')->select();
        foreach ($tcs as $v){
            $result[] = ['label' => "游戏平台-[{$v['tc_name']}]游戏", 'value' => "/all-game?ptid={$v['tc_id']}"];
        }
        return $result;
    }

    //获取活动类型数组
    public static function getActionTypeList(){
        //获取链接列表
        $urlList = ActionTypeModel::field('id,name')->order('sort desc,id desc')->select();
        $result = [];
        $result[] = ['label' => '无', 'value' => 0];
        foreach ($urlList as $v){
            $result[] = ['label' => $v['name'], 'value' => $v['id']];
        }
        return $result;
    }

    //判断数据是否存在
    public static function judgeDataExist($model, $data, $fields, $id = 0){
        $condition = [];
        foreach ($fields as $v){
            $condition[$v] = ['=', $data[$v]];
        }
        $_id = $model->where($condition)->value('id');
        if( $_id ){
            if( $id && $id != $_id ){
                return true;
            }elseif( !$id ){
                return true;
            }
        }
        return false;
    }
}