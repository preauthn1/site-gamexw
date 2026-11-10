<?php

use app\common\lib\Respons;
use app\common\lib\TokenCheck;
use app\common\model\UserModel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use think\facade\Session;
use think\facade\Config;

// 应用公共文件
/*
 * 解日期
 * @param  date 格式 2021-08-01|2021-08-05，2021-08-01当做2021-08-01|2021-08-01处理
 * return array [start_time,end_time] 时间戳
 */
function undate($date){
    $date=explode("|", $date);
    if(count($date) > 1){
        $start_date = $date[0]." 00:00:00";
        $end_date = $date[1]." 23:59:59";
    }else{
        $start_date = $date[0]." 00:00:00";
        $end_date = $date[0]." 23:59:59";
    }
    $patten = "/^\d{4}[\-](0?[1-9]|1[012])[\-](0?[1-9]|[12][0-9]|3[01])(\s+(0?[0-9]|1[0-9]|2[0-3])\:(0?[0-9]|[1-5][0-9])\:(0?[0-9]|[1-5][0-9]))?$/";
    if (preg_match($patten, $start_date) && preg_match($patten, $end_date)) {
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
        return [$start_time, $end_time];
    }else{
        return false;
    }
}

/**
 * IP转地区
 * @param type $ip
 * @param type $fields
 * @return type
 */
function ip2location($ip, $fields = [2,3,4], $ipdatabase = null) {
    if(empty($ip)) {
        return "";
    }
    if (is_null($ipdatabase)) {
        $ipdatabase = cookie("ipdatabase");
    }
    if ($ipdatabase != "en") {
        $res = \ip\GetIpArea::find(trim($ip));
        if(!isArrayAndNotEmpty($res)) {
            return "";
        }else{
            return implode(" ", array_unique(array_filter($res)));
        }
    }else{
        $db = new \ip\IP2Location();
        $records = $db->lookup(trim($ip), $fields);
        if (is_array($records)) {
            $str = [];
            $str[] = isset($records['countryName']) && $records['countryName'] != "-" ? "{$records['countryName']}" : "";
            $str[] = isset($records['regionName']) && $records['regionName'] != "-" ? "{$records['regionName']}" : "";
            $str[] = isset($records['cityName']) && $records['cityName'] != "-" ? "{$records['cityName']}" : "";
            $records = array_filter(array_unique($str));
            return is_array($records) ? implode(" ", $records) : "";
        }
        return $records === "-" ? "" : $records;
    }
}

/**
 * 过滤特殊字符
 * @param type $strParam
 * @return type
 */
function replaceSpecialChar($strParam) {
    $regex = "/\ |\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
    $strParam = preg_replace( '/[\x{200B}-\x{200D}]/u', '', $strParam );
    return preg_replace($regex, "", $strParam);
}

function userLevelSql($uid, $level=0, $search_level=0, $isall=0, $is_zj=0){
    $where = "";
    if($search_level == 1){
        $where = "rec_id = ".$uid;
    }elseif($search_level == 2){
        $where = "rec_id2 = ".$uid;
    }elseif($search_level == 3){
        $where = "rec_id3 = ".$uid;
    }else{
        $where = " (rec_id = ".$uid." or rec_id2 = ".$uid." or rec_id3 = ".$uid.")";
    }
    if($is_zj == 1){
        $where .= " or id=".$uid;
    }
    $sql = \app\common\model\UserModel::where($where)->field("id")->buildSql();
    return $sql;
}

function unLevelSql($uid, $level=0, $search_level=0, $tree=''){
    if(empty($tree)){
        $tree = \app\common\model\UserModel::where(['id'=>$uid])->value('tree');
    }
    if(empty($search_level)) $search_level = 0;
    if($search_level == 0){
        $sql = \app\common\model\UserModel::where([["tree","like",$tree."%"]])->field("id")->buildSql();
    }else{
        $where[] = ['tree','like',$tree."%"];
        $where[] = ['rec_level','=', $level + $search_level];
        $sql = \app\common\model\UserModel::where($where)->field("id")->buildSql();
    }
    return $sql;
}

