<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;
use think\facade\Request;

class Third_ka extends Third
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
        $hash = hash_hmac('sha256', json_encode($data), $config['trdcfg']['tc_api_signkey']);
        return $hash;
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
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        if($func == 'GETUSERURL'){
            $url = $config['trdcfg']['tc_token'];
            $g = Meta::initGetField('gtag',$tcid,$gid,$uid); //游戏标识
            $p = $config['trdcfg']['tc_tag'];
            $u = Meta::initGetField('uname',$tcid,$gid,$uid);
            $ak = $config['trdcfg']['tc_api_prikey'];
            $cr = $config['trdcfg']['tc_currency'];
            $t = rand(1,9999999);
            $loc = $config['trdcfg']['tc_langmap'];
            $kds['tcgameurl'] = $url.'?g='.$g.'&p='.$p.'&u='.$u.'&ak='.$ak.'&cr='.$cr.'&t='.$t.'&loc='.$loc;
        }

        if($func == 'GETBETDETAIL'){
            $order_sn = explode('_',$ohterkvs['gbsn'])[0];
            $url = $config['trdcfg']['tc_token'];
            $g = Meta::initGetField('gtag',$tcid,$ohterkvs['betInfo']['gb_gid'],$uid); //游戏标识
            $p = $config['trdcfg']['tc_tag'];
            $ak = $config['trdcfg']['tc_api_prikey'];
            $grts = time();
            $grha = hash_hmac('sha256', $order_sn.$grts, $config['trdcfg']['tc_api_signkey']);
            $kds['tcgamedetailurl'] = $url.'?g='.$g.'&p='.$p.'&ak='.$ak.'&grid='.$order_sn.'&grts='.$grts.'&grha='.$grha;
        }

    }

}
