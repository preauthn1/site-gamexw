<?php


namespace app\common\FastGame\Base;


use app\common\model\UserModel;
use app\model\GameConfigs;
use app\model\Games;
use app\model\ThirdConfigs;
use app\model\UserGames;
use app\model\Users;
use app\model\UserThirds;
use spec\League\Flysystem\Cached\CachedAdapterSpec;

/**
 *  无数据定义和操作表
 * Class Meta
 * @package app\common\FastGame\Base
 * @author twt326
 * @date 2022-08-13 15:39
 */
class Meta
{
    //获取游戏投注列表开始标志位字段 --  弃用，在三方接口读取封装中处理
    const GGETBETLISTSTART='ggetbetliststart';


    //第三方平台访问AUTH TOKEN
    const TCTOKEN='tctoken';
    //第三方平台AUTH TOKEN过期时间
    const TCTOKENEXP='tctokentime';
    //第三方平台注册用户时的唯一标识
    const TCUSERKEY='tcuserkey';
    //第三方平台注册用户时的在第三方的名字
    const TCUSERNAME='tcusername';
    //第三方平台用户余额
    const TCUSERBALANCE='tcbalance';
    //第三方平台转账transID
    const TCTRANSBALANCEID='tctransbanid'; //在返回结果中定义，但不需要封装处理
    //第三方平台某用户的游戏进入地址
    const TCGAMEURL='tcgameurl';
    // 游戏结果详情连接
    const TCGAMEDETAILURL='tcgamedetailurl';




    //用户第三方TOKEN标志字段
    const UGTOKEN='uttoken';
    //用户第三方TOKEN过期时间标志字段
    const UGTOKENEXP='uttokentime';

    //-------以下这些暂时无用
    //用户第三方游戏URL
    const UGAMEURL='r_usergameurl';
    //用户第三方余额字段
    const GBALANCE='r_balance';
    //---------------

    //可以由通用接口方法调用的用户元数据
    const UFIELD=[
        'uid'=>'id',
        'uname'=>'rec_code',
        'ubalance'=>'balance',
        'uip'=>'reg_ip',
        'ulastip'=>'end_login_ip',
        'phone'=>'phone'
    ];

    //用户在三方游戏中的信息 ：user_thirds 中的数据
    const UTFIELD=[
        'tcbalance'=>'ut_balance',
        'uttoken'=>'ut_token',
        'uttokentime'=>'ut_tokentime'
    ];

    //可以由通用接口方法调用的游戏元数据
    const GFIELD=[
        'gid'=>'g_id',
        'gtcid'=>'g_tcid',//不显示使用
        'gtokentime'=>'gc_tokentime',
        //'gapikey'=>'gc_api_prikey',
        //'gapiurlpre'=>'gc_api_urlpre',
        'gcode'=>'g_code',
        'gtag'=>'g_tag',
        'grtp'=>'g_rtp',
        'ggetbetliststart'=>'gc_betlist_sflag',
        'ggetbetlistsize'=>'gc_betlist_getsize'
    ];
    //可以由通用接口方法调用的三方平台元数据
    const TFIELD=[
        'tcapikey'=>'tc_api_prikey',
        'tcapiurlpre'=>'tc_api_urlpre',
        'tcapisignkey'=>'tc_api_signkey',
        'tctag'=>'tc_tag',
        'tctoken'=>'tc_token',
        'tctokentime'=>'tc_tokentime',
        'tclangmap'=>'tc_langmap',
        'tcname'=>'tc_name',
        'tcexchangerate'=>'tc_exchangerate',
        'tccurrency'=>'tc_currency',
        'tcminbet'=>'tc_min_bet',
        'tcmaxbet'=>'tc_max_bet',
        'tcexfield'=>'tc_exfield'//自定义扩展信息
    ];

    const SFIELD=[
        'page',
        'pagesize',
        //test
        'utoken',
        'lang',
        'random',
        'random30',
        'timestamp',
    ];

