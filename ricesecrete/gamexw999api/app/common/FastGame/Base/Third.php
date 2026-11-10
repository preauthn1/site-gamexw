<?php


namespace app\common\FastGame\Base;


use app\model\GameInfos;
use app\model\Games;
use app\model\ThirdConfigs;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * 第三方游戏接口
 * 非公共方法或不能通过公共方法实现的业务功能继承此类，类名为： Thrid{g_code} 全小写，并实现API表中的func命名方法
 * Class Third
 * @package app\common\FastGame\Base
 * @author twt326
 * @date 2022-08-23 15:48
 */

abstract class Third
{
    //TODO ***重要***最好正式上线前将$extClass改为数组存放，因为在实际业务中很可能存在（特别是任务中）同时处理多个第三方数据时，如果只使用静态属性时会导致数据处理混乱

    /** @var Third null  */
    public static $extClass=null;
    public static $extClassName=null;
    public $apicfgs=null;

    public $signDatas=null;//参与签名的数据及指定签名的数据 null or [runfunc=>当前执行的函数名,get=>,header=>,post=>,otherkvs=>],在继承类中使用

    /**
     * 签名
     * @param $config
     * @param $data POST参数
     * @return mixed
     * @author twt326
     * @date 2022-08-26 17:29
     */
    abstract function MakeSign($config, &$data);

    /**验证签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-08-26 17:32
     */
    abstract function CheckSign($config,$data);

    /**
     * 转换第三方TOKEN过期时间为本平台支持的过期时间戳，将获取到的第三方TOKEN过期时间转换为整型秒为单位的时间戳
     * 不需要处理的直接 return $in; 即可
     * @param mixed $in
     * @return int
     * @author twt326
     * @date 2022-09-13 16:32
     */
    abstract function TokenTimestampTranser($in);

    /** 转换第三方用户TOKEN过期时间为本平台支持的过期时间戳，将获取到的第三方TOKEN过期时间转换为整型秒为单位的时间戳
     * 默认直接调用 TokenTimestampTranser ，如果两种处理方式不一样，请重写此方法
     * @param $in
     * @return int
     * @author twt326
     * @date 2022-12-12 16:21
     */
    public function UTokenTimestampTranser($in){
        return $this->TokenTimestampTranser($in);
    }

    /**
     * 获取三方每次查询时间范围最大值，单位：秒，默认 15 分钟。
     * @return int
     */
    public function getBetListMaxTime(): int {
        return 15 * 60 - 1;
    }

    /**
     * 将自定义参数进行二次处理，如果不需要处理，则不用写任何代码
     * 主要用于不同的平台可能在访问不同的API时需要的数据不能直接使用调用时传入的参数，而需要进行二次处理的
     * 比如传入参数为 plat=2,但三方平台接口需要的值是 plat=desktop,那么可以在这里进行转换
     * 由于是传值引用，所以为避免多次重复处理，浪费资源，可自行在$kvs中增加一个标志，用于表示是否已经处理过
     * @param $kvs
     * @return mixed
     * @author twt326
     * @date 2022-10-11 22:21
     */
    abstract function transOtherKvs(&$kvs);

    /** 在HTTP访问之前对POST数据进行处理，如果不需要处理，则不用重写此方法
     * @param $runfunc 当前执行的API函数名
     * @param $postdata 当前要传送的POST数据，建议在函数体内进行修改
     * @param $otherkvs
     * @return mixed
     */
    public function transFinallyPostdata($runfunc,&$postdata,$otherkvs){
        //TODO 示例:某些特殊情况下需要对最终传送的数据进行一些特殊处理，比如SOAP协议下需要对数据进行XML化
        return $postdata;
    }

