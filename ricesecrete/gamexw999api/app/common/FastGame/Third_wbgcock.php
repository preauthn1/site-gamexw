<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\{Http, Meta, Third};

//COCK
//Third_wbgcock
class Third_wbgcock extends Third
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
        ksort($data);
        // $body_str = json_encode($data);
        $string = $this->_ToUrlParams($data);
        $data['signature'] = md5("{$string}{$config['trdcfg']['tc_api_signkey']}");
        return $data;
    }

    function _ToUrlParams($params)
    {
        $string = "";
        foreach ($params as $key => $value) {
            if ($key == 'signature') {
                continue;
            }
            $string .= "{$value}";
        }
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
        $kvs['site_id'] = $config['trdcfg']['tc_tag'];
        $kvs['timestamp'] = time() * 1000;
        if (in_array($kvs['runfunc'], ['GETUSERURL'])) {
            //{"secret_key":"{secret_key}","operator_token":"{operator_token}","player_name":"{player_name}","game_code":"{game_code},"language":"{language}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['gamecode'] = Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); //游戏标识
            $kvs['name'] = $uname;
            $kvs['user_id'] = "game95_" . $kvs['uid'];
            $kvs['wallet'] = 0;//
            $kvs['lang'] = $config['trdcfg']['tc_langmap'];
            $kvs['minimum'] = intval($config['trdcfg']['tc_min_bet']);
            $kvs['maximum'] = intval($config['trdcfg']['tc_max_bet']);
        }
        if (in_array($kvs['runfunc'], ['REGISTER'])) {
            //{"secret_key":"{secret_key}","operator_token":"{operator_token}","player_name":"{player_name}","currency":"{currency}"}
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['name'] = $uname;
            $kvs['user_id'] = "game95_" . $kvs['uid'];
            $kvs['wallet'] = 0;//
            $kvs['lang'] = $config['trdcfg']['tc_langmap'];
            $kvs['minimum'] = intval($config['trdcfg']['tc_min_bet']);
            $kvs['maximum'] = intval($config['trdcfg']['tc_max_bet']);
        }
        if (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {
            $kvs['user_id'] = "game95_" . $kvs['uid'];
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
        }
        if (in_array($kvs['runfunc'], ['GETBALANCE'])) {
            $kvs['user_id'] = "game95_" . $kvs['uid'];
        }
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {

    }
}