    //可以根据三方接口返回值进行更新数据的元数据字段
    const CANUPADATEFIELDS=[
        self::GGETBETLISTSTART,self::UGTOKEN,self::GBALANCE,self::UGTOKENEXP,self::UGAMEURL,self::TCTOKEN,self::TCTOKENEXP,
        self::TCUSERKEY,//专用于在第三方平台注册时的返回处理，如其它接口也有返回此数据时请定义另外的标识
        self::TCUSERNAME,//专用于在第三方平台注册时的返回处理，如其它接口也有返回此数据时请定义另外的标识
        self::TCGAMEURL,self::TCUSERBALANCE, self::TCGAMEDETAILURL
    ];

    public static $uinfo=null;//用户元数据
    public static $ginfo=null;//游戏元数据
    public static $tinfo=null;//三方平台元数据
    public static $utinfo=null;//用户三方平台元数据
    public static $tcid=null;
    public static $gid=null;
    public static $uid=null;

    /** 初始化元数据
     * @param $field
     * @param $tcid 三方平台ID
     * @param $gid
     * @param $uid
     * @return |null
     * @author twt326
     * @date 2022-08-24 17:11
     */
    public static function initGetField($field,$tcid,$gid,$uid)
    {
        Utils::L("{$tcid}/initGetField",['initGetField:in',$field,$tcid,$gid,$uid]);
        if ($tcid !=self::$tcid) {
            self::$tinfo=null;
        }
        if ($uid !=self::$uid){
            self::$uinfo=null;
            self::$utinfo=null;
        }
        if ($gid !=self::$gid){
            self::$uinfo=null;
            self::$ginfo=null;
            self::$utinfo=null;
        }
        self::$tcid=$tcid;
        self::$gid=$gid;
        self::$uid=$uid;
        if (array_key_exists($field, self::UFIELD)) {
            if (self::$uinfo == null) {
                self::$uinfo=UserModel::field(array_column(self::UFIELD, null, null))
                    ->join('user_games','id=ug_uid and ug_gid='.$gid,'left')
                    ->where('id',$uid)->find()->toArray();
            }

            //TODO uname 各平台要求长度可能不一样，目前默认按反波最少6位操作，如果发现有最低要求超过6位时要进行统一处理，比如加上ID或其它进行凑数，这样就需要在返回数据处理时也做相应的更改
            return isset(self::$uinfo[self::UFIELD[$field]]) ? self::$uinfo[self::UFIELD[$field]] : null;
        }

        if (array_key_exists($field, self::GFIELD)) {
            if (self::$ginfo == null) {
                self::$ginfo=Games::field(array_column(self::GFIELD,null,null))
                    ->join('dq_game_configs','g_id=gc_gid','left')
                    ->where('g_id',$gid)->find()->toArray();
            }
            return isset(self::$ginfo[self::GFIELD[$field]]) ? self::$ginfo[self::GFIELD[$field]] : null;
        }

        if (array_key_exists($field, self::TFIELD)) {
            Utils::L("{$tcid}/initGetField",['initGetField:tfield',$field]);
            if (self::$tinfo == null) {
                self::$tinfo=ThirdConfigs::field(array_column(self::TFIELD,null,null))
                    ->where('tc_id',$tcid)->find()->toArray();
                Utils::L("{$tcid}/initGetField",['initGetField:tfield',$field,self::$tinfo]);

            }
            if ($field=='tctoken'){//如果token超时了 重新获取一次
                if (self::$tinfo['tc_tokentime']<=time() || self::$tinfo['tc_tokentime']==0){
                    $gcfgs=Games::getConfigs($gid,$tcid);
                    Utils::L("{$tcid}/initGetField",['initGetField:tctoken',$gid,$tcid,isset($gcfgs['spcapis']['getauthtoken'])]);
                    if (isset($gcfgs['spcapis']['getauthtoken'])){
                        $okv=[];
                        $okv = array_merge($okv,["uid" => $uid]);
                        $trlt=Third::run($gcfgs,$gcfgs['spcapis']['getauthtoken']['ga_func'],$okv);
                        self::$tinfo=ThirdConfigs::field(array_column(self::TFIELD,null,null))
                            ->where('tc_id',$tcid)->find()->toArray();
                        //self::$tinfo['tc_token']=$trlt[self::TCTOKEN];
                        //self::$tinfo['tc_tokentime']=$trlt[self::TCTOKENEXP];
                        Utils::L("{$tcid}/initGetField",['initGetField:tctokenget',self::$tinfo,$trlt]);
                    }
                }
            }
            return isset(self::$tinfo[self::TFIELD[$field]]) ? self::$tinfo[self::TFIELD[$field]] : null;
        }

        if (array_key_exists($field, self::UTFIELD)) {
            Utils::L("{$tcid}/initGetField",['initGetField:utfield',$field]);
            if (self::$utinfo == null) {
                self::$utinfo=UserThirds::field(array_column(self::UTFIELD,null,null))
                    ->where('ut_tcid',$tcid)->where('ut_uid',$uid)->find()->toArray();
                Utils::L("{$tcid}/initGetField",['initGetField:utfield',$field,self::$utinfo]);
                if ($field=='uttoken'){//如果token超时了 重新获取一次
                    if (self::$utinfo['ut_tokentime']<=time() || self::$utinfo['ut_tokentime']==0){
                        $gcfgs=Games::getConfigs($gid,$tcid);
                        if (isset($gcfgs['spcapis']['getusertoken'])){
                            Utils::L("{$tcid}/initGetField",['initGetField:uttoken',$gid,$tcid,$gcfgs['spcapis']['getusertoken']]);
                            $okv=[];
                            $okv = array_merge($okv,["uid" => $uid]);
                            $trlt=Third::run($gcfgs,$gcfgs['spcapis']['getusertoken']['ga_func'],$okv);
                            self::$utinfo=UserThirds::field(array_column(self::UTFIELD,null,null))
                                ->where('ut_tcid',$tcid)->where('ut_uid',$uid)->find()->toArray();
                            //self::$utinfo['tc_token']=$trlt[self::TCTOKEN];
                            //self::$utinfo['tc_tokentime']=$trlt[self::TCTOKENEXP];
                            Utils::L("{$tcid}/initGetField",['initGetField:utokenget',self::$utinfo,$trlt]);
                        }
                    }
                }
            }
            return isset(self::$utinfo[self::UTFIELD[$field]]) ? self::$utinfo[self::UTFIELD[$field]] : null;
        }

        if (in_array($field, self::SFIELD)) {
            switch ($field){
                case 'page':
                    return Params::$page;
                    break;
                case 'psize':
                    return Params::$psize;
                    break;
                case 'utoken':
                    return Params::$utoken;
                    break;
                case 'lang':
                    $langs=self::initGetField('tclangmap',$tcid,$gid,$uid);
                    $langs=json_decode($langs,true);
                    if (isset($langs[Params::$lang])){
                        return $langs[Params::$lang];
                    }else{
                        return 'zh-CN';
                    }
                    break;
                case 'random':
                    return Utils::random(32);
                    break;
                case 'random30':
                    return Utils::random(30);
                    break;
                case 'timestamp':
                    return time();
                    break;
            }
        }


        return null;
    }

