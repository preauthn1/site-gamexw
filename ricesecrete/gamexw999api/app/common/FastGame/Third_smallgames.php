<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Meta;
use app\common\FastGame\Base\Utils;

class Third_smallgames extends Third
{

    /**
     * 签名
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-08-26 17:29
     */
    function MakeSign($config, &$data)
    {
//        $configs = $this->getConfig($data['channel'] ?? $data['game_code']);
//        $data['channel'] = $configs['channel'];
        $data['timestamp']=time();
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string = Http::ToUrlParams($data,false,['sign']);
        //签名步骤二：在string后加入KEY
        // dump($config['trdcfg']['tc_api_prikey']);
        // die;
        $string = $string .'&key='. $config['trdcfg']['tc_api_prikey'];
//        $string = $string .'&key='. $configs['key'];
        //$data['signstr']=$string;
        //签名步骤三：MD5加密
        $string = md5($string);
        //$data['signstr']=Utils::custGetSignStr($data,false,['sign']).$config['trdcfg']['tc_api_signkey'];//TEST
        $data['sign']=$string;

        return $string;
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
//        $configs = $this->getConfig($data['channelId']);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string = Http::ToUrlParams($data,false,['sign']);
        //签名步骤二：在string后加入KEY
        $string = $string .'&key='.  $config['trdcfg']['tc_api_prikey'];
        //$data['signstr']=$string;
        //签名步骤三：MD5加密
        $string = md5($string);
        //$data['signstr']=Utils::custGetSignStr($data,false,['sign']).$config['trdcfg']['tc_api_signkey'];//TEST
//        $data['sign']=$string;

        return $string;
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
        /*Utils::L('test',['TokenTimestampTranser',$in,time()+$in-600]);
        return time()+$in-600;*/
    }

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        // TODO: Implement transOtherKvs() method.
    }

    function getConfig($gtag)
    {
        if (!$gtag) {
            return [];
        }
        $config = env('gameconfig.smallgames'.$gtag, '');
        $config_arr = explode('-', $config);
        return ['channel' => $config_arr[0], 'key' => $config_arr[1]];
    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'GETUSERURL') {
            $language = Meta::initGetField('lang',$tcid,$gid,$uid) ;
            $kds['tcgameurl'] = $datas['data']['url'].'&lang='.$language;
        }
    }
}
