<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Meta;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Utils;

class Third_gmag extends Third
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
        //签名步骤一：按字典序排序参数
        // if (isset($data['amount'])) {
        //     $data['amount'] = floatval($data['amount']);
        // }
        ksort($data);
        $string = '';
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_SLASHES);
            }
            $string .= $k . '=' . $v . '&';
        }
        // unicode string replace
        $string = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
            return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
        }, $string);
        return md5(substr($string, 0, -1).$config['trdcfg']['tc_api_prikey']);
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

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $platform = $kvs['platform'] ?? 0;
        $kvs['platForm'] = $platform == 1 ? 'pc' : 'mobile'; //平台标识 1 pc 2 h5  3 mobile  'platform' 1 PC   2 手机
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'REGISTER') {
            $kds['tcusername'] = $datas['playerName'];
        }

        if ($func == 'GETUSERURL') {
            $uname = Meta::initGetField('uname',$tcid,$gid,$uid); //用户名称
            $tctag = Meta::initGetField('tctag',$tcid,$gid,$uid); //商户号
            $gtag = Meta::initGetField('gtag',$tcid,$gid,$uid); //游戏标识
            $tcapiurlpre = Meta::initGetField('tcapiurlpre',$tcid,$gid,$uid); //接口域名
            
            if (isset($datas['token'])) {
                $url = $tcapiurlpre."/launcher?gameCode={$gtag}&token={$datas['token']}&platform=web&language=EN&playerId={$uname}&brandId={$tctag}&mode=1";
                $kds['r_utoken'] = $datas['token'];
                $kds['tcgameurl'] = $url;
            }
        }
    }
}