    /** 元数据标签替换为数据
     * @param $odt
     * @param $tcid
     * @param $gid
     * @param $uid
     * @return mixed
     * @author twt326
     * @date 2022-08-24 17:12
     */
    public static function parseMateData($odt,$tcid,$gid,$uid){

        $fields=[];
        $fv=[];
        if (is_string($odt)){
            //$tfd=[];
            preg_match_all("/\{\w+\}/",$odt,$fields);
            $fields=$fields[0];// array_column($fields,null,null);
            foreach ($fields as $v){
                if (!isset($fv["$v"])) $fv["$v"]=self::initGetField(substr($v,1,-1),$tcid,$gid,$uid);
                if ($fv["$v"]!==null) $odt=str_replace($v,$fv["$v"],$odt);
            }
            //return $fields[0];
        }else if(is_array($odt)){
            self::parseMateArrData($odt,$tcid,$gid,$uid);
            /*foreach ($odt as $k=>$v){
                if (is_array($v)){
                    self::parseMateData($odt[$k],$tcid,$gid,$uid);
                }else {
                    $tfd = [];
                    preg_match_all("/\{\w+\}/", $v, $tfd);
                    $fields = array_merge($fields, $tfd[0]);//array_column($tfd,0));
                    foreach ($tfd[0] as $vv) {
                        if (!isset($fv["$vv"])) $fv["$vv"] = self::initGetField(substr($vv, 1, -1), $tcid, $gid, $uid);

                        if ($fv["$v"] !== null) $odt[$k] = str_replace($vv, $fv["$vv"], $odt[$k]);
                    }
                }

            }
            Utils::L("{$tcid}/parseMateData",['parseMetaData:',$fields,$fv]);*/
            //return $fields;
        }
        $fv=[];
        //return [$fields,$gid,$uid];
        /*foreach ($fields as $v){
            $fv["$v"]=self::initGetField(substr($v,1,-1),$gid,$uid);
        }*/

        //return ["fields"=>$fields,"fv"=>$fv];
        return $odt;
    }

