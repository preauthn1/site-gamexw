<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Game;
use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Utils;
use app\common\lib\Util;
use app\common\service\MemberService;
use DateTime;

class Third_fc1 extends Third
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
        $Params = $data['param'];
        unset($data['param']);
        $Params_aes = openssl_encrypt(json_encode($Params,JSON_UNESCAPED_SLASHES), 'AES-128-ECB', $config['trdcfg']["tc_api_prikey"], OPENSSL_RAW_DATA);
        $Sign = md5(json_encode($Params,JSON_UNESCAPED_SLASHES));
        $Params = base64_encode($Params_aes);
        return compact('Params', 'Sign');
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
        $sign = md5(json_encode($data,JSON_UNESCAPED_SLASHES));
        return $sign;
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
        $config = $this->apicfgs;
        if($kvs['runfunc'] == 'GETBETLIST') {
            date_default_timezone_set(gettimezone("-4")); //转三方时区时间
            $max_time = $this->getBetListMaxTime();
            // $kvs['next'] = $kvs['start'] + $max_time;
            $kvs['StartDate'] = date("Y-m-d H:i:s", time() - $max_time);
            $kvs['EndDate'] = date("Y-m-d H:i:s", time() - 1);
            // dd($kvs);
        }elseif( $kvs['runfunc'] == 'DEPOSIT' ){
            if(isset($kvs['o_amount'])){
                $kvs['o_amount'] = round($kvs['o_amount'], 4);
            }
        }elseif( $kvs['runfunc'] == 'WITHDRAW' ){
            if(isset($kvs['o_amount'])){
                $kvs['o_amount'] = '-'.round($kvs['o_amount'], 4);
            }
        }
    }

    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if ($func == 'GETUSERURL') {
            $kds['tcgameurl'] = $datas['Url'];
        }
        if ($func == 'GETBETLIST') {
            $kds['r_list'] = $kds['r_list'];
        }
        if ($func == 'WITHDRAW') {
            $kds['tcbalance'] = abs(round($kds['tcbalance'], 4));
        }
    }


    function procBetData($list, $cfgs) {
        if (empty($list)) {
            return [];
        }
        Third::initExtClass($cfgs);
        // FC API数据时间的时区为UTC-4
        $tz = gettimezone("-4");
        $result = [];
        foreach ($list as $data) {
            $gb_winprice = Third::transExchangerate($data['prize'],0,2);
            $gb_otherwin = Third::transExchangerate($data['winlose'],0,2);
            $gb_price = Third::transExchangerate($data['bet'],0,2);
            $gid = Game::getGamesInfoBy($cfgs['trdcfg']['tc_id'], $data['gameID'], 'tag', 'g_id');
            $gb_overtime = Util::getTimeZoneConversion(\app\common\Utils::getQueryDateTime($data['bdate']), $tz);
            $rd = [
                'gb_gid' => $gid,
                'gb_uid' => MemberService::getUidByReCode($data['account']),
                'gb_winlose' => $gb_winprice > 0 ? 3 : 2,
                'gb_bettime' => $gb_overtime,
                'gb_overtime' => $gb_overtime,
                'gb_price' => $gb_price,
                'gb_sn'=> $data['recordID'],
                'gb_winprice'=>$gb_winprice,
                'gb_otherwin'=>$gb_otherwin,
                'gb_validwater'=>$gb_winprice > $gb_price ? $gb_price : $gb_price - $gb_winprice,
            ];

            $result[] = $rd;
        }

        return $result;
    }
}
