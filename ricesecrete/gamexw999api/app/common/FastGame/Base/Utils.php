<?php


namespace app\common\FastGame\Base;


use app\common\lib\Respons;
use app\model\SysSets;
use BN\Red;
use Phinx\Util\Util;
use spec\League\Flysystem\Cached\CachedAdapterSpec;
use think\facade\Db;

class Utils
{
    private static $sysinfo=false;
    public static $inicache=[];

    public static $debuginfos=[];

    // 必须记录的日志类型
    public static $debug_tag = ['jsback', 'jsfy', 'jsfypay', 'jsfy_water', 'notify_error', 'ThirdErrLogs', 'error'];

    /** 后端日志封装
     * @param $tag 日志标志，作为文件名的一部分
     * @param $msg 日志消息，任意类型值
     * @param $byDay 是否按日分割日志，默认为按月分割日志
     */
    public static function L($tag, $msg, $byDay = true, $debug = false){
        $dir = 'log';
        if (strexists($tag, '/')) {
            $tmp = explode("/", $tag);
            $dir .= "/{$tmp[0]}";
            $tag = $tmp[1];
        }
        $log_dir = runtime_path($dir);
        if (!file_exists($log_dir)) {
            @mkdir($log_dir, 0777, true);
        }
        try {
            $filename = $log_dir . ($tag ? $tag : 'runtime') . "_" . ($byDay ? date("Ymd") : date("Ym")) . ".log";
            $content = date("Y-m-d H:i:s") . " [".gethostname()."]  " . (is_string($msg) || is_numeric($msg) ?$msg: var_export($msg, true) ) . "\r\n";
            if (env('APP_DEBUG',false) && env('APP_TRACE',false)) {
                array_push(self::$debuginfos, $content);
                self::$debuginfos = array_slice(self::$debuginfos,env('APP_TRACE_LEN',10)*-1);
            }
            if (php_sapi_name() == "cli") {
                echo $content . "\n";
            }
            // DEBUG 模式记录所有日志
            if (env("app.debug") || $debug || in_array($tag, self::$debug_tag)) {
                file_put_contents($filename, $content, FILE_APPEND);
            }
        }catch (Exception $ex){
        }
    }


    //专用于写静态文件的方法
    public static function writeToStaticFile($filename,$data){
        $dir=self::getStaticFilePath();// dirname(__FILE__) . DIRECTORY_SEPARATOR . './../../public/staticCaches/';
        @mkdir($dir,077,true);
        file_put_contents($dir.$filename,$data);
    }

    //获取静态文件存放目录
    public static function getStaticFilePath(){
        return public_path('staticCaches');//dirname(__FILE__) . DIRECTORY_SEPARATOR . './../../public/staticCaches/';
    }

    //获取一个目录下的所有文件列表
    public static function getDirFiles($path){
        if(!is_dir($path)){
            return false;
        }
        //scandir方法
        $arr = array();
        $data = scandir($path);
        foreach ($data as $value){
            if($value != '.' && $value != '..'){
                $arr[] = $value;
            }
        }
        return $arr;
    }

    public static function microtime(){
        return intval(microtime(true)*1000);
    }