    //切开标签，用于后续处理 TODO 可继续扩展
    public static function splitTag($tag){
        $tag=substr($tag, 1, -1);
        $tag=explode("|",$tag);
        $c=count($tag);
        return [
            'type'=>$c==1?'':$tag[0],
            'tag'=>$c==1?$tag[0]:$tag[1]
        ];
    }

    /** 替换标签元素
     * @param $type 元素类型
     * @param $data 要处理的带模板数据
     * @param $otag 原始标签
     * @param $rdata 要替换的数据
     * @return void
     */
    public static function praseDataByTag($type,&$data,$otag,$rdata){
        switch ($type) {
            case 'n':
                $data=floatval($rdata);
                break;
            case 'i':
                $data=intval($rdata);
                break;
            case 'b':
                $data = ($rdata===true || $rdata=='1' || $rdata==1 || $rdata=='true')?true:false;
                break;
            case 'j':
                $data = str_replace($otag, json_encode($rdata), $data);
                break;
            case 'ts'://时间戳
                $data = intval(is_int($rdata)?$rdata:strtotime($rdata));
                break;
            default:
                if( is_array($rdata) || is_object($rdata)){
                    $data=$rdata;//如果是数组则直接替换键值
                }else{
                    $data = str_replace($otag, $rdata, $data);
                }
                break;

        }
    }

    public static function parseMateArrData(&$odt,$tcid,$gid,$uid){

        //$fields=[];
        $fv=[];

        foreach ($odt as $k=>$v){
            if (is_array($v)){
                self::parseMateArrData($odt[$k],$tcid,$gid,$uid);
            }else {
                $tfd = [];
                preg_match_all("/(\{\w+\|\w+\})|(\{\w+\})/", $v, $tfd);
                //$fields = array_merge($fields, $tfd[0]);//array_column($tfd,0));
                foreach ($tfd[0] as $vv) {
                    $tags=self::splitTag($vv);
                    if (!empty($tags['tag'])) {
                        $tag=$tags['tag'];
                        if (!isset($fv[$tag])) $fv[$tag] = self::initGetField($tag, $tcid, $gid, $uid);

                        if ($fv[$tag] !== null) {
                            self::praseDataByTag($tags['type'],$odt[$k],$vv,$fv[$tag]);
                            /*switch ($tags['type']){
                                case 'n':
                                    $odt[$k] = floatval($fv[$tag]);
                                    break;
                                case 'i':
                                    $odt[$k] = intval($fv[$tag]);
                                    break;
                                case 'b':
                                    $odt[$k] = ($fv[$tag]===true || $fv[$tag]=='1' || $fv[$tag]==1 || $fv[$tag]=='true')?true:false;
                                    break;
                                case 'j':
                                    $odt[$k] = str_replace($vv, json_encode($fv[$tag]), $odt[$k]);
                                    break;
                                default:
                                    $odt[$k] = str_replace($vv, $fv[$tag], $odt[$k]);
                                    if (is_string($fv[$tag])) {
                                        $odt[$k] = str_replace($vv, $fv[$tag], $odt[$k]);
                                    }else{
                                        $odt[$k]=$fv[$tag];//如果是数组则直接替换键值
                                    }
                                    break;
                            }*/

                        }
                    }
                }
            }

        }
        //Utils::L("{$tcid}/parseMateData",['parseMetaData:',$fields,$fv]);
        //return $fields;

        $fv=[];
        //return [$fields,$gid,$uid];
        /*foreach ($fields as $v){
            $fv["$v"]=self::initGetField(substr($v,1,-1),$gid,$uid);
        }*/

        //return ["fields"=>$fields,"fv"=>$fv];
        return $odt;
    }


