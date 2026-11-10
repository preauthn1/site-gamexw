<?php

namespace app\common\FastGame;

use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Meta;
use app\common\FastGame\Base\Redis;
use app\common\FastGame\Base\Utils;
use app\manage\controller\Game;

class Third_wazdan extends Base\Third
{

    private $product="WAZDAN";

    /**
     * @param $config
     * @param $data
     * @return string[]
     */
    function MakeSign($config, &$data)
    {
        //return ["authorization"=>"Basic ".base64_encode($config['trdcfg']['tc_tag'].":".$config['trdcfg']['tc_api_prikey'])];
        return ["Signature"=>hash_hmac('sha256',json_encode($data),$config['trdcfg']['tc_api_prikey'])];
    }

    /**
     * @inheritDoc
     */
    function CheckSign($config, $data)
    {
        if (isset($data['return_headers'])){
            $header=$data['return_headers'];
        }elseif(isset($data['header'])){
            $header=$data['header'];
        }elseif(isset($data['headers'])){
            $header=$data['headers'];
        }else{
            $header=$data;
        }
        if (is_array($header) && isset($header['authorization'])){
            $au=explode(" ",$header['authorization']);
            if (isset($au[1])){
                return $au[1]==base64_encode($config['trdcfg']['tc_api_signkey']);
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    function TokenTimestampTranser($in)
    {
        return $in;
    }

    /**
     * 生成和获取token
     * @param null|string $token null生成token，string读取token
     * @param null|string $uname token==null时必须有值，表示生成此用户的TOKEN,token!=null时如有此参数则更新时效
     * @return string|null token=null返回token,否则返回用户name或null
     */
    function utoken($token=null,$uname=null){
        if ($token){
            //读
            $runame=Redis::get($this->product."_usertoken_".$token,2);
            if ($uname && $runame==$uname){
                Redis::setex($this->product."_usertoken_".$token,$uname,1800,2);
            }
            return $runame;
        }else{
            //创建
            $token=md5($uname.'_'.time().'_'.rand(100000,999999));
            Redis::setex($this->product."_usertoken_".$token,$uname,1800,2);
            return $token;
        }
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;
        if($kvs['runfunc']=='GETUSERURL'){
            $kvs['platform'] = $kvs['platform'] == 1 ? 'desktop' : 'mobile'; //平台标识
        }
        // TODO: Implement transOtherKvs() method.
    }

    function getgamelist($apicfgs,$otherkvs){
        list("weburl"=>$weburl,"partnercode"=>$partnercode,"license"=>$license)=json_decode(Meta::initGetField('tcexfield',$apicfgs['trdcfg']['tc_id'],0,0),true);
        $otherkvs['license'] = $license;
        $rlt=Http::http($apicfgs,'GETGAMELIST',$this,$otherkvs);
        if ($rlt){
            $rlt= Meta::parseResultData($apicfgs['apis']['GETGAMELIST'],$rlt,self::$extClass,$apicfgs['trdcfg']['tc_id'],$otherkvs['uid'] ?? 0,0,$otherkvs);
            $list=[];
            foreach ($rlt['list'] as $k => $v){
                $item=[
                    'g_tcid'=>$apicfgs['trdcfg']['tc_id'],
                    'g_web_tcid'=>$apicfgs['trdcfg']['tc_id'],
                    'g_code'=>$v['gameId'],
                    'g_tag'=>$v['gameId'],
                    'g_type'=>0,
                    'gi_name'=>$v['gameName'],
                    'gi_thirdlang'=>'en',
                    'g_icon'=>$v['thumbUrl'][0],
                    'gi_headimg'=>$v['thumbUrl'][1]
                ];
                array_push($list,$item);
                Game::addGame($item);
            }
            return $list;
        }else{
            return false;
        }
    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'GETUSERURL'){
            $username=Meta::initGetField('uname',$tcid,$gid,$uid);
            $utoken=Meta::initGetField('uttoken',$tcid,$gid,$uid);
            $gameurl=Meta::initGetField('tcgameurl',$tcid,$gid,$uid);
            $lang=Meta::initGetField('lang',$tcid,$gid,$uid);
            $gtag=Meta::initGetField('gtag',$tcid,$gid,$uid);
            $gettoken=true;
            if($utoken) {
                $uname = $this->utoken($utoken, $username);
                if ($uname && $uname== $username) $gettoken=false;
            }
            if ($gettoken){
                $utoken = $this->utoken(null,$username);
                $kds['uttoken'] = $utoken;
            }
            if ($gettoken || empty($gameurl)){
                list("weburl"=>$weburl,"partnercode"=>$partnercode,"license"=>$license)=json_decode(Meta::initGetField('tcexfield',$tcid,$gid,$uid),true);
                $tctag=Meta::initGetField('tctag',$tcid,$gid,$uid);
                $kds['tcgameurl'] = $weburl."/{$partnercode}/gamelauncher?
operator={$tctag}&game={$gtag}&mode=real&token={$utoken}&license={$license}&lang={$lang}
&platform={$ohterkvs['platform']}&lobbyUrl=".getAppUrl();
            }
        }
    }
}