    /**获取客户端IP
     * @return mixed|string|null
     * @author twt326
     * @date 2022-05-13 22:29
     */
    public static function get_ip()
    {
        static $ip = null;
        if (null !== $ip) {
            return $ip;
        }
        $httpAgentIp = env('http_agent_ip');
        if ($httpAgentIp && isset($_SERVER[$httpAgentIp])) {
            $ip = $_SERVER[$httpAgentIp];
        }

        if (empty($ip)) {
            if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                foreach ($arr AS $xip) {
                    if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                        $ip = trim($xip);
                        break;
                    }
                }
            } elseif (isset($_SERVER['HTTP_CDN_SRC_IP'])) {
                $ip = $_SERVER['HTTP_CDN_SRC_IP'];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        }
        return empty($ip) ? '0.0.0.0' : $ip;
    }

    /** 加密或验证用户登录记录
     * @param $code
     * @param $codex
     * @param null $md5code 不为Null表示验证
     * @return bool|string
     * @author twt326
     * @date 2022-05-13 22:40
     */
    public static function checkUserCode($code,$codex,$md5code=null){
        if ($md5code){
            //验证
            return md5(md5($code).$codex)==$md5code;
        }else{
            //加密
            return md5(md5($code).$codex);
        }
    }

    /** 设置或验证utoken
     * @param null $uid 不为null表示设置 为null 表示验证
     * @param null $utoken 不为null表示验证
     * @return bool|string
     * @author twt326
     * @date 2022-05-13 23:01
     */
    public static function uToken($uid=null,$utoken=null){
        //Utils::L('test',[$uid,$utoken]);
        $ip=Utils::get_ip();
        $ips=str_replace(":","_",str_replace('.',"_",$ip));
        if ($uid){//登录后写缓存
            //设置token
            session('uid',$uid);
            $token=md5(implode("_",[session_id(),$uid,Utils::get_ip()]));
            //读取配置
            $cfg=SysSets::getSetByCode('G_LOGIN_IP_DISTINCT');
            $c=Redis::hget('ipLimit',$ips,2);
            if ($c){
                //有重复登录，则顶掉前一人

                if ($cfg) {//如果IP限制
                    //Redis::hdel('loginTokens', [$c], 2);
                    self::clearRedisOldToken($c);
                }
            }
            $c=Redis::hget('loginIds',$uid,2);
            if ($c){
                //清除无效redis
                self::clearRedisOldToken($c);
            }
            Redis::hset('loginIds',$uid,$token,2);//缓存历史数据
            if ($cfg)Redis::hset('ipLimit',$ips,$token,2);
            Redis::limitCache('loginTokens',$token,[
                "u"=>$uid,
                "i"=>$ips
            ],false,28800,2);
            return $token;
        }elseif($utoken !=null){
            //验证token
            $c=Redis::limitCache('loginTokens',$utoken,null,true,28800,2);
            if ($c){
                /*if ($c['i']==$ips) {//ip更换后token失效
                    session('uid', $c['u']);
                    return true;
                }*/
                //TODO 测试代码,更换IP后TOKEN不失效，用于测试，正式更改为上述注释代码
                session('uid', $c['u']);
                return true;
            }
            self::clearRedisOldToken($utoken);

            return false;

        }else{
            return false;
        }
    }

    /** 清除redis中的无效token
     * @param $token
     * @author twt326
     * @date 2022-05-31 16:40
     */
    public static function clearRedisOldToken($token){
        //self::L('test',"crot:".$token);
        //self::L('test',getSaaSdbconnection());
        $c=Redis::hget('loginTokens',$token,2);
        //self::L('test',$c);
        if ($c){
            $clt=json_decode($c,true);
            $clt=$clt["v"];
            //self::L('test',$clt);
            $c=Redis::hget('ipLimit',$clt["i"],2);
            if ($c){
                //self::L('test',['ipLimit',$clt['i']]);
                //self::L('test',$c);
                $cc=Redis::limitCache('loginTokens',$c,null,false,28800,2);
                if (!$cc){//如果IP限制中存放的TOKEN也已经过期，则顺便删除
                    Redis::hdel('loginTokens',$c,2);
                    Redis::hdel('ipLimit',$clt["i"]);
                }
            }
            $c=Redis::hget('loginIds',$clt['u'],2);
            //self::L('test',['loginIds',$clt['u']]);
            //self::L('test',$c);
            if ($c) {
                Redis::hdel('loginIds',[$clt['u']],2);
                self::clearRedisOldToken($c);
            }
        }
        Redis::hdel('loginTokens',[$token],2);
    }

    /** 生成随机字符串
     * @param int $len 默认4 要生成的字符串长度
     * @return string
     * @author twt326
     * @date 2022-05-14 15:41
     */
    public static function getRandomStr($len=4){
        $str="";
        for($i=0;$i<$len;$i++){
            $x=mt_rand(1,62);
            $str.=chr($x>36?$x+60:($x>10?$x+54:$x+47));
        }
        return $str;
    }

    /** 获取推广码
     * @param $intkey 唯一ID，建议使用USERID
     * @author twt326
     * @date 2022-05-14 15:48
     */
    public static function getPcode($intkey){
        return strtoupper(self::getRandomStr(3).base_convert($intkey,10,36));
    }

    /**
     * 代币精度处理方法(暂不启用)
     * @param $value
     * @param $dec
     * @return float 返回两位精度的数值
     * @author twt326
     * @date 2022-05-16 23:54
     */
    /*public static function valueToAmount($value,$dec){
        $len=strlen($value);
        if ($len>$dec){
            $v=substr($value,0,$len-$dec).".".substr($value,$dec*-1);
        }else{
            $v="0.".str_pad($value,$dec-$len,"0",STR_PAD_LEFT);
        }
        return floatval(number_format($v,2,".",""));
    }*/

    /**代币精度处理方法（原始精度固定认为是6）
     * @param $value
     * @param int $dec
     * @return float
     * @author twt326
     * @date 2022-05-16 23:56
     */
    public static function valueToAmount($value,$dec=6){
        //return floatval(number_format($value/1000000,2,".",""));
        return floatval(bcdiv($value, bcpow(10,$dec)));
    }
    /*public static function valueToAmount($value,$dec=6){
        return floatval(number_format($value/1000000,2,".",""));
    }*/


    /*public static function decimalValue(string $int, int $scale = 18): string {
    //备用于万一出现需要大数处理，但应该是用不上
        return (new BcNumber($int))->divide(pow(10, $scale), $scale)->value();
    }*/

    /**
     * 根据当前时间获取客损结算当前结算时间段和上一个时间段
     * @param int null 有值表示获取指定时间所在的时间段
     * @return array
     * @author twt326
     * @date 2022-05-23 20:57
     */
    public static function getKSTimePeriods($time=null){
        if ($time==null) $time=time();
        $d=date("d",$time);
        if ($d>15){
            $tp=[
                "cnt"=>[
                    "s"=>mktime(0,0,0,date('m',$time),16,date('Y',$time)),
                    "e"=>mktime(0,0,0,date('m',$time)+1,1,date('Y',$time)),
                    "dt"=>date("Ymd",mktime(0,0,0,date('m',$time)+1,1,date('Y',$time))-1)//结算时间点日期
                ],
                "yst"=>[
                    "s"=>mktime(0,0,0,date('m',$time),1,date('Y',$time)),
                    "e"=>mktime(0,0,0,date('m',$time),16,date('Y',$time)),
                    "dt"=>date("Ymd",mktime(0,0,0,date('m',$time),16,date('Y',$time))-1)//结算时间点日期
                ]
            ];
        }else{
            $tp=[
                "cnt"=>[
                    "s"=>mktime(0,0,0,date('m',$time),1,date('Y',$time)),
                    "e"=>mktime(0,0,0,date('m',$time),16,date('Y',$time)),
                    "dt"=>date("Ymd",mktime(0,0,0,date('m',$time),16,date('Y',$time))-1)//结算时间点日期
                ],
                "yst"=>[
                    "s"=>mktime(0,0,0,date('m',$time)-1,16,date('Y',$time)),
                    "e"=>mktime(0,0,0,date('m',$time),1,date('Y',$time)),
                    "dt"=>date("Ymd",mktime(0,0,0,date('m',$time),1,date('Y',$time))-1)//结算时间点日期
                ],
            ];
        }
        return $tp;
    }

    //

    /**根据KEY字符串从数据中取出数据
     * @param string $key 用.分隔的多级KEY
     * @param array $data
     * @return mixed|null
     * @author twt326
     * @date 2022-09-08 17:57
     */
    public static function getDataFromArrayByStrKey($key,$data){
        if (is_string($key) && strpos($key,".")){
            $key=explode(".",$key);
            return self::getDataFromArrayByStrKey($key,$data);
        }else if (is_array($key)){
            if (count($key)>1 && isset($data[$key[0]])){
                return self::getDataFromArrayByStrKey(array_slice($key,1),$data[$key[0]]);
            }else{
                return isset($data[$key[0]])?$data[$key[0]]:null;
            }
        }else if (strtolower($key)=='rawdata') {//原样返回数据，一般用于非JSon返回数据
            return $data;
        }else{
            return isset($data[$key])?$data[$key]:null;
        }
        return null;
    }

    /** 自定义获取签名字符串方法
     * @param array $values
     * @param array $exKeys 排除KEY
     * @return string
     * @author twt326
     * @date 2022-09-14 10:49
     */
    public static function custGetSignStr($values,$incNullValue=false,$exKeys=[]){
        //参数值直接拼接，真假转换为 true false,忽略数组和对象，忽略空字段
        $buff = "";
        foreach ($values as $k => $v)
        {
            if(!in_array($k,$exKeys) && ($incNullValue || $v != "") && !is_array($v)){
                $buff .= is_bool($v)?($v?'true':'false'):$v;
            }
        }

        //$buff = trim($buff, "&");
        return $buff;
    }


    /**
     * 生成指定长度的随机字符串
     * @param int $length
     * @param bool $numeric
     * @return string
     */
    public static function random($length, $numeric = FALSE) {
        $seed = base_convert(md5(microtime() . $_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));
        if ($numeric) {
            $hash = '';
        } else {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            $length--;
        }
        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $seed[mt_rand(0, $max)];
        }
        return $hash;
    }

    /**获取分割表后缀
     *
     * @param $table 要获取分表后缀的表名 支持dq_前缀和无前缀
     * @param $time 要获取分表后缀的时间 支持 YYYY-mm-dd YYYYmmdd YYYYmm/YYYYWW YYYY-mm/YYYY-WW timestamp
     * @param $type 按周模式获取表后缀（w）还是按月模式获取表后缀(m),默认月，目前仅有 dq_third_stats_fyfy(分佣表）和dq_third_stats_fyfylog(分佣日志表）需要用到此参数
     * @return string|false  string表示表名后缀（含_) false 表示数据正在分割中，此时调用者应返回前端提示：数据正在分割中，请等待完成！
     * @author twt326
     * @date 2023-01-02 21:47
     */
    public static function getCutTableEx($table,$time,$type='m'){
        if (!$time || $time==null) return false;
        if (substr($table,0,3)=="dq_"){
            $table=substr($table,3);
        }
        $len=strlen($time);
        switch ($len){
            case 6://YYYYmm   YYYYWW
                if ($type!='m') {
                    $time=self::nextweek($time,1,'ym');//按周获取周一所在月份
                }
                break;
            case 7://YYYY-mm YYYY-WW
                $time = str_replace('-', '', $time);
                if ($type!='m') {
                    $time=self::nextweek($time,1,'ym');//按周获取周一所在月份
                }
                break;
            case 8://YYYYmmdd
                if ($type=='m') {
                    $time = substr($time, 0, 6);
                }else{
                    $time=self::nextweek(strtotime(substr($time,0,4)."-".substr($time,4,2)."-".substr($time,6)),1,'ym');
                }
                break;
            default://YYYY-mm-dd or timestamp
                if (strpos($time,'-')){
                    //YYYY-mm-dd
                    if($type=='m') {
                        $time = str_replace('-', '', substr($time, 0, 7));
                    }else{
                        $time=self::nextweek(strtotime($time),1,'ym');
                    }
                }elseif(is_int($time)){
                    //timestamp
                    if ($type=='m') {
                        $time = date("Ym", $time);
                    }else{
                        $time=self::nextweek($time,1,'ym');
                    }
                }else{
                    return false;
                }
                break;
        }
        //分割表中是否有数据
        $sql="select ct_m from dq_cut_tables where ct_table='$table' and ct_m='$time'";
        $rlt=Db::query($sql);
        if (count($rlt)>0) {
            return "_".$time;
        }else {
            //数据表是否已存在
            $sql="select count(*) as num from information_schema.TABLES where table_schema='".env('database.database','')."' and table_name='dq_{$table}_{$time}'";
            $rlt=Db::query($sql);
            if ($rlt[0]['num']==0){
                return '';
            }else{
                return false;
            }
        }
    }

    /** 根据指定的时间和指定的前后周数获取周时间相关信息
     * @param $yw YYYYWW timestamp
     * @param int $num 前后周数 负数表示前面的周数，正数表示后面的周数
     * @param null $key 指定直接返回信息中的某个KEY
     * @return array|mixed yw:当前周字符串 ys当前周一0时时间戳 ym当前周一年月 ye当前周日23：59：59秒时间戳 next前缀指的是根据$num算出的目标周的相应结果
     * @author twt326
     * @date 2023-03-14 22:21
     */
    public static function nextweek($yw,$num=1,$key=null){
        //指定时间的年、周、开始结束时间 周一所在的年份为周所在年份
        if ($yw>999999){
            $w=intval(date("W",$yw));
            $wd=date("w",$yw);
            //得到周一时间
            $st=strtotime(date("Y-m-d 00:00:00",$yw))-86400*($wd==0?6:($wd-1));
        }else{
            $w=intval(substr($yw,4));
            //年度第一天
            $yfst=strtotime(date(substr($yw,0,4)."-01-01 00:00:00",$yw));
            $yfstwd=date("w",$yfst);
            //拿到年度第一周第一天
            if ($yfstwd==1){
                //一年的第一天刚好是周一
                $st=$yfst;
                //$st=$st+($w-1)*86400*7;
            }else{
                //当前时间为上年度最后一周
                $st=$yfst+86400*(7-($yfstwd==0?6:($yfstwd-1)));
            }
            $st=$st+($w-1)*86400*7;//拿到指定周数所在第一天
        }
        $et=$st+86400*7-1;
        $yw=date("YW",$st);
        $nextst=$st+$num*7*86400;
        $nextet=$nextst+86000*7-1;
        $rlt=[
            "yw"=>$yw,
            //"ysd"=>date("Y-m-d H:i:s",$st),
            "ys"=>$st,
            "ym"=>date("Ym",$st),
            //"yed"=>date("Y-m-d H:i:s",$et),
            "ye"=>$et,
            "nextyw"=>date("YW",$nextst),
            //"nextysd"=>date("Y-m-d H:i:s",$nextst),
            "nextys"=>$nextst,
            "nextym"=>date("Ym",$nextst),
            //"nextyed"=>date("Y-m-d H:i:s",$nextet),
            "nextye"=>$nextet
        ];
        return $key==null?$rlt:$rlt[$key];

    }

    /** 根据年份获取一年的周信息
     * @param int $year 年份
     * @param int $format 返回结果的title格式 0：YW 1:Y-W 2:Y年第W周
     * @return array [[t:周标题，用于展示，v:[yw:年周数字组合，用于调用相关方法时的参数，ys：当前周开始时间戳,ye：当前周结束时间戳23:59:59]]]
     * @author twt326
     * @date 2023-03-14 22:52
     */
    public static function getWeeksByYear($year,$format=0){
        $year=intval($year);
        $endflag=false;
        $cnt=1;
        $rlt=[];
        while(!$endflag){
            $week=self::nextweek($year.($cnt>9?$cnt:("0".$cnt)),1);
            if (intval(date("Y",$week['ys']))!=$year){
                $endflag=true;
            }else{
                array_push($rlt,[
                    "t"=>$format==0?$week['yw']:($format==1?date("Y-W",$week['ys']):date("Y年第W周",$week['ys'])),
                    "v"=>[
                        "yw"=>$week['yw'],
                        "ys"=>$week['ys'],
                        "ye"=>$week['ye']
                    ]
                ]);
            }
            if (intval(date("Y",$week['ys']))!=$year){
                $endflag=true;
            }else{
                array_push($rlt,[
                    "t"=>$format==0?$week['nextyw']:($format==1?date("Y-W",$week['nextys']):date("Y年第W周",$week['nextys'])),
                    "v"=>[
                        "yw"=>$week['nextyw'],
                        "ys"=>$week['nextys'],
                        "ye"=>$week['nextye']
                    ]
                ]);
            }
            $cnt+=2;
        }
        return $rlt;
    }


    public static function ini($key,$default=null){
        $ini_file = root_path() . "socketmsgcfg.ini";
        if (count(self::$inicache)==0 && file_exists($ini_file)){
            self::$inicache=parse_ini_file($ini_file,true);
        }
        $keys=explode(".",$key);
        if (count($keys)>1){
            return (isset(self::$inicache[$keys[0]]) && isset(self::$inicache[$keys[0]][$keys[1]])) ? self::$inicache[$keys[0]][$keys[1]]: $default;
        }else {
            return isset(self::$inicache[$key]) ? self::$inicache[$key] : $default;
        }
    }

    public static function getSocketUid($uid, $prefix = "") {
        if (is_null($uid)) {
            return null;
        }
        if (!$prefix) {
            $prefix = self::ini('userprefix', 'default');
        }
        return "{$prefix}_{$uid}";
    }

}