    /**自定义参数替换
     * @param $kvs
     * @param $data
     * @param Third $pclass 传flase时用于单纯处理模板数据
     * @return mixed
     * @author twt326
     * @date 2022-09-18 12:35
     */
    public static function cusParseMateData(&$kvs,$data,$pclass){
        if ($pclass) $pclass->transOtherKvs($kvs);
        $fields=[];
        if (is_string($data)){
            preg_match_all("/\{\w+\}/",$data,$fields);
            $fields=$fields[0];// array_column($fields,null,null);
            foreach ($fields as $v){
                $kf=substr($v,1,-1);
                if (isset($kvs[$kf])) {
                    $data = str_replace($v, $kvs[$kf], $data);
                }
            }
        }else if(is_array($data)){
            //foreach ($data as $k=>$v){
            self::cusParseMateArrData($kvs,$data);
            /*$tdf=[];
            preg_match_all("/\{\w+\}/",$v,$tfd);
            foreach ($tfd[0] as $vv){
                $kf=substr($vv,1,-1);
                if (isset($kvs[$kf])) {
                    $data[$k] = str_replace($vv, $kvs[$kf], $data[$k]);
                }
            }*/
            //}
        }
        return $data;
    }

    public static function cusParseMateArrData(&$kvs,&$data){
        $fields=[];

        foreach ($data as $k=>$v){
            if (is_array($v)){
                self::cusParseMateArrData($kvs,$data[$k]);
            }else {
                $tdf = [];
                preg_match_all("/(\{\w+\|\w+\})|(\{\w+\})/", $v, $tfd);
                foreach ($tfd[0] as $vv) {
                    $tags=self::splitTag($vv);
                    if (!empty($tags['tag'])) {
                        $tag=$tags['tag'];
                        if (isset($kvs[$tag])) {
                            self::praseDataByTag($tags['type'],$data[$k],$vv,$kvs[$tag]);
                            /*switch ($tags['type']) {
                                case 'n':
                                    $data[$k]=floatval($kvs[$tag]);
                                    break;
                                case 'i':
                                    $data[$k]=intval($kvs[$tag]);
                                    break;
                                case 'b':
                                    $data[$k] = ($kvs[$tag]===true || $kvs[$tag]=='1' || $kvs[$tag]==1 || $kvs[$tag]=='true')?true:false;
                                    break;
                                case 'j':
                                    $data[$k] = str_replace($vv, json_encode($kvs[$tag]), $data[$k]);
                                    break;
                                default:
                                    if (is_string($kvs[$tag])) {
                                        $data[$k] = str_replace($vv, $kvs[$tag], $data[$k]);
                                    }else{
                                        $data[$k]=$kvs[$tag];//如果是数组则直接替换键值
                                    }
                                    break;

                            }*/
                        }
                    }
                }
            }
        }

        return $data;
    }

    /** 返回数据根据元数据标签提取值
     * @param $apicfgs
     * @param $datas
     * @param Third $thirdObj
     * @param $tcid
     * @param $uid
     * @param $gid
     * @param $otherkvs 初始调用时传入的参数
     * @author twt326
     * @date 2022-08-24 17:12
     */
    public static function parseResultData($apicfgs,$datas,$thirdObj,$tcid,$uid,$gid=0,$otherkvs=null){
        Utils::L("{$tcid}/parseResultData",['parseResultData:in',$apicfgs,$datas,$tcid,$uid,$gid]);
        $kfs=$apicfgs['ga_keyfield']?json_decode($apicfgs['ga_keyfield'],true):[];//返回值关系
        $kds=[];
        Utils::L("{$tcid}/parseResultData",['parseResultData:kfs',$kfs]);
        if(is_array($kfs)){//处理kfs不是数组的报错
            foreach ($kfs as $k=>$v){
                if ($k!='rightflag'){
                    Utils::L("{$tcid}/parseResultData",['parseResultData:getkey',$v,$v['key']]);
                    $kds[$k]=Utils::getDataFromArrayByStrKey($v['key'],$datas);
                }
            }

        }
        $thirdObj->transResultData($apicfgs['ga_func'],$kds,$datas,$tcid,$uid,$gid,$otherkvs);//对返回结果进行可能的预处理
        $flag=self::updateMetaDatas($apicfgs,$kds,$thirdObj,$tcid,$uid,$gid);
        if (!$flag) {
            //TODO 优化健壮性时要处理更新信息出错的情况，或者在 updateMetaDatas 中处理，或者在调用本方法的地方进行处理
        }
        return $kds;
    }