function getMatchStatus($status){
    $code = [
        0 => lang("未开始"),
        1 => lang("进行中"),
        2 => lang("结束"),
        4 => lang('点球大战'),
        8 => lang('加时赛'),
        11 => lang('推迟'),
        13 => lang('取消'),
        14 => lang('暂停'),
        15 => lang('中断'),
        16 => lang('待定'),
        17 => lang('上半场'),
        18 => lang('下半场'),
        19 => lang('中场休息'),
        20 => lang('异常'),
    ];
    $describe = $code[$status];
    return $describe;
}

function getEmptyArr($s_time,$e_time,$type='day'){
    $tmp = array();
    if($type=='day'){
        $s_time = strtotime($s_time);
        $e_time = strtotime($e_time);
        while($e_time>=$s_time){
            $tmp[] = date('Y-m-d',$e_time);
            $e_time -= 86400;
        }
    }else if($type == 'month'){
        $s_time = strtotime(date('Y-m',strtotime($s_time)));
        $e_time = strtotime(date('Y-m',strtotime($e_time)));
        while($e_time>=$s_time){
            $e_time_str = date('Y-m',$e_time);
            $tmp[] = $e_time_str;
            $e_time = strtotime("$e_time_str -1 month");
        }
    }
    return $tmp;
}

function isArrayAndNotEmpty($array) {
    return (is_array($array) && (!empty($array)));
}

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }
    return (substr($haystack, -$length) === $needle);
}

function getLang($lang, $key){
    if(!$key || $key == 'undefined' || $key == null){
        return $key;
    }
    return lang($key);
}

/**
 * 自定义数组过滤空数据
 * @param type $arr
 */
function myArrayFilter($arr) {
    return array_filter($arr, function ($value) {
        return '' !== $value && !is_null($value);
    });
}

function ajax_return($msg="", $code=0, $data=[]){
    throw new \app\common\exception\MessageException($msg, $code);
}

/**
 * 生成CSV
 * @param type $header
 * @param type $list
 * @param type $file_name
 * @return boolean
 */
function genCsv($header, $list, $file_name = "data_list", $output = true, $dir = "storage/xlsx") {
    if (empty($header)) {
        return false;
    }

    $fileData = "\xEF\xBB\xBF" . implode(",", array_values($header));
    $keys = array_keys($header);
    foreach ($list as $row) {
        $_row = [];
        foreach ($keys as $k) {
            $_row[] = "\t".($row[$k]);
        }
        $fileData .= ("\n" . implode(",", $_row));
    }

    if (!$output) {
        $save_path = public_path();
        if (!file_exists($save_path . $dir)) {
            @mkdir($save_path . $dir,0777,true);
        }
        $file_name = "{$dir}/{$file_name}.csv";
        file_put_contents($save_path . $file_name, $fileData);
        return $file_name;
    } else {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-type:text/csv");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename="' . $file_name . '.csv"');
        header("Content-Transfer-Encoding:binary");
        echo $fileData;
        exit;
    }
}

/**
 * Excel表格导出
 * @param $header 表头
 * @param $data 数据
 * @param $filename 表名
 */
function Excel($header,$data,$filename = 'test'){
    $excel = new Spreadsheet();
    for($i = 0;$i < count($header);$i++) {
        $excel->getActiveSheet()->getColumnDimension(get_zimu($i))->setWidth(20);
        $excel->getActiveSheet()->setCellValue(get_zimu($i).'1',"$header[$i]");
    }
    if($data) {
        for ($i = 2; $i <= count($data) + 1; $i++) {
            $j = 0;
            foreach ($data[$i - 2] as $key => $value) {
                $excel->getActiveSheet()->setCellValue(get_zimu($j) . $i, "$value");
                $j++;
            }
        }
    }

    header('Content-Type:application/vnd.ms-excel');
    header('Content-Disposition:attachment;filename=' . $filename.'.xlsx');
    header('Cache-Control:max-age=0');
    $writer = IOFactory::createWriter($excel, 'Xlsx');
    $writer->save('php://output');exit;
}

