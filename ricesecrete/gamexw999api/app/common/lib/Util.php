<?php
namespace app\common\lib;
use app\common\redis\CommomRedis;
use app\common\model\{CurrencyModel,
    LangModel,
    MailNotifyTemplateModel as MailNotifyTemplateModel,
    PaySetModel,
    UserModel,
    NationModel};
use Carbon\Carbon;
use app\api\redis\ApiRedis;
use think\facade\Config;
use think\facade\Db;

class Util
{	
        /**
	* 后台密码
	* @param password
	*/
	public static function md5Password($password){
            return md5(md5("hudi".$password));
	}


	/**
	* 用户登录密码
	* @param password
	*/
	public static function userMd5Password($password){
		return md5(md5('dq'.$password));
	}

    /**
     * 用户登录密码
     * @param $password 密码
     * @param $salt 密码盐
     * @return string
     */
	public static function userMd5Password1($password, $salt){
		return md5((md5($password) . $salt));
	}

	/**
	* 用户提现密码
	* @param password
	*/
	public static function winMd5Password($password){
		return md5(md5('tx'.$password));
	}

	/**
	* 手机号隐藏
	* @param password
	*/
	public static function phone_sub($phone = ""){
        if (empty($phone)) {
            return '';
        }
		return substr_replace($phone, '****', 3, -3);
	}
	
	/**
	* 银行卡号隐藏
	* @param password
	*/
	public static function bank_number_sub($bank_number=""){
		$str="";
		if (mb_strlen($bank_number)>4) {
			$str=substr($bank_number,(mb_strlen($bank_number)-4));
		}else{
			$str=$bank_number;
		}
	   	return $str;
	}