    /** 更新一些接口返回值中的特殊字段的值到数据表或缓存--用于替代下面的updateMetaField方法
     * TODO 未来业务如果复杂的话，可启用updateMetaField方法用于拆分代码
     * TCGAMEURL更新 Params::$lang必须有值
     * @param $apicfgs
     * @param $datas
     * @param Third $thirdObj
     * @param $tcid
     * @param $uid
     * @param $gid
     * @author twt326
     * @date 2022-09-20 23:33
     */
    public static function updateMetaDatas($apicfgs,&$datas,$thirdObj,$tcid,$uid,$gid=0){
        //Utils::L("{$tcid}/updateMetaDatas",['updateMetaDatas:in',$datas,$tcid,$uid,$gid,get_class($thirdObj)]);
        foreach ($datas as $field=>$value){
            Utils::L("{$tcid}/updateMetaDatas",['updateMetaDatas:each',$field,$value]);
            if (in_array($field,self::CANUPADATEFIELDS)){
                //Utils::L("{$tcid}/updateMetaDatas",['updateMetaDatas:inarray',$field,$value]);
                $flag=true;
                switch ($field){
                    case self::UGTOKEN:
                        $flag=UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find()->save([
                            'ut_token'=>$value
                        ]);
                        break;
                    case self::UGTOKENEXP:
                        $datas[$field]=$thirdObj->UTokenTimestampTranser($value);
                        Utils::L("{$tcid}/updateMetaDatas",['updateMetaDatas:updateusertokenexp',$datas,$apicfgs['ga_tcid'],$tcid]);
                        $flag=UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find()->save([
                            'ut_tokentime'=>$datas[$field]
                        ]);
                        break;
                    /*case self::GBALANCE:
                        $flag=UserGames::where('ug_uid',self::$uinfo['u_id'])->where('ug_gid',self::$ginfo['g_id'])->update([
                            'ug_balance'=>$value
                        ]);
                        break;
                    case self::GGETBETLISTSTART:
                        $flag=GameConfigs::where('gc_gid',self::$ginfo['g_id'])->update([
                            'gc_betlist_sflag'=>$value
                        ]);
                        break;*/
                    case self::TCTOKEN:
                        $flag=ThirdConfigs::where('tc_id',$apicfgs['ga_tcid'])->find()->save([
                            'tc_token'=>$value
                        ]);
                        Utils::L("{$tcid}/updateMetaDatas",['updateMetaDatas:updatetctoken',$flag]);
                        break;
                    case self::TCTOKENEXP://调用继承类对象方法处理TOKEN过期时间
                        $datas[$field]=$thirdObj->TokenTimestampTranser($value);
                        Utils::L("{$tcid}/updateMetaDatas",['updateMetaDatas:updatetokenexp',$datas,$apicfgs['ga_tcid'],$tcid]);
                        $flag=ThirdConfigs::where('tc_id',$apicfgs['ga_tcid'])->find()->save([
                            'tc_tokentime'=>$datas[$field]
                        ]);
                        break;
                    case self::TCUSERKEY://注册
                        //判断用户是否已经存在
                        $guser=UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
                        if (!$guser){
                            //TODO 错误处理
                            UserThirds::create([
                                'ut_uid'=>$uid,
                                'ut_tcid'=>$tcid,
                                'ut_trdid'=>$value,
                                'ut_intime'=>time(),
                                'ut_state'=>0
                            ]);
                        }
                        Utils::L("{$tcid}/updateMetaDatas",['updateMetaDatas:createuserbykey',$guser]);
                        break;
                    case self::TCUSERNAME://注册时的用户名
                        $guser=UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
                        if (!$guser){
                            //TODO 错误处理
                            UserThirds::create([
                                'ut_uid'=>$uid,
                                'ut_tcid'=>$tcid,
                                'ut_trdname'=>$value,
                                'ut_intime'=>time(),
                                'ut_state'=>0
                            ]);
                        }else{
                            if (empty($guser->ut_trdname)){
                                $guser->ut_trdname=$value;
                                //TODO 错误处理
                                $guser->save();
                            }else{
                                if ($guser->ut_trdname != $value){
                                    //TODO 有可能的错误处理，返回的三方平台用户名值与原存储值不一样，可能是因为 $uinfo['u_id'] 值被意外改变
                                }
                            }
                        }
                        Utils::L("{$tcid}/updateMetaDatas",['updateMetaDatas:createuserbyname',$guser]);
                        break;
                    case self::TCGAMEURL://TCGAMEURL更新 Params::$lang必须有值
                        //TODO 将最后进入三方平台和出三方平台的时间修改替换到这里
                        $guser=UserGames::where('ug_uid',$uid)->where('ug_gid',$gid)->where('ug_lang',Params::$lang)->find();
                        if ($guser){
                            $guser->ug_url=$value;
                            $guser->ug_lastupdate=time();
                            $guser->save();
                        }else{
                            Utils::L("{$tcid}/updateMetaDatas",['updateMetaDatas:createUserGameRec',[
                                'ug_uid'=>$uid,
                                'ug_tcid'=>$tcid,
                                'ug_gid'=>$gid,
                                'ug_lang'=>Params::$lang,//self::initGetField('lang',$tcid,$gid,$uid),
                                'ug_url'=>$value,
                                'ug_lastupdate'=>time()
                            ]]);
                            UserGames::create([
                                'ug_uid'=>$uid,
                                'ug_tcid'=>$tcid,
                                'ug_gid'=>$gid,
                                'ug_lang'=>Params::$lang,//self::initGetField('lang',$tcid,$gid,$uid),
                                'ug_url'=>$value,
                                'ug_lastupdate'=>time()
                            ]);
                        }
                        break;
                    case self::TCUSERBALANCE://三方余额更新
                        $guser=UserThirds::where('ut_uid',$uid)->where('ut_tcid',$tcid)->find();
                        if ($guser){
                            $guser->ut_balance=$value;
                            $guser->save();
                        }else{
                            //异常情况：没有用户在第三方平台的信息
                            //TODO 异常日志记录
                        }
                        break;
                }
                if ($flag){
                    if (in_array($field,array_keys(self::UFIELD))){
                        self::$uinfo=null;//更新信息后清除缓存  TODO 考虑：为防止上面的更新SWITCH中因清除缓存后导致拿不到需要参与计算的数据，此处的 更新信息后清除缓存 应修改为 更新后更新缓存 ，可不在此处进行，而直接在CASE中去更新缓存值，以下同
                    }else if (in_array($field,array_keys(self::GFIELD))){
                        self::$ginfo=null;//更新信息后清除缓存
                    }else if (in_array($field,array_keys(self::TFIELD))){
                        self::$tinfo=null;//更新信息后清除缓存
                    }

                }else{
                    return false;
                }
            }else{
                return false;
            }
        }
        return true;
    }