    /** 对三方平台返回结果进行预处理
     * 用于对返回数据不能简单的以 封装标签 或 自定义标签 做处理的情况
     * 如果不需要处理则在继承类中不需要重写
     * 如果要改变结果或是将自定标签获取的数据转为 封装标签 可以通过 增加或修改 $kds 的键值来实现
     * @param string $func API配置的名字，根据它来区分要处理的数据
     * @param array $kds 以ga_keyfield中设置的一级索引名为索引，值为对应的返回数据体中以key指定的数据
     * @param array $datas 三方平台接口原始返回的数据体
     * @param int $tcid 三方平台ID
     * @param int $uid 用户在本平台的ID
     * @param int $gid 游戏ID，某些与游戏无关的情况下值为0
     * @param null|array $otherkvs 调用初始时传入的参数
     * @return mixed
     * @author twt326
     * @date 2022-10-15 13:51
     */
    public function transResultData($func,&$kds,$datas,$tcid,$uid,$gid,$ohterkvs){
        //DEMO
        //示例：某个三方平台的获取游戏地址接口返回的并不是完整地址，比如会返回一个 token  一个 weburl 一个 gameid
        //那么：在ga_keyfield中可以这样定义它们  c_token:{key:data.token} c_weburl:{key:data.weburl} c_gameid:{key:data.gameid}
        //在重写的本方法体内可以这样处理：
        /*switch ($func){
            case 'GETUSERURL':
                $kds['tcgameurl']=$kds['c_weburl']."?token=".$kds['c_token']."&gid=".$kds['c_gameid'];
                break;
        }*/
        //注：tcgameurl 是封装定义的用户三方平台游戏地址标签，因此做这样的处理后，封装会自动接下面的流程
        //如果是封装无法处理的事情，可以在这里做

        /**
         * 特别说明，有些游戏并不是返回的url地址，也许返回的是一段js代码，或者是html代码
         * 这里统一约定存入数据表中的游戏地址使用前缀和|隔开游戏地址内容，约定如下，由前端拿到数据后解析展示：
         * URL| 或 无前缀  ：游戏地址
         * HTML| ：html代码
         * JS| : js代码
         */

        /**
         * 新增说明
         * 如果 isset($datas['ErrHttpCode'])==true 则说明是错误的请求，需要抛出异常或进行特殊处理，比如有些三方是以返回状态码来传递信息的时候
         * 当出现这样的情况时，请参考技术文档中$datas的结构，并在这里进行处理，比如可以判断ErrHttpCode是否为200，如果不是则抛出异常，并附带错误信息
         */
    }

    /**
     * 三方接口返回错误时的处理方法，默认返回false，如果需要处理，请重写此方法
     * @param bool $ErrHasResponse 是否有返回值
     * @param int|null $ErrHttpCode 错误代码
     * @param $ErrMessage
     * @param null|ResponseInterface $response 返回的response对象
     * @param array $apicfgs API配置
     * @param string $funcname 执行API的函数名
     * @param array $otherkvs 执行时的参数
     * @return bool|mixed 如果是真错误，请返回FALSE，否则请返回处理好的结果（可以被 parseResultData 方法处理的结果或者根据实际调用上下文进行数据处理）
     */
    public function handleHttpException($ErrHasResponse,$ErrHttpCode,$ErrMessage,$response,$apicfgs,$funcname,$otherkvs){
        return false;
    }

    /**
     * 如果三方是以HTTPCODE来判定是否成功的，或者返回结果加密的情况下，需要进行再次判断的情况下，必须重写此方法，除非三方返回的状态就能代码结果
     * @param $funcname 当前执行的API函数名
     * @param $data HTTP请求返回结果
     * @param $apicfgs API配置
     * @param $otherkvs 附加参数
     * @return bool
     */
    public function httpcodeDataCheck($funcname,$data,$apicfgs,$otherkvs){
        return true;
    }
    
    /** 检查三方API是否还存在频率限制
     * 
     * @param int $gaid 游戏API记录ID 
     * @param int $garate 游戏api频率限制设置值（秒）
     * @param boolean $updatetime 默认true，每次检查时是否同时更新时间戳(如果已过限制)，一般来说保持默认值即可，但可根据业务情况决定给false，比如要保持上次调用时间时，或者只是查验一下当前的状态，但不想改变上次频率限制的时间点
     * @return true|number 返回TRUE（===）表示没有触发频率限制，否则返回数字，表示限制还有x秒
     */
    public static function checkGameApiRate($gaid,$garate,$updatetime=true){
        if (intval($garate)==0) return true;
        $flag=true;
        $t= intval(Redis::hget('thirdapisratecache', $gaid));
        if ($t){
            $flag= time()-$t-$garate;
            $flag=$flag>=0?true:abs($flag);
        }
        if ($updatetime && $flag===true){
            Redis::hset('thirdapisratecache', $gaid, time());
        }
        return $flag;
    }

    public function __construct($apicfgs=null)
    {
        $this->apicfgs=$apicfgs;
    }