function get_zimu($index, $start = 65)
{
    $str = '';
    if (floor($index / 26) > 0){
        $str .= get_zimu(floor($index / 26) - 1);
    }

    return $str . chr($index % 26 + $start);
}

//二维数组去重
function unique_array_key($_2d_array, $unique_key) {
    $tmp_key[] = array();
    foreach ($_2d_array as $key => &$item) {
        if ( is_array($item) && isset($item[$unique_key]) ) {
            if ( in_array($item[$unique_key], $tmp_key) ) {
                unset($_2d_array[$key]);
            } else {
                $tmp_key[] = $item[$unique_key];
            }
        }
    }
    return $_2d_array;
}

//存日志记录字段操作
function updata($model = '',$fodata = [],$redata = []){
    $str = $str2 = '';$field['table'] = '';
    if(!empty($model)) {
        $field = $model->getField();
        $forward_data = $rear_data = [];
        foreach ($field['field'] as $k => $v) {
            if (isset($fodata['id']) && isset($redata['id'])) {
                $forward_data['id'] = $fodata['id'];
                $rear_data['id'] = $redata['id'];
            }

            if (isset($fodata['uid']) && isset($redata['uid'])) {
                $forward_data['uid'] = $fodata['uid'];
                $rear_data['uid'] = $redata['uid'];
            }

            $key1 = isset($fodata[$k]) ? $fodata[$k] : '';
            $key2 = isset($redata[$k]) ? $redata[$k] : '';
            if ($key1 != $key2) {
                $forward_data[$v] = $key1;
                $rear_data[$v] = $key2;
                if (!empty($field['set_key']) && isset($field['set_key'])) {
                    foreach ($field['set_key'] as $f) {
                        if (isset($f[$k])) {
                            $forward_data[$v] = isset($f[$k][$forward_data[$v]]) ? $f[$k][$forward_data[$v]] : '';
                            $rear_data[$v] = isset($f[$k][$rear_data[$v]]) ? $f[$k][$rear_data[$v]] : '';
                        }
                    }
                }
            }
        }

        if ($forward_data && $rear_data) {
            foreach ($forward_data as $k1 => $v1) {
                if ($v1) {
                    $str .= $k1 . ':' . $v1 . ' | ';
                }
            }
            foreach ($rear_data as $k2 => $v2) {
                if ($v2) {
                    $str2 .= $k2 . ':' . $v2 . ' | ';
                }
            }
            $str = trim($str, ' | ');
            $str2 = trim($str2, ' | ');
        }
    }
    return ['forward_data' => $str, 'rear_data' => $str2, 'table' => $field['table']];

}

function checkPhone($str){
    if (!preg_match("/^[0-9]+$/", $str)) { // 限制手机号格式
        return false;
    }

    $sys = (new \app\common\redis\CommomRedis())->systemInfo();
    $phone_num_rule = isset($sys['phone_num_rule'])?$sys['phone_num_rule']:'';
    $phone_rule = isset($sys['phone_rule'])?$sys['phone_rule']:'';
    $flag_len = true;
    if(!empty($phone_num_rule)){ // 判断位数
        $phone_num_rule = explode(',',$phone_num_rule);
        $phone_len = strlen($str);
        if(!in_array($phone_len, $phone_num_rule)){
            $flag_len = false;
        }
    }

    $flag_num = true;
    if(!empty($phone_rule)){ // 判断首位
        $phone_rule = explode(',',$phone_rule);
        $phone_num = substr($str,0,1);
        if(!in_array($phone_num, $phone_rule)){
            $flag_num = false;
        }
    }

    if($flag_len && $flag_num){
        return true;
    }else{
        return false;
    }
}

function checkUsername($str){
    if(!preg_match("/^[A-Za-z0-9]+$/u",$str)){
        return false;
    }
    return true;
}

