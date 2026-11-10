<?php


namespace app\common\FastGame;

use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};


class Third_whitecliff extends Third
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
        return true;
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
        if(in_array($kvs['runfunc'],['GETUSERURL'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $balance = Meta::initGetField('ubalance', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['name'] = $uname;
            $kvs['balance'] = $balance;
            $kvs['currency'] = $config['trdcfg']['tc_currency'];
            $platform = $kvs['platform'] ?? 0;
            $kvs['is_mobile'] = $platform == 1 ? 0 : 1; //平台标识 1 pc 2 h5  3 mobile  'platform' 1 PC   2 手机
        }
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        if ($func == 'GETUSERURL') {
            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
            if (!$guser){
                //TODO 错误处理
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_trdid' => $datas['user_id'],
                    'ut_trdname' => $datas['username'],
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }
        }
    }

}