    /** 更新一些特殊字段的值   ----  目前弃用
     * @param $field
     * @param $value
     * @return bool
     * @author twt326
     * @date 2022-08-24 21:53
     */
    public static function updateMetaField($field,$value){
        if (in_array($field,self::CANUPADATEFIELDS)){
            $flag=false;
            switch ($field){
                case self::UGTOKEN:
                    $flag=UserGames::where('ug_uid',self::$uinfo['u_id'])->where('ug_gid',self::$ginfo['g_id'])->update([
                        'ug_lastin'=>time(),
                        'ug_token'=>$value
                    ]);
                    break;
                case self::GBALANCE:
                    $flag=UserGames::where('ug_uid',self::$uinfo['u_id'])->where('ug_gid',self::$ginfo['g_id'])->update([
                        'ug_balance'=>$value
                    ]);
                    break;
                case self::GGETBETLISTSTART:
                    $flag=GameConfigs::where('gc_gid',self::$ginfo['g_id'])->update([
                        'gc_betlist_sflag'=>$value
                    ]);
                    break;
            }
            if ($flag){
                if (in_array($field,array_keys(self::UFIELD))){
                    self::$uinfo=null;//更新信息后清除缓存
                }else if (in_array($field,array_keys(self::GFIELD))){
                    self::$ginfo=null;//更新信息后清除缓存
                }
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

}