    /** 集成三方对接时必须重写此方法 需要实现的功能为根据 $setrd_datas 中提供的信息获取游戏ID后重新获取真实的API配置
     * 使用 Games::getConfigs($gid); 获取新的配置(对于无法确定游戏ID或真实TCID的情况，比如三方获取余额的操作，可以随机返回一个集合内TCID即可，因数获取余额与平台无关)
     * @param $apicfgs 旧配置
     * @param $setrd_datas 提供的附加信息，来自于三方回调的request
     * $setrd_datas=[
    'request'=>$request,
    'action_method'=>$param['action_method'] ?? null,
    'header'=>Request::header(),
    'param'=>Request::param()
    ];
     * 重写方法体内最后调用基类此方法
     * @return void
     */
    public function setrdConstruct($apicfgs,$setrd_datas){
        if($apicfgs==null){
            throw new Exception('集合三方类处理TCID出错');
        }else {
            $this->apicfgs = $apicfgs;
        }
    }

    /**
     * 平台间汇率转换
     * @param $amount 要转汇率的金额
     * @param int $tomeorthird 转换模式 0 三方转我方 1我方转三方
     * @param int $scale
     * @return string 字符串数值
     * @author twt326
     * @date 2022-10-16 16:16
     */
    public static function transExchangerate($amount,$tomeorthird,$scale = 8){
        //读取汇率
        $third_name = strtoupper(explode('Third_',self::$extClassName)[1]);
        $rate = Redis::hget('fastgame_exchangerate', $third_name);
        if (!$rate){
            $rate=ThirdConfigs::where('tc_code', $third_name)->whereOr('tc_set_code', $third_name)->value('tc_exchangerate');            if (!$rate){
                throw new \Exception("exchange rate error",0);
            }
        }
        //转换汇率
        if ($tomeorthird==0){
            $amount=bcdiv($amount,$rate,$scale);
            //$amount/=$rate;
        }else{
            $amount=bcmul($amount,$rate,$scale);
            //$amount*=$rate;
        }
        return $amount;
    }


    /**
     * 如果在本静态类初始化前需要使用继承类时调用
     * @param $apicfgs
     * @param $func
     * @return Third|bool
     * @author twt326
     * @date 2022-09-21 21:27
     */
    public static function initExtClass($apicfgs,$setrd_data=null){
        $gcfg=null;
        if ($setrd_data !=null){
            //集合三方
            /*if (isset($setrd_data['gid'])){//通过指定GID方式的调用
                $gcfg=Games::getConfigs($setrd_data['gid']);
                $classname=$gcfg['trdcfg']['tc_set_code'];
            }else{//通过三方回调信息的调用*/
                $classname=strtolower($apicfgs['trdcfg']['tc_set_code']);
            //}
            $exclass=getc('Third_'.$classname);
        }else {
            if (isset($apicfgs['trdcfg']['tc_set_code']) && !empty($apicfgs['trdcfg']['tc_set_code'])){//通过TCID或GID已经获取到正确的集成三方配置的
                $exclass = getc('Third_' . strtolower($apicfgs['trdcfg']['tc_set_code']));
            }else {
                $exclass = getc('Third_' . strtolower($apicfgs['trdcfg']['tc_code']));
            }
        }
        if (self::$extClass==null || $exclass !=self::$extClassName){
            if ($exclass) {
                self::$extClassName=$exclass;
                self::$extClass = new $exclass($gcfg==null?$apicfgs:$gcfg);
                if ($setrd_data !=null && $gcfg==null) self::$extClass->setrdConstruct($apicfgs,$setrd_data);
            }else{
                return false;
            }
        }else{
            self::$extClass->apicfgs=$gcfg==null?$apicfgs:$gcfg;
        }
        return self::$extClass;
    }

    /** 执行三方接口方法
     * TODO 因提取了initExtClass，并根据参数识别是否需要重建第三方继承类的对象，这里将来可以尝试将 apicfgs也进行缓存，以提高代码可阅读性和降低耦合
     * @param $apicfgs
     * @param $func
     * array array $otherkvs 需要解析的其它参数值，如无传[]
     * @return string|null
     * @author twt326
     * @date 2022-09-21 21:32
     */
    public static function run($apicfgs,$func,&$otherkvs){
        if (self::initExtClass($apicfgs)===false){
            Utils::L('ThirdErrLogs',[
                'msg'=>'三方平台Third继承类不存在',
                'suggest'=>'继承Third类并实现相应方法',
                'track'=>[
                    'apicfgs'=>$apicfgs,
                    'func'=>$func,
                    'otherkvs'=>$otherkvs
                ]]);
            return null;
            return "can not find class :".'Third_'.strtolower($apicfgs['trdcfg']['tc_code']);
        }
        if (strtoupper($func)==$func){
            $rfunc="G_".strtolower($func);
            return self::$rfunc($apicfgs,$otherkvs);
        }elseif(substr($func,0,10)=="GETBETLIST"){//用于支持某些平台有多种获取注单的方式
            $rfunc="G_".strtolower("GETBETLIST");
            return self::$rfunc($apicfgs,$otherkvs,$func);
        }else{
            return self::runapi($apicfgs,$func,$otherkvs);
        }
    }

