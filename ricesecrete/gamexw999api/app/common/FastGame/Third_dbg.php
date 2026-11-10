<?php


namespace app\common\FastGame;


use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};
use app\model\Games;

class Third_dbg extends Third
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
        //echo json_encode($config);die;
        $secret = $config['trdcfg']["tc_api_signkey"];

        $currency = $config['trdcfg']["tc_currency"];

        if($kvs['runfunc'] == 'REGISTER'){

            $uname = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);

            $json = '{"account":"'.$uname.'","currency":'.$currency.'}';
            $token = MD5($secret.$json);

        }else if($kvs['runfunc'] == 'GETUSERURL'){

            $uname = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);

            $lang = $config['trdcfg']["tc_langmap"];;
            $back = getAppUrl();
            $s['account'] = $uname;
            $s['lang'] = $lang;
            $s['back'] = $back;
            //$json = '{"account":"'.$uname.'","lang":"'.$lang.'","back":"'.$back.'"}';
            $json = json_encode($s);
            $token = MD5($secret.$json);
            $kvs['back'] = $back;

        }else if($kvs['runfunc'] == 'GETBETLIST1'){

            $s['account'] = '';
            $s['page'] = $kvs['page'];
            $s['from'] = $kvs['start_time'];
            $s['to'] = $kvs['end_time'];
            $json = json_encode($s);
            $token = MD5($secret.$json);

        }else if($kvs['runfunc'] == 'GETBETDETAIL'){
            $spinId = $kvs['gbsn'];
            $s['spinId'] = (int)$spinId;
            $json = json_encode($s);

            $token = MD5($secret.$json);
            $kvs['spinId'] = $spinId;

        }

        $kvs['token'] = $token;
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if( $func == 'REGISTER' ){
            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
            if( !$guser ){
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }
        }
    }

    private function generateSign($data, $secret = ''){
        unset($data["token"]);
        ksort($data);
        $str = json_encode($data);
        return strtolower(md5($secret.$str));
    }

    //导入文件
    public function importPlatform() {
        return '';
    }

}