function checkString($str){
    if(!preg_match("/^(([a-z]+[0-9]+)|([0-9]+[a-z]+))[a-z0-9]*$/i",$str)){
        return false;
    }
    return true;
}

/**
 * 隐藏邮箱地址
 * @param $mail
 * @return array|mixed|string|string[]
 */
function hideMail($mail){
    if (empty($mail)) {
        return $mail;
    }
    if (!strexists($mail, '@')) {
        return hideStr($mail);
    }
    $arr = explode("@", $mail);
    return hideStr($arr[0]) . "@" . $arr[1];
}

/**
 * 隐藏字符串
 * @param $str
 * @param int $length 首尾隐藏长度
 * @return array|mixed|string|string[]
 */
function hideStr($str, $length = 2) {
    if (empty($str)) {
        return $str;
    }
    return mb_substr($str, 0, $length, 'UTF-8').'****'.mb_substr($str, 0-$length, $length, 'UTF-8');
//    return substr_replace($str, '****', 2, -2);
}
/**
 * 系统非常规MD5加密方法
 * @param  string $str 要加密的字符串
 * @return string
 */
function think_ucenter_md5($str, $key = '') {
    return '' === $str ? '' : md5(sha1($str) . $key);
}

/**
 *
 * @param type $string
 * @param type $find
 * @return type
 */
function strexists($string, $find) {
    return !(strpos($string, $find) === FALSE);
}

function getWebUrl($isAuto = true) {
    $web_url = false;
    if ($isAuto) {
        $web_url = request()->domain();
    }
    if (!$web_url) {
        $web_url = config('app.url');
    }
    if (!$web_url) {
        $sys = (new \app\common\redis\CommomRedis())->systemInfo();
        $web_url = $sys['domain'] ?? $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'];
    }
    return rtrim($web_url, '/');
}

/**
 * 获取前端访问地址
 * @param $isAuto 自动获取前端访问地址
 * @return string
 */
function getAppUrl($isAuto = true) {
    $web_url = "";
    if ($isAuto) {
        if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER']) {
            $web_url = $_SERVER['HTTP_REFERER'];
        } else if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN']) {
            $web_url = $_SERVER['HTTP_ORIGIN'];
        }
    }
    if (empty($web_url)) {
        $sys = (new \app\common\redis\CommomRedis())->systemInfo();
        $web_url = $sys['web_url'] ?? $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'];
        if (!$web_url) {
            $web_url = request()->domain();
        }
    }
    return rtrim(trim($web_url), '/');
}

/**
 * 获取静态资源url
 * @return type
 */
function getStaticUrl() {
    $static_url = config('app.static_url');
    if (!$static_url) {
        $static_url = getWebUrl();
    }
    return $static_url;
}

/**
 * 生成资源文件地址
 * @param type $path 文件路径
 * @param type $dir 目录
 * @param type $url_type 0:返回相对路径，1返回远程访问地址，2返回本地绝对路径
 * @return string
 */
function toMedia($path, $dir = '/storage/', $url_type = 1) {
    if (empty($path) || !is_string($path)) {
        return "";
    }

    if (is_numeric($dir)) {
        $url_type = $dir;
        $dir = '/storage/';
    }
    $t = strtolower($path);
    if (strexists($t, 'http://') || strexists($t, 'https://')) {
        return $path;
    }
//    $request = request();
    $url = $dir . ltrim($path, '/');
    if ($url_type == 1) {
        return toWebUrl($url);
    } elseif ($url_type == 2) {
        return public_path() . ltrim($url, '/');
    }
    return $url;
}

function toWebUrl($uri) {
    $web_url = getStaticUrl();
    return rtrim($web_url, '/') . "/" . ltrim($uri, '/');
}

function object_array($array) {
    $array = json_decode(json_encode($array),TRUE);
    return $array;
}

function quempty($data){
    if(!is_array($data) || empty($data)){
        return;
    }
    $list = [];
    foreach ($data as $k=>$v){
        if($v != '' && $v != ' '){
            $list[] = $v;
        }
    }
    return $list;
}