    public static function G_getauth($apicfgs,&$otherkvs){
        $rlt=Http::http($apicfgs,'GETAUTH',self::$extClass,$otherkvs);
        if ($rlt){
            return Meta::parseResultData($apicfgs['apis']['GETAUTH'],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'],$otherkvs['uid'] ?? 0,0,$otherkvs);
        }else{
            return false;
        }
        //return [$apicfgs,"ourl"=>$apicfgs['trdcfg']['tc_api_urlpre'].$cfg['ga_route']."?".$cfg['ga_gparam'],"url"=>$url,'pd'=>$postdata,'rlt'=>$rlt,];
    }

    public static function G_getuserauth($apicfgs,&$otherkvs){
        $rlt=Http::http($apicfgs,'GETUSERAUTH',self::$extClass,$otherkvs);
        if ($rlt){
            return Meta::parseResultData($apicfgs['apis']['GETUSERAUTH'],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'],$otherkvs['uid'] ?? 0,0,$otherkvs);
        }else{
            return false;
        }
        //return [$apicfgs,"ourl"=>$apicfgs['trdcfg']['tc_api_urlpre'].$cfg['ga_route']."?".$cfg['ga_gparam'],"url"=>$url,'pd'=>$postdata,'rlt'=>$rlt,];
    }

    //公共注册第三方平台
    public static function G_register($apicfgs,&$otherkvs){
        $rlt=Http::http($apicfgs,'REGISTER',self::$extClass,$otherkvs);
        return $rlt;

    }

    //公共获取单用户第三方平台入口网址
    public static function G_getuserurl($apicfgs,&$otherkvs){
        $rlt=Http::http($apicfgs,'GETUSERURL',self::$extClass,$otherkvs);
        return $rlt;
    }

    //赠送免费游戏
    public static function G_givefreebets($apicfgs,&$otherkvs){
        $rlt=Http::http($apicfgs,'GIVEFREEBETS',self::$extClass,$otherkvs);
        return $rlt;
    }

    //创建免费游戏用户/将用户添加到免费游戏中
    public static function G_createfreebetuser($apicfgs,&$otherkvs){
        $rlt=Http::http($apicfgs,'CREATEFREEBETUSER',self::$extClass,$otherkvs);
        return $rlt;
    }

    public static function G_getbalance($apicfgs,&$otherkvs){
        $rlt=Http::http($apicfgs,'GETBALANCE',self::$extClass,$otherkvs);
        if ($rlt){
            $data=Meta::parseResultData($apicfgs['apis']['GETBALANCE'],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'],$otherkvs['uid'],0,$otherkvs);//这里可以在调用::run的地方进行处理，以免复杂化
            if (isset($data['tcbalance'])){
                $data['tcbalance']=self::transExchangerate($data['tcbalance'],0);//汇率转换
                if (isset($otherkvs['byWithdraw']) && $otherkvs['byWithdraw'] && isset($apicfgs['trdcfg']['tc_exchangeint']) && $apicfgs['trdcfg']['tc_exchangeint']==1){
                    $data['tcbalance']=floor($data['tcbalance']);
                }
            }
            return $data;
        }else{
            return false;
        }
    }

    public static function G_deposit($apicfgs,&$otherkvs){
        //汇率转换
        if (isset($otherkvs['o_amount'])){
            $otherkvs['o_amount']=self::transExchangerate($otherkvs['o_amount'],1);
            if (isset($apicfgs['trdcfg']['tc_exchangeint']) && $apicfgs['trdcfg']['tc_exchangeint']==1){
                $otherkvs['o_amount']=floor($otherkvs['o_amount']);//如果三方要求充值整数
            }
        }
        $rlt=Http::http($apicfgs,'DEPOSIT',self::$extClass,$otherkvs);
        if ($rlt){
            return Meta::parseResultData($apicfgs['apis']['DEPOSIT'],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'],$otherkvs['uid'],0,$otherkvs);//这里可以在调用::run的地方进行处理，以免复杂化
        }else{
            return false;
        }
    }

    public static function G_withdraw($apicfgs,&$otherkvs){
        //汇率转换
        if (isset($otherkvs['o_amount'])){
            $otherkvs['o_amount']=self::transExchangerate($otherkvs['o_amount'],1);
            if (isset($apicfgs['trdcfg']['tc_exchangeint']) && $apicfgs['trdcfg']['tc_exchangeint']==1){
                $otherkvs['o_amount']=floor($otherkvs['o_amount']);//如果三方要求提现整数
            }
        }
        $rlt=Http::http($apicfgs,'WITHDRAW',self::$extClass,$otherkvs);
        if ($rlt){
            $data= Meta::parseResultData($apicfgs['apis']['WITHDRAW'],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'],$otherkvs['uid'],0,$otherkvs);//这里可以在调用::run的地方进行处理，以免复杂化
            if (isset($data['tcbalance'])){
                $data['tcbalance']=self::transExchangerate($data['tcbalance'],0);//汇率转换
            }
            return $data;
        }else{
            return false;
        }
    }

    /** 执行其它API
     * @param $apicfgs
     * @param $func
     * array $otherkvs 需要解析的其它参数值，如无为null
     * @return |null
     * @author twt326
     * @date 2022-09-22 21:28
     */
    public static function runapi($apicfgs,$func,&$otherkvs){
        $rfunc=strtolower($func);
        if (self::$extClass && method_exists(self::$extClass, $rfunc)){
            return self::$extClass->$rfunc($apicfgs,$otherkvs);
        }else{
            return null;
        }
    }

    /**
     * 取得注单详细结果连结
     * @param $apicfgs
     * @param $otherkvs
     * @return array|false
     */
    public static function G_getbetdetail($apicfgs,&$otherkvs){
        $rlt=Http::http($apicfgs,'GETBETDETAIL', self::$extClass,$otherkvs);
        if ($rlt){
            return Meta::parseResultData($apicfgs['apis']['GETBETDETAIL'],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'],$otherkvs['uid'],0,$otherkvs);//这里可以在调用::run的地方进行处理，以免复杂化
        }else{
            return false;
        }
    }

    /**
     * 获取用户rtp
     * @param $apicfgs
     * @param $otherkvs
     * @return array|false
     */
    public static function G_getoneuserrtp($apicfgs,&$otherkvs){
        $rlt=Http::http($apicfgs,'GETONEUSERRTP', self::$extClass,$otherkvs);
        if ($rlt){
            return Meta::parseResultData($apicfgs['apis']['GETONEUSERRTP'],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'],$otherkvs['uid'],0,$otherkvs);//这里可以在调用::run的地方进行处理，以免复杂化
        }else{
            return false;
        }
    }

    /**
     * 设置用户rtp
     * @param $apicfgs
     * @param $otherkvs
     * @return array|false
     */
    public static function G_setuserrtp($apicfgs,&$otherkvs){
        $rlt=Http::http($apicfgs,'SETUSERRTP', self::$extClass,$otherkvs);
        if ($rlt){
            return Meta::parseResultData($apicfgs['apis']['SETUSERRTP'],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'],$otherkvs['uid'],0,$otherkvs);//这里可以在调用::run的地方进行处理，以免复杂化
        }else{
            return false;
        }
    }


    /**
     * 主动获取三方订单，用于对账，或者处理未结算订单
     * @param $apicfgs
     * @param $otherkvs
     * @param $func 用以支持某些平台可能有多种获取注单的形式
     * @return array|false
     */
    public static function G_getbetlist($apicfgs, &$otherkvs,$func='GETBETLIST'){
        // 时间戳
        if (!isset($otherkvs['start'])) {
            return false;
        }
        $rlt=Http::http($apicfgs,$func, self::$extClass,$otherkvs);
        if ($rlt){
            $res = Meta::parseResultData($apicfgs['apis'][$func],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'], 0,0, $otherkvs);//这里可以在调用::run的地方进行处理，以免复杂化
            // 下一轮开始时间
            $res['next'] = intval($otherkvs['start']) + self::$extClass->getBetListMaxTime() + 1;
            $res['r_list'] = self::$extClass->procBetData($res['r_list'] ?? [], $apicfgs);

            return $res;
        }else{
            return false;
        }
    }

    //公共获取单用户第三方平台入口网址
    public static function G_getTryUrl($apicfgs,&$otherkvs){
        $func = 'GETTRYURL';
        $rlt=Http::http($apicfgs,$func,self::$extClass,$otherkvs);
        if ($rlt){
            $res = Meta::parseResultData($apicfgs['apis'][$func],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'], 0,0, $otherkvs);//这里可以在调用::run的地方进行处理，以免复杂化
            return $res;
        }else{
            return false;
        }
    }

    /**
     * 处理游戏记录数据为可写入注单表的状态
     * @param $list 游戏注单数据列表
     * @return mixed
     */
    public function procBetData($list, $cfgs) {}
}