	/**
	* 获取ip
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

	/**
	* get请求-json返回格式
	* @param url 
	*/
	public static function getRequest($url=""){
		$headerArray =array("Content-type:application/json;","Accept:application/json");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_HTTPHEADER,$headerArray);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output,true);
	}

	/**
	* post请求
	* @param url
	* @param data
	* @param is_json 是否json数据
	*/
	public static function postRequest($url,$data,$is_json=false){

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        if ($is_json) {
        	curl_setopt($ch, CURLOPT_HTTPHEADER,array(
		      "Content-Type: application/json",
		      "Accept: application/json",
			));
        }
        
        // POST数据
        curl_setopt($ch, CURLOPT_POST, 1);
        // 把post的变量加上
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;

    }
    /**
    * post表单请求
    * @param url
    * @param data
    * @param is_json 是否json数据
    */
    public static function postFromRequest($url,$data,$header=[]){
        if(!$header){
            $header=["Content-type: application/x-www-form-urlencoded"];
            $data = http_build_query($data);
        }
        // 增加HTTP代理请求
        $isProxy = env('IS_REQUIRE_GAME_PROXY', '');
        $proxy = '';
        if ($isProxy) {
            $proxy = env('GAME_PROXY_URL', '');
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        // POST数据
        curl_setopt($ch, CURLOPT_POST, 1);
        // 把post的变量加上
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_PROXY, $proxy);

        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output,true);
    }
    
    /**
    * 某天 开始结束时间戳
    */
    public static function randDayDate($date){
        $arr['start_time'] = strtotime($date." 00:00:00");
        $arr['end_time'] = strtotime($date." 23:59:59");
        $arr['start_time'] = strtotime(Util::i18n_datetime2str3($arr['start_time']));
        $arr['end_time'] = strtotime(Util::i18n_datetime2str3($arr['end_time']));
        return $arr;
    }

	/**
	* 下载远程文件
	*/
	public static function downloadFile($file_url, $save_to)
	{
	 	$in=    fopen($file_url, "rb");
		$out=   fopen($save_to, "wb");
		while ($chunk = fread($in,8192))
		{
		 	fwrite($out, $chunk, 8192);
		}
		fclose($in);
		fclose($out);
		return true;
	}


	/**
	* 金额转大写
	*/
	public static function num_to_rmb($num){
	    $c1 = "零壹贰叁肆伍陆柒捌玖";
	    $c2 = "分角元拾佰仟万拾佰仟亿";
	    //精确到分后面就不要了，所以只留两个小数位
	    $num = round($num, 2); 
	    //将数字转化为整数
	    $num = $num * 100;
	    if (strlen($num) > 10) {
	        return "金额太大，请检查";
	    } 
	    $i = 0;
	    $c = "";
	    while (1) {
	        if ($i == 0) {
	            //获取最后一位数字
	            $n = substr($num, strlen($num)-1, 1);
	        } else {
	            $n = $num % 10;
	        }
	        //每次将最后一位数字转化为中文
	        $p1 = substr($c1, 3 * $n, 3);
	        $p2 = substr($c2, 3 * $i, 3);
	        if ($n != '0' || ($n == '0' && ($p2 == '亿' || $p2 == '万' || $p2 == '元'))) {
	            $c = $p1 . $p2 . $c;
	        } else {
	            $c = $p1 . $c;
	        }
	        $i = $i + 1;
	        //去掉数字最后一位了
	        $num = $num / 10;
	        $num = (int)$num;
	        //结束循环
	        if ($num == 0) {
	            break;
	        } 
	    }
	    $j = 0;
	    $slen = strlen($c);
	    while ($j < $slen) {
	        //utf8一个汉字相当3个字符
	        $m = substr($c, $j, 6);
	        //处理数字中很多0的情况,每次循环去掉一个汉字“零”
	        if ($m == '零元' || $m == '零万' || $m == '零亿' || $m == '零零') {
	            $left = substr($c, 0, $j);
	            $right = substr($c, $j + 3);
	            $c = $left . $right;
	            $j = $j-3;
	            $slen = $slen-3;
	        } 
	        $j = $j + 3;
	    } 
	    //这个是为了去掉类似23.0中最后一个“零”字
	    if (substr($c, strlen($c)-3, 3) == '零') {
	        $c = substr($c, 0, strlen($c)-3);
	    }
	    //将处理的汉字加上“整”
	    if (empty($c)) {
	        return "零元整";
	    }else{
	        return $c . "整";
	    }
	}


	/**
	* base64保存
	*/
	public static function base64($base64){
        //接收base64数据
        $image=$base64;
        //设置图片名称
        $imageName = "25220_".date("His",time())."_".rand(1111,9999).'.png';
        //判断是否有逗号 如果有就截取后半部分
        if (strstr($image,",")){
            $image = explode(',',$image);
            $image = $image[1];
        }
        //设置图片保存路径
        $path = "/contract/".date("Ymd",time());

        //判断目录是否存在 不存在就创建
        if (!is_dir(".".$path)){
            mkdir(".".$path,0777,true);
        }
        //图片路径
        $imageSrc= ".".$path."/". $imageName;

        //生成文件夹和图片
        $r = file_put_contents($imageSrc, base64_decode($image));
        if (!$r) {
            throw new \think\Exception("保存错误", 0);//
        }
        return $path."/".$imageName;
	}


        /**
         * 生成推广码
         * @param number
         */
        public static function rec_code(int $number = 0) {
            $rec_code = self::random(8, true);
            $isExist = UserModel::where('rec_code', $rec_code)->count();
            if ($isExist) {
                return self::rec_code($number);
            }
            return $rec_code;
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


	/**
	* 生成日期范围
	* @param start_time
	* @param end_time
	*/
	public static function periodDate($start_time,$end_time){
	    $start_time = strtotime($start_time);
	    $end_time = strtotime($end_time);
	    $arr=[];
	    $i=0;

	    while ($start_time<=$end_time){
	        $arr[$i]['date']=date('Y-m-d',$start_time);
	        $start_time = strtotime('+1 day',$start_time);
	        $i++;
	    }

	    return $arr;
	}

    /**
     * 格式化数字
    */
    public static function float_number(int $number){
        if($number >= 1000000000){ //十亿单位
            return round(($number/1000000000),1)."B";
        }elseif($number >= 1000000){ //百万单位
            return round(($number/1000000),1)."M";
        }elseif($number >= 1000){ //千单位
            return round(($number/1000),1)."K";
        }else{
            return $number;
        }
    }
        
    /**
    * 时区转换
    */
    public static function timeZoneConversion($date, $format="Y-m-d H:i:s"){
        $sys = (new ApiRedis())->systemInfo();
        $time_zone = $sys['time_zone'];
        if($time_zone == Config::get('app.default_timezone')){
            $time = strtotime($date);
            $change_time = strtotime(Carbon::parse($time, 'UTC')->setTimezone('Asia/Shanghai')->format("Y-m-d H:i:s"));
            $localtime = strtotime(Carbon::parse($date, 'UTC')->setTimezone($time_zone)->format($format));
            $time = $localtime + $time - $change_time; // 时间差额
            $formatted_date = date("Y-m-d H:i:s", $time);
        }else{
            $formatted_date = Carbon::parse($date, 'UTC')->setTimezone($time_zone)->format($format);
        }
        return $formatted_date;
    }
    
    /**
    * 北京时区转换目标时区
    */
    public static function beijTimeZoneConversion($date, $format="Y-m-d H:i:s"){
        $sys = (new ApiRedis())->systemInfo();
        $time_zone = $sys['time_zone'];
        $formatted_date = Carbon::parse($date, 'UTC')->setTimezone($time_zone)->format($format);
        return $formatted_date;
    }
    
    /**
    * 国际化数字格式
    * @param int $number
     * @param bool $isReturnFormat  是否只返回格式，只返回格式时金额可以随便传
     */
    public static function i18n_number_format($number, $isReturnFormat = false) {
        $range = self::getDefaultCountry();
        $format_number = Config::get('custom.number');
        if (!$range || !isset($format_number[$range])) {
            $range = 'CN';
        }
        $format = $format_number[$range];
        if( $isReturnFormat ){
            return $format;
        }
        //env是否设置小数点位数
        $decimalPoint = env('AMOUNT_DECIMAL_POINT', $format[0]);
        //env是否设置四舍五入
        if( env('AMOUNT_IS_ROUNDING', 1) == 0 ){
            $pow = pow(10, $decimalPoint);
            $number = floor($number * $pow) / $pow;
        }
        return number_format(floatval($number), $decimalPoint, $format[1], $format[2]);
    }
    
    /**
    * 国际化货币格式
    * @param int $number
    * @param bool $isReturnFormat  是否只返回格式，只返回格式时金额可以随便传
    */
    public static function i18n_currency($number, $isReturnFormat = false) {
        $range = self::getDefaultCountry();
        if (!$range) {
            $range = 'CN';
        }
        $currency = Config::get('custom.currency');
        if (!$range || !isset($currency[$range])) {
            $range = 'CN';
        }
        $format = $currency[$range];
        if( $isReturnFormat ){
            return $format;
        }
        return sprintf($format, self::i18n_number_format($number));
    }

    /**
    * 国际化货币格式（没有金额）
    */
    public static function i18n_currency_no_amount() {
        $range = self::getDefaultCountry();
        if (!$range) {
            $range = 'CN';
        }
        $currency = Config::get('custom.currency');
        if (!$range || !isset($currency[$range])) {
            $range = 'CN';
        }
        $format = $currency[$range];
        return str_replace('%s', '', $format);
    }

    /**
     * @param $field string 字段 symbol | symbol_img
     * @return array|mixed
     */
    public static function getCurrencyDefaultSymbol($field = null){
        $CommonRedis = new CommomRedis();
        $currency = $CommonRedis->getCurrency();
        $code = $symbol_img = $symbol = "";
//        dd(collect($currency)->toArray());
        if (isset($currency['defa_data'])) {
            $symbol = $currency['defa_data']['symbol'] ?: $currency['defa_data']['currency_code'] ?: "";
            $symbol_img = $currency['defa_data']['symbol_img'] ?? "";
            $code = $currency['defa_data']['currency_code'] ?? "";
        }

        $result = [
            'symbol' => $symbol,
            'symbol_img' => $symbol_img,
            'code' => $code,
        ];

        return $result[$field] ?? $result;
    }
    /**
    * 国际化货币格式（没有金额）
    */
    public static function i18n_currency_by_code($code) {
        if( !$code ){
            $code = env('default_country', '');
        }
        $currency = Config::get('custom.currency');
        if ( !isset($currency[$code]) ) {
            return '$';
        }
        return str_replace('%s', '', $currency[$code]);
    }
    
    /**
    * 国际化货币code
    * @param int $number
    */
    public static function i18n_currency_code($range = '') {
        $currency_type = env('currency_type', 0);
        if( !$range ){
            $range = env('default_country', '');
        }
        if(empty($range)){
            $range = CurrencyModel::where('is_defa', 1)->value('country_code');
        }
        if (!$range) {
            $range = 'CN';
        }
        $currency = Config::get('custom.currency_code');
        if (!$range || !isset($currency[$range])) {
            $range = 'CN';
        }
        return $currency[$range];        
    }
    
    /**
    * 国际化日期格式
    * @param int $time
    */
    public static function i18n_date2str($time) {
        $range = self::getDefaultCountry();
        $format = Config::get('custom.date');
        if (!$range || !isset($format[$range])) {
            $range = 'CN';
        }
        return self::beijTimeZoneConversion($time, $format[$range]);
    }
    
    /**
    * 国际化时间格式
    * @param int $time
    */
    public static function i18n_time2str($time) {
        $range = self::getDefaultCountry();
        $format = Config::get('custom.time');
        if (!$range || !isset($format[$range])) {
            $range = 'CN';
        }
        
        return self::beijTimeZoneConversion($time, $format[$range]);
    }
    
    /**
     * 国际化时间格式
     * @param int $time
     */
    public static function i18n_datetime2str($time = null) {
        if (is_null($time)) {
            $time = time();
        }
        $range = self::getDefaultCountry();
        $format = Config::get('custom.datetime');
        if (!$range || !isset($format[$range])) {
            $range = 'CN';
        }
        return self::beijTimeZoneConversion($time, $format[$range]);
    }
    
    public static function i18n_datetime2str3($time = null) {
        if (is_null($time)) {
            $time = time();
        }
        $time = Util::differencej($time);
        return self::beijTimeZoneConversion($time, "Y-m-d H:i:s");
    }
    
    public static function i18n_datetime2str4($time = null) {
        if (is_null($time)) {
            $time = time();
        }
        $range = self::getDefaultCountry();
        $format = Config::get('custom.datetime');
        if (!$range || !isset($format[$range])) {
            $range = 'CN';
        }
        $formatted_date = Carbon::parse($time, 'UTC')->setTimezone("Asia/Shanghai")->format($format[$range]);
        return $formatted_date;
    }
    
    /**
     * 国际化时间格式 赛事
     * @param int $time
     */
    public static function i18n_datetime3str($time = null) {
        if (is_null($time)) {
            $time = time();
        }
        $range = self::getDefaultCountry();
        $format = Config::get('custom.datetime');
        if (!$range || !isset($format[$range])) {
            $range = 'CN';
        }
        return date($format[$range], $time);
    }
    
    /**
     * 国际化时间格式2
     * @param int $time
     */
    public static function i18n_datetime2str2($time = null) {
        if (is_null($time)) {
            $time = time();
        }
        $time = self::difference($time);
        $range = self::getDefaultCountry();
        $format = Config::get('custom.datetime2');
        if (!$range || !isset($format[$range])) {
            $range = 'CN';
        }
        return date($format[$range], $time);
    }
    
    public static function difference($time){
        $sys = (new ApiRedis())->systemInfo();
        $time_zone = $sys['time_zone'];        
        if($time_zone == Config::get('app.default_timezone')){
            $change_time = strtotime(Carbon::parse(time(), 'UTC')->setTimezone('Asia/Shanghai')->format("Y-m-d H:i:s"));
            $time = $time - time() + $change_time; // 时间差额    
        }
        return $time;
    }
    
    public static function differencej($time){
        $sys = (new ApiRedis())->systemInfo();
        $time_zone = $sys['time_zone'];        
        if($time_zone == Config::get('app.default_timezone')){
            $change_time = strtotime(Carbon::parse(time(), 'UTC')->setTimezone('Asia/Shanghai')->format("Y-m-d H:i:s"));
            $time = $time + time() - $change_time; // 时间差额    
        }
        return $time;
    }
    
    public static function getPayTypeName($type){
        $payment_method_type = Config::get('custom.payment_method_type');
        if(isset($payment_method_type[$type])){
            return $payment_method_type[$type];
        }else{
            return "无类型";
        }
    }
    
    public static function getPayType(){
        $payment_method_type = Config::get('custom.payment_method_type');
        $is_online_pay = env('is_online_pay', 0);
        $is_online_pay_bank = env('is_online_pay_bank', 0); //银行渠道
        $is_online_pay_usdt = env('is_online_pay_usdt', 0); // usdt渠道
        $is_online_pay_wallet = env('is_online_pay_wallet', 0); // 电子钱包
        $is_online_pay_pix = env('is_online_pay_pix', 0); // 电子钱包

        if($is_online_pay == 1){
            if($is_online_pay_bank == 0){
                unset($payment_method_type[0]);
            }
            if($is_online_pay_usdt == 0){
                unset($payment_method_type[1]);
            }
            if($is_online_pay_wallet == 0){
                unset($payment_method_type[2]);
            }
            if($is_online_pay_pix == 0){
                unset($payment_method_type[3]);
            }
            return $payment_method_type;
        }else{
            return [];
        }
    }
    
    public static function getOfflinePayType(){
        $payment_method_type = Config::get('custom.payment_method_type');
        $is_offline_pay = env('is_offline_pay', 0);
        $is_offline_pay_bank = env('is_offline_pay_bank', 0); //银行渠道
        $is_offline_pay_usdt = env('is_offline_pay_usdt', 0); // usdt渠道
        $is_offline_pay_wallet = env('is_offline_pay_wallet', 0); // 电子钱包
        $is_offline_pay_pix = env('is_offline_pay_pix', 0); // 电子钱包
        if($is_offline_pay == 1){
            if($is_offline_pay_bank == 0){
                unset($payment_method_type[0]);
            }
            if($is_offline_pay_usdt == 0){
                unset($payment_method_type[1]);
            }
            if($is_offline_pay_wallet == 0){
                unset($payment_method_type[2]);
            }
            if($is_offline_pay_pix == 0){
                unset($payment_method_type[3]);
            }
            return $payment_method_type;
        }else{
            return [];
        }
    }
    
    public static function getWOpenChannel(){
        $payment_method_type = Config::get('custom.payment_method_type');
        $is_withdrawal_bank = env('is_withdrawal_bank', 0); //银行渠道
        $is_withdrawal_usdt = env('is_withdrawal_usdt', 0); // usdt渠道
        $is_withdrawal_wallet = env('is_withdrawal_wallet', 0); // 线下电子钱包
        $is_withdrawal_pix = env('is_withdrawal_pix', 0); // 线下电子钱包

        if($is_withdrawal_bank == 0){
            unset($payment_method_type[0]);
        }
        if($is_withdrawal_usdt == 0){
            unset($payment_method_type[1]);
        }
        if($is_withdrawal_wallet == 0){
            unset($payment_method_type[2]);
        }
        if($is_withdrawal_pix == 0){
            unset($payment_method_type[3]);
        }

        return $payment_method_type;
    }
    
    public static function getOpenChannel(){
        $channel = Config::get('custom.channel');
        $is_offline_pay_bank = env('is_offline_pay_bank', 0); //银行转账
        $is_offline_pay_usdt = env('is_offline_pay_usdt', 0); // usdt转账
        $is_online_pay_bank = env('is_online_pay_bank', 0); //银行渠道
        $is_online_pay_usdt = env('is_online_pay_usdt', 0); // usdt渠道
        $is_offline_pay_wallet = env('is_offline_pay_wallet', 0); // 线下电子钱包
        $is_online_pay_wallet = env('is_online_pay_wallet', 0); // 线上电子钱包
        $is_offline_pay_pix = env('is_offline_pay_pix', 0); // 线下PIX
        $is_online_pay_pix = env('is_online_pay_pix', 0); // 线上PIX
        $is_online_pay = env('is_online_pay', 0);
        $is_offline_pay = env('is_offline_pay', 0);
        $is_agent_pay = env('is_agent_pay', 0); // 银商
        if($is_online_pay == 0){
            $is_online_pay_bank = $is_online_pay_usdt  = 0;
        }
        if($is_offline_pay == 0){
            $is_offline_pay_bank = $is_offline_pay_usdt = $is_offline_pay_wallet = $is_offline_pay_pix = 0;
        }
        
        if($is_offline_pay_bank == 0){
            unset($channel[0]);
        }
        if($is_online_pay_bank == 0){
            unset($channel[1]);
        }
        if($is_offline_pay_usdt == 0){
            unset($channel[3]);
        }
        if($is_online_pay_usdt == 0){
            unset($channel[2]);
        }
        if($is_offline_pay_wallet == 0){
            unset($channel[4]);
        }
        if($is_online_pay_wallet == 0){
            unset($channel[5]);
        }
        if($is_offline_pay_pix == 0){
            unset($channel[6]);
        }
        if($is_online_pay_pix == 0){
            unset($channel[7]);
        }

        if ($is_agent_pay == 0) {
            unset($channel[20]);
        }
        return $channel;
    }
    
    public static function getPayCurrency($pay='', $type=0){ //支付信息   0充值 1提现
        $CommomRedis = new CommomRedis();
        $currency = $CommomRedis->getCurrency();
        $defa_currency = $currency['defa_data'];
        $ordinary_data = $currency['ordinary_data'];
        $range = env('default_country', '');
        if(empty($range)){
            $range = CurrencyModel::where('is_defa', 1)->value('country_code');
        }
        
        //如果支付渠道或者提现的银行卡没有币种 直接获取默认币种
        if(!isset($pay['currencyApi'])){
                //如果是 usdt 就获取usdt的货币
            if($pay['pay_type'] == 1){
                if(isset($currency['usdt_data'][0])){
                    $pay['currencyApi'] = $currency['usdt_data'][0];
                }
            }else{  //如果是银行卡 就获取普通货币的默认币种
                if(!empty($ordinary_data)){
                    foreach($ordinary_data as $cval){
                        if($cval['country_code'] == $range){
                            $pay['currencyApi'] = $cval;
                        }
                    }
                }
            }
        }
        
        //当对应的货币被关闭了的时候 或者 该渠道没有币种并且也没有默认币种的时候
        if((isset($pay['currencyApi']) && $pay['currencyApi']['state'] == 2) || !$pay['currencyApi']){
            $str =  $type == 0 ? '操作失败,请更换渠道后尝试' : '操作失败,请更换银行卡后尝试';
            ajax_return($str, 0);
        }

        $data = [];
        if($type == 0){
            //没有支付渠道信息的时候  或者系统没有默认货币的时候
            if(empty($pay) || empty($defa_currency)){
                $data['pay_currency'] = $range; // 当前支付货币
                $data['exchange_rate'] = 1; // 汇率
                $data['arrival_currency'] = $range; // 最终转换货币
            }else{
                $data['pay_currency'] = $pay['currencyApi']['country_code']; // 当前支付货币
                $data['exchange_rate'] = $pay['currencyApi']['recharge_rate']; // 汇率
                $data['arrival_currency'] = $defa_currency['country_code']; // 最终转换货币
            }
        }else{
            if(!isset($defa_currency)){
                ajax_return('操作失败,请更换银行卡后尝试', 0);
            }
            $data['current_currency'] = $defa_currency['country_code']; // 当前支付货币
            $data['exchange_rate'] = $pay['currencyApi']['withdrawal_rate']; // 提现汇率
            $data['arrival_currency'] = $pay['currencyApi']['country_code']; // 最终转换货币
        }
        return $data;
    }



    public static function common_validation_param($param){
        if(is_array($param)){
            foreach($param as &$v){
                $v = $v == '' || $v == 'undefined' || $v == null ? '' : $v;
            }
        }
        return $param;

    }

    /**
     * 复制数组中keys指定或排除keys的数据
     * @param $fields str【sp:,】 or array
     * @param bool $ex default false 是否排除指定的key
     * @return array
     * @author twt326
     * @date 2022-03-03 3:51
     */
    public  static function getArrayDataByKeys($arr,$keys,$ex=false){
        $dt=[];
        if (!is_array($keys)) $keys=explode(",",$keys);
        foreach ($arr as $key=>$v){
            $flag=in_array($key,$keys);
            if ($ex != $flag){
                $dt[$key]=json_decode(json_encode($v),true);
            }
        }
        return $dt;
    }

    /**
     * 判断表名是否存在
     * @param string $full_table_name
     * @param string $config
     * @return bool
     */
    public static function existTable($full_table_name, $config = "") {
        $exist = Db::connect($config)->query("show tables like '%{$full_table_name}%'");
        return empty($exist) ? false : true;
    }

    /**
     * 执行SQL
     * @param type $sql
     * @param type $config 数据库配置
     */
    public static function execute($sql, $config = "") {
        Db::connect($config)->execute($sql);
    }

    /**
     * 将unix时间戳转成字符
     * @param int $time unix时间戳，默认为当前时间
     * @param string $format 格式。默认为 Y-m-d H:i:s
     * @return string
     */
    public static function timeToStr($time = NULL, $format = NULL) {
        if (!$time) {
            $time = time();
        }
        if (!$format) {
            $format = 'Y-m-d H:i:s';
        }
        return date($format, $time);
    }
    /**
     * 把字符串格式的时间日期转换成时间戳
     * @param string $dtime
     * @return int
     */
    public static function getMkTime($dtime) {
        if (!preg_match("/[^0-9]/", $dtime)) {
            return $dtime;
        }
        $mt = strtotime(trim($dtime));
        return !empty($mt) ? $mt : time();
    }


    /**
     * 处理获取usdt的链接名
     * @return array|mixed
     */
    public static function getUsdtType(){
        /* @var CurrencyModel $CurrencyModel*/
        $CurrencyModel = invoke(CurrencyModel::class); //;
        $usdt_data = $CurrencyModel->where(['type' => 1])->field('link_name,state')->find();
        $usdt_type = [];
        //TODO 已做处理 数据库会默认开启USDT
        if($usdt_data){
            //已经添加 但是没有开启  或者没有链接名称的  直接返回空
            if($usdt_data['state'] == 2){
                return [];
            }
            //已经添加的
            foreach($usdt_data['link_name'] as $key => $val){
                $usdt_type[$val] = $val;
            }
        }else{
            //没有添加的时候 获取系统配置的
            $usdt_type = Config::get('custom.usdt_type');
        }
        return $usdt_type;
    }

    /**
     * @return array
     * @throws \think\exception\DbException
     */
    public static function getCurrency(): array
    {
        /** @var CurrencyModel $CurrencyModel*/
        $CurrencyModel = app()->make(CurrencyModel::class);
        //如果不存在 USDT 就是代表被关了
        $usdt_data = $CurrencyModel->where(['state' => 1, 'type' => 1])->withoutField('create_time,update_time')->select(); //理论上是只有一个usdt的数据 但是为了统一格式
        //普通货币信息
        $ordinary_data = $CurrencyModel->where(['state' => 1, 'type' => 2])->withoutField('create_time,update_time')->select();
        //默认货币信息
        $defa_data = $CurrencyModel->where(['state' => 1, 'is_defa' => 1])->withoutField('create_time,update_time')->find();
        return compact('usdt_data', 'ordinary_data', 'defa_data');
    }

    /**
     * 获取数据库中 正常状态的语种(后台状态开启)
     * @return Object
     */
    public static function getLangs(): object
    {
        return LangModel::where(['status' => 1])->order('sort desc')->select();
    }

    /**
     * 获取数据库中 正常状态的语种（前台状态开启的）
     * @return Object
     */
    public static function getLangsWeb(): object
    {
        return LangModel::where(['web_status' => 1])->order('sort desc')->select();
    }


    /**
     * 获取数据库中 正常状态的国家并且获取语种
     * @param object $langs
     * @return Object
     */
    public static function getNation(): object
    {
        $CommomRedis = new CommomRedis();
        $langs = $CommomRedis->getLangsData();
        return NationModel::order('sort desc')
            ->filter(function($data) use ($langs){
                $arr = [];
                foreach($data['lang'] as $k => $v){
                    foreach($langs as $val){
                        if($val['id'] == $v){
                            $arr[$k] = $val['lang'];
                        }
                    }
                }
                $data['langs'] = $arr;
                unset($data['lang']);
            })
            ->where(['state' => 1])
            ->withoutField('update_time,create_time,id,sort,state')
            ->select();
    }

    /**
     * 转换目标时区
     */
    public static function getTimeZoneConversion($date, $time_zone, $format="Y-m-d H:i:s"){
        $time = is_numeric($date) ? $date : strtotime($date);
        $change_time = strtotime(Carbon::parse($time, 'UTC')->setTimezone(Config::get('app.default_timezone'))->format($format));
        $formatted_date = strtotime(Carbon::parse($time, 'UTC')->setTimezone($time_zone)->format($format));
        return $time + ($change_time - $formatted_date);
    }

    /**
     * 转换目标时区和本地时区的时差秒数
     */
    public static function getTimeZoneDifference($time_zone){
        if (empty($time_zone)) {
            return 0;
        }
        $local_time = strtotime(Carbon::now('UTC')->setTimezone(Config::get('app.default_timezone'))->format("Y-m-d H:i:s"));
        $to_time = strtotime(Carbon::now('UTC')->setTimezone($time_zone)->format("Y-m-d H:i:s"));
        return $local_time - $to_time;
    }
    /**
     * 转换目标时区
     */
    public static function localTimeToTimezone($date, $time_zone, $format="Y-m-d H:i:s"){
        $time = is_numeric($date) ? $date : strtotime($date);
        return Carbon::parse($time, Config::get('app.default_timezone'))->setTimezone($time_zone)->format($format);
    }
    /**
     * 图片地址转换
     */
    public static function img_url($img) {
        $url = env('img_url', '');
        if (stripos($img, 'http') === false || stripos($img, 'https') === false) {
            return $url.$img;
        }
        return $img;
    }

    /**
     * @return array
     * @throws \think\exception\DbException
     */
    public static function getBankList(): array
    {
        /** @var PaySetModel $PaySetModel */
        $PaySetModel = app()->make(PaySetModel::class);
        $bank_list = [];
        $condition = [];
        $condition[] = ['is_withdra', '=', 1];
        $condition[] = ['isdel', '=', 0];
        $list = $PaySetModel->where($condition)->where('pay_bank', '<>', '[]')->select();
//        $list = $PaySetModel->where($condition)->where('pay_bank', '<>', '[]')->group('pay_sys_sign')->select();
        foreach ($list as $key => $val) {
            $list2 = json_decode($val['pay_bank'], true);
            foreach ($list2 as $row) {
                $temp_field = [
                    'bank_type',
                    'bank_name',
                    'bank_number',
                    'bank_hm'
                ];
                if (isset($row['bank_field'])) {
                    if (!empty($row['bank_field'])) {
                        $temp_field_ = explode("|", $row['bank_field']);
                        $temp_field = array_merge($temp_field, $temp_field_);
                    }
                }
                $temp_field = array_merge(array_unique($temp_field));
                $row['bank_field'] = $temp_field;
                if (strexists($row['bank_name'], "@@")) {
                    continue;
                }
                $bank_list[$row['bank_name']] = $row;
                $bank_list[$row['bank_name']]['currency_id'] = $val['currency_id'] ?? 0;
            }
        }
        return [array_values($bank_list)];
    }
    //格式化用户名（例：ad***in）
    public static function formatUsername($username){
        $len = mb_strlen($username);
        $str1 = mb_substr($username,0,2);
        $str2 = mb_substr($username,$len-2,2);
        return $str1.'****'.$str2;
    }

    public static function getDefaultCountry(){
        $range = env('default_country', '');
        if(empty($range)){
            $ApiRedis=new ApiRedis();
            $range = $ApiRedis->get("default_country");
            if(empty($range)){
                $range = LangModel::where('is_default', 1)->value('lang');
                $ApiRedis->set("default_country", $range, null);
            }
        }
        return $range;
    }

    /**
     * 把固定标识替换成站点名称
     * @param $content
     * @return array|string|string[]
     */
    public static function replaceWebName($content) {
        static $website_name;
        if (!$website_name) {
            $ApiRedis=new ApiRedis();
            $website_name = $ApiRedis->systemInfo("website_name");
        }
        return str_replace('WEBNAME', $website_name, $content);
    }


    public static function email_notify($t = '', $uid){
        $CommomRedis = new CommomRedis();
        $sys = $CommomRedis->systemInfo();
        if(empty($t)){
            return false;
        }
        //发送行销模板消息
        if(isset($sys['mail_notify_is_open']) && $sys['mail_notify_is_open'] == 1){
            //实名认证模板开启的时候
//            $k = $t .'_sta';
            $MailNotifyTemplateModel = new MailNotifyTemplateModel();
            $w = [];
            $w[] = ['sign', '=', $t];
            $w[] = ['state', '=', 1];
            $oneData = $MailNotifyTemplateModel->where($w)->find();
            if(!empty($oneData)){
                $u_w = [];
                $u_w[] = ['id', '=', $uid];
                $u_w[] = ['auto_mail_notify_sta', '=', 1]; //用户开启了通知邮件的时候
                $email = UserModel::where($u_w)->value('mail') ?? '';
                if(!empty($email)){
                    $other = [];
                    //type 填写类型  设置中的字段是 类型加title 比如 verify_title
//                    $other['notify_type'] = $t;
                    $other['title'] = $oneData['title'];
                    $other['content'] = $oneData['content'];
                    EmailCode::notifyMail($email, $other);
                }
            }
        }

    }
}