function getOnlineUids(){
    $redis_read = env('redis_read', 0);
    $count = (new \app\api\redis\ApiRedis)->getOnline();
    return $count;
}


/**
 * 过滤XSS
 * @staticvar type $obj
 * @param type $val
 * @return type
 */
function removeXSS($val) {
    if (!class_exists("HTMLPurifier")) {
        return $val;
    }
    static $obj = null;
    if ($obj === null) {
        $obj = new HTMLPurifier();
    }

    // 返回过滤后的数据
    return $obj->purify($val);
}

function get_rand($proArr) {
    $result = '';
    //概率数组的总概率精度
    $proSum = array_sum($proArr);
    //概率数组循环
    foreach ($proArr as $key => $proCur) {
        $randNum = mt_rand(1, $proSum);
        if ($randNum <= $proCur) {
            $result = $key;
            break;
        } else {
            $proSum -= $proCur;
        }
    }
    unset ($proArr);
    return $result;
}

//判断电脑还是手机访问
function is_mobile(){
    // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
    if (isset ($_SERVER['HTTP_X_WAP_PROFILE']))
    {
        return true;
    }
    // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
    if (isset ($_SERVER['HTTP_VIA']))
    {
        // 找不到为flase,否则为true
        return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
    }
    // 脑残法，判断手机发送的客户端标志,兼容性有待提高
    if (isset ($_SERVER['HTTP_USER_AGENT']))
    {
        $clientkeywords = array ('nokia',
            'sony',
            'ericsson',
            'mot',
            'samsung',
            'htc',
            'sgh',
            'lg',
            'sharp',
            'sie-',
            'philips',
            'panasonic',
            'alcatel',
            'lenovo',
            'iphone',
            'ipod',
            'blackberry',
            'meizu',
            'android',
            'netfront',
            'symbian',
            'ucweb',
            'windowsce',
            'palm',
            'operamini',
            'operamobi',
            'openwave',
            'nexusone',
            'cldc',
            'midp',
            'wap',
            'mobile'
            );
        // 从HTTP_USER_AGENT中查找手机浏览器的关键字
        if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT'])))
        {
            return true;
        }
    }
    // 协议法，因为有可能不准确，放到最后判断
    if (isset ($_SERVER['HTTP_ACCEPT']))
    {
        // 如果只支持wml并且不支持html那一定是移动设备
        // 如果支持wml和html但是wml在html之前则是移动设备
        if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html'))))
        {
            return true;
        }
    }
    return false;
}

/**
 *
 * @param type $userid
 * @param type $action
 * @param type $timeout
 * @return type
 */
function userLock($userid, $action, $timeout = 5) {
    $locker = new util\Locker("{$action}:{$userid}");
    if (!$locker->lock($timeout)) {
        $locker = null;
    }
    return $locker;
}


function timediff($begin_time,$end_time){
    if($begin_time <$end_time){
        $starttime =$begin_time;
        $endtime =$end_time;
    }else{
        $starttime =$end_time;
        $endtime =$begin_time;
    }
    //计算天数
    $timediff =$endtime-$starttime;
    $days =intval($timediff/86400);
    //计算小时数
    $remain =$timediff%86400;
    $hours =intval($remain/3600);
    //计算分钟数
    $remain =$remain%3600;
    $mins =intval($remain/60);
    //计算秒数
    $secs =$remain%60;
    $res =array("day" =>$days,"hour" =>$hours,"min" =>$mins,"sec" =>$secs);
    return $res;
}

/**
 * @desc 生成指定随机字符串
 * @param $length
 * @return string|null
 */
function get_rand_char($length=12) {
    $str = null;
    $strPol = "23456789abcdefghjkmnpqrstuvwxyz23456789ABCDEFGHJKLMNPQRSTUVWXYZ";
    $max = strlen($strPol) - 1;

    for ($i = 0; $i < $length; $i++) {
        $str .= $strPol[rand(0, $max)];
    }
    $pattern = '/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9a-zA-Z]{8,16}$/';
    if(!preg_match($pattern,$str)){
        get_rand_char();
    }

    return $str;
}

/**
 * 语言转换 用于前端用了 vue解析的时候 在后台先转换
 * */
 function lang_change(&$arr){
    foreach($arr as &$v){
        $v = lang($v);
    }
}

    /**
     * 获取ENV配置中 正常展示的菜单
     * @return int[]|string[]
     */
    function getNodeShow(){
        $arr = [0];
        $type = array_diff(config::get('custom.three_game'), $arr);
        $therr_game_state = count($type) > 0 ? 1 : 0;
        //先去掉值为0的  再获取键名
        $array = array_keys(array_diff([
            'normal' => 1,
            'turntable' =>  env('is_turntable_open', 0),
            'shaba' => $therr_game_state,
            'third' => env('IS_THIRD_OPEN', 0),
            'offline_pay' => env('IS_OFFLINE_PAY', 0),
            'online_pay' => env('IS_ONLINE_PAY', 0),
            'agent_pay' => env('IS_AGENT_PAY', 0),
        ],[0]));
        return $array;
    }

    /**
     * 判断按钮是否展示
     * @param $node //要控制的节点
     * @return bool
     */
    function NodeShowState($node = ''): bool
    {
        [$admin,$node_url_arr] =  [Session::get('adminInfo'),Session::get('node_url_arr')];
        if($admin['role_id'] > 1){
            return in_array(trim($node,''),$node_url_arr);
        }
        return true;
    }


    /**
     * 判断表名是否存在
     * @param string $full_table_name 表名称带前缀
     * @param string $config 数据库配置
     * @return bool
     */
    function existTable($full_table_name, $config = "") {
        $exist = \think\facade\Db::connect($config)->query("show tables like '%{$full_table_name}%'");
        return empty($exist) ? false : true;
    }
/**
 * 检测每次修改的时候 是否存在数据 作用于修改数据的时候 先校验有没有数据 再返回model
 * 新的 比权限板块 优先级高
 * @param Object $model
 * @param array $where
 * @param string $field
 * @return array
 * @throws \think\Exception
 */
function getOneData(Object $model, array $where, string $field = '*')
{
    try{
        $model = $model->where($where)->field($field)->find();
        $toArray = $model->id > 0 ? $model->toArray() : [];
        return [$model, $toArray];
    }catch(Exception $e){
        throw new \think\Exception(lang('参数异常'), 0);
    }
}

/** 获取request参数信息
 * @param $name 参数名，多个用,隔开
 * @return array|mixed
 * @author twt326
 * @date 2022-08-21 18:47
 */
function getp($name){
    $names=explode(",",$name);
    if (count($names)>1){
        $rlts=[];
        foreach ($names as $v){
            $rlts[$v]=\app\common\FastGame\Base\Params::$Request->get($v);

        }
        return $rlts;
    }else{
        return \app\common\FastGame\Base\Params::$Request->get($name);
    }
}

function getc($classname){
    $fcn="app\common\FastGame\\".$classname;
    return class_exists($fcn)?$fcn:null;
}


function gettimezone($zone){
    $zone = str_replace("+", "", $zone);
    $data = [
        '-1'=>'Atlantic/Cape_Verde',
        '-2'=>'Atlantic/South_Georgia',
        '-3'=>'America/Sao_Paulo',
        '-4'=>'America/Caracas',
        '-5'=>'America/Mexico_City',
        '-6'=>'America/Ojinaga',
        '-7'=>'America/Los_Angeles',
        '-8'=>'America/Anchorage',
        '-9'=>'Pacific/Gambier',
        '-10'=>'Pacific/Tahiti',
        '-11'=>'Pacific/Niue',
        '-12'=>'Pacific/Midway',
        '0'=>'UTC',
        '1'=>'Africa/Algiers',
        '2'=>'Africa/Cairo',
        '3'=>'Europe/Moscow',
        '4'=>'Indian/Mauritius',
        '5'=>'Indian/Maldives',
        '6'=>'Asia/Bishkek',
        '7'=>'Asia/Bangkok',
        '8'=>'Asia/Shanghai',
        '9'=>'Asia/Tokyo',
        '10'=>'Pacific/Guam',
        '11'=>'Pacific/Guadalcanal',
        '12'=>'Pacific/Auckland',
    ];
    return $data[$zone] ?? 'UTC';
}


