<?php


namespace app\common\FastGame;

use app\common\game\GameRequest;
use app\api\redis\ApiRedis;
use app\common\redis\CommomRedis;
use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;

class Third_super extends Third
{

    /**
     * 签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     */
    function MakeSign($config, &$data)
    {
        return [];
    }

    /**验证签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-08-26 17:32
     */
    function CheckSign($config, $data)
    {
        // 此三方站回复数据无签名
        return true;
    }

    /**
     * 转换第三方TOKEN过期时间为本平台支持的过期时间戳，将获取到的第三方TOKEN过期时间转换为整型秒为单位的时间戳
     * @param mixed $in
     * @return int
     * @author twt326
     * @date 2022-09-13 16:32
     */
    function TokenTimestampTranser($in)
    {
        return $in;
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;

        $kvs['VendorId'] = $config['trdcfg']['tc_tag'];

        $prkey = $config['trdcfg']['tc_api_prikey'];

        $kvs['Signature'] = MD5($kvs['VendorId'].$prkey);

        //所有
        // if($kvs['runfunc'] != 'GETBETLIST'){
        // }

        // if($kvs['runfunc'] == 'GETBETLIST'){
        // }
        if($kvs['runfunc'] == 'REGISTER'){
            $kvs['UpAccount'] = $config['trdcfg']['tc_api_signkey'];
            $kvs['langs'] = $config['trdcfg']['tc_langmap'];

        }

        // if($kvs['runfunc'] == 'GETUSERURL'){
        // }
        // if($kvs['runfunc'] == 'GETBALANCE'){
        // }

        if(in_array($kvs['runfunc'],['WITHDRAW','DEPOSIT'])){

            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['Point'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 0);
            $kvs['traceId'] = $config['trdcfg']['tc_tag'].'_'.md5(date("YmdHis") . Utils::random(16));

        }
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'GETUSERURL'){
            if($ohterkvs['platform'] == 2){//pc地址
                $kds['tcgameurl'] = $datas['Data']['RedirectUrl'];
            }else{
                //手机地址
                $kds['tcgameurl'] = $datas['Data']['MobileRedirectUrl'];
            }

        }

    }

}