function array_sort($array, $keys, $type='asc') {
    $keysvalue = array_column($array, $keys);
    if($type == 'asc') {
        array_multisort($keysvalue, SORT_ASC, $array);
    } else {
        array_multisort($keysvalue, SORT_DESC, $array);
    }
    return $array;
}

/**
 * 保留两位小数不四舍五入
 * @param type $num
 * @param type $decimal 小数精度
 * @param type $round 是否四舍五入，默认是
 * @param type $floatval 是否使用floatval函数转换
 * @return float|string
 */
function sprintf4f($num, $decimal = 2, $round = true, $floatval = false) {
    if ($round) {
        $val = sprintf("%.{$decimal}f", $num);
    }else{
        $val = sprintf("%.{$decimal}f", substr(sprintf("%." . ($decimal + 1) . "f", $num), 0, -1));
    }
    return $floatval ? floatval($val) : $val;
}

/**
 * 发布自动申请活动队列任务
 * @param int $uid 用户ID
 * @param int|array $type 活动类型 1 首充，2再存， 3一般优惠
 * @param int|null $recharge_id 充值记录ID
 * @param int $time 发送时长
 * @param int $actionId 强制执行的活动id
 * @return void
 */
function action_task($uid, $type, int $recharge_id = null, $time = 3, $actionId = 0) {
    // 处理再存活动时，增加缓存限制，防止重复发布无效任务
    if ($type == 2 && is_null($recharge_id)) {
        $redis = new \app\api\redis\ApiRedis();
        $redis_key = "action_task{$uid}:{$type}";
        if ($redis->has($redis_key)) {
            return;
        }
        $redis->set($redis_key, 1);
    }
    event(new \app\event\ActivityTaskEvent([
        'type' => $type,
        'uid' => $uid,
        'recharge_id' => $recharge_id,
        'time' => $time,
        'action_id' => $actionId
    ]));
}



/*
* 获取投注输的金额
*/
function getTransportTzloss($where=[], $where1=[]){

//    $tz_loss = \think\facade\Db::name('third_stats_global')->where($where)->where($where1)->sum('su_syswin');
//    return max(floatval($tz_loss), 0);


    //指定时间内最后一次有效的投注输的金额
    //最后一次有效记录
    $table = \app\common\service\BetService::getThirdStatsTable();
    $data_loss = \think\facade\Db::name($table['_stats_global'])->field('su_syswin,su_dt')->where($where)->where($where1)->order('su_dt DESC')->find();
    if(empty($data_loss)){
        return 0;
    }
    //得到最近一次有效的投注日期
    $su_dt = $data_loss['su_dt'];
    $map =[
        'su_dt'=>$su_dt,
    ];
    //根据有效日期统计投注输的金额
    $tz_loss = \think\facade\Db::name($table['_stats_global'])->where($map)->where($where)->where($where1)->sum('su_syswin');
    return max(floatval($tz_loss), 0);
}

if( !function_exists('myEncrypt') ){
    function myEncrypt($string, $key = 'T-1dwdyjhdgba1n6'){
        // 加密
        return openssl_encrypt($string, 'aes-256-cbc', $key, 0, $key);
    }
}

if( !function_exists('myDecrypt') ){
    function myDecrypt($data, $key = 'T-1dwdyjhdgba1n6'){
        // 解密
        return openssl_decrypt($data, 'aes-256-cbc', $key, 0, $key);

    }
}

if( !function_exists('handleManyUrl') ){
    function handleManyUrl($urlString){
        $link = str_replace('，', ',', $urlString);
        $urlArr = explode(',', $link);
        $_key = rand(0, count($urlArr) - 1);
        return html_entity_decode($urlArr[$_key]);
    }
}

//覆盖原来的lang方法
function lang(string $name, array $vars = [], string $lang = '', $isCreate = true)
{
    if (empty($name)) {
        return '';
    }
    $debug = env("auto_lang");
    $langValue = \think\facade\Lang::get($name, $vars, $lang);
    if ($debug && $langValue == $name && $isCreate) {
        $info = \app\common\model\LangItemsModel::where('title', $name)->find();
        if (!$info) {
            \app\common\model\LangItemsModel::create([
                'title' => $name
            ]);
        }
    }
    return $langValue;
}

//获取分享链接域名
function getShareDomain(){
    $domainEnv = env('SHARE_URL', '');
    if( $domainEnv ){
        $domain = handleManyUrl($domainEnv);
    }else{
        $domain = getAppUrl(false);
    }
    return $domain;
}

//随机浮点数
function rand_float($st_num=0,$end_num=1,$mul=100)
{
    if ($st_num>$end_num) return false;
    return mt_rand($st_num*$mul,$end_num*$mul)/$mul;
}

//获取来源域名
if( !function_exists('getOriginalDomain') ){
    function getOriginalDomain(){
        $header = request()->header();
        $domain = '';
        if( isset($header['Origin']) || isset($header['origin']) ){
            $domain = $header['Origin'] ?? $header['origin'];
        }
        if( !$domain && (isset($header['Referer']) || isset($header['referer'])) ){
            $domain = $header['Referer'] ?? $header['referer'];
            //去掉最后一个/
            $domain = substr($domain, 0, -1);
        }
        if( !$domain && isset($header['access-control-allow-origin']) ){
            $domain = $header['access-control-allow-origin'];
        }
        //将http替换成https
        return str_replace('http://', 'https://', $domain);
    }
}

//判断随机是否中奖，格式数字，1为1%
if( !function_exists('judgeOddsIsTrue') ){
    function judgeOddsIsTrue($odds = 100){
        $max = 100;
        $min = 1;
        if( rand($min, $max) <= $odds ){
            return true;
        }
        return false;
    }
}

if( !function_exists('returnAmountByRange') ){
    /**
     * 根据范围返回金额
     * @param $min, 最小值
     * @param $max, 最大值
     * @param int $decimalPlaces 小数点
     * @return float|int
     */
    function returnAmountByRange($min, $max, $decimalPlaces = 2){
        if( $min == 0 || $max == 0 || $min > $max  ){
            return 0;
        }
        return mt_rand($min * pow(10, $decimalPlaces), $max * pow(10, $decimalPlaces)) / pow(10, $decimalPlaces);
    }
}

if( !function_exists('getIpDomain') ){
    /**
     * 根据ip获取ip域，例127.0.0.1为127.0.0
     * @param $ip
     * @return string
     */
    function getIpDomain($ip){
        // 使用 explode 函数拆分 IP 地址
        $parts = explode('.', $ip);
        // 提取前三段并连接成字符串
        return implode('.', array_slice($parts, 0, 3));
    }
}

if( !function_exists('hidden_array_data') ){
    /**
     * 自定义隐藏数据
     * @param $data
     * @param array $hiddenArr
     * @return array
     */
    function hidden_array_data($data, array $hiddenArr = [])
    {
        foreach ($hiddenArr as $key){
            unset($data[$key]);
        }
        return $data;
    }
}

//不强制登录下获取用户id
if( !function_exists('get_user_id_no_enforce_login') ){
    function get_user_id_no_enforce_login(){
        //判断用户
        $uid = 0;
        $header = request()->header();
        try {
            $token = TokenCheck::run($header);
            if( $token ){
                $uid = $token->uid;
            }
        }catch (\Exception $e){}
        return $uid;
    }
}
