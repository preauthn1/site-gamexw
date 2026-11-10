<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;
use app\common\payment\PayRequest;


class Third_dg extends Third
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
        if($kvs['runfunc'] == 'REGISTER' || $kvs['runfunc'] == 'GETUSERURL') {
            $rand = get_rand_char(10);
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $token = md5($config['trdcfg']['tc_tag'].$config['trdcfg']['tc_api_signkey'].$rand);
            $password = md5($uname);
            $kvs['token'] = $token;
            $kvs['rand'] = $rand;
            $kvs['password'] = $password;
        }
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        if($func == 'GETUSERURL'){
            $web_url = getAppUrl();
            $token = $datas['token'];
            $lan = $config['trdcfg']['tc_langmap'];
            $game_id = Meta::initGetField('gtag',$tcid,$gid,$uid); //游戏标识
            $tctokentime = Meta::initGetField('tctokentime',$tcid,$gid,$uid); //DG游戏用这个来特殊标记是否进入指定游戏(1不进入)
            if( $tctokentime == 1 ){
                $url1 = $datas['list'][0].$token.'&language='.$lan.'&backType=enter&backUrl='.$web_url;
                $url2 = $datas['list'][1].$token.'&language='.$lan.'&backType=enter&backUrl='.$web_url;
            }else{
                $url1 = $datas['list'][0].$token.'&language='.$lan.'&tableId='.$game_id.'&backType=enter&backUrl='.$web_url;
                $url2 = $datas['list'][1].$token.'&language='.$lan.'&tableId='.$game_id.'&backType=enter&backUrl='.$web_url;
            }
            $kds['tcgameurl'] = $ohterkvs['platform'] == 1 ? $url1:$url2;
        }

        if($func == 'GETBETDETAIL') {
            $urls = $config['trdcfg']['tc_token']."/ag/result/getAgentFix.html?username=".$config['trdcfg']['tc_tag'];
            $agentFix = PayRequest::curlS($urls,[]);
            if($agentFix){
                $agentFix = intval(trim($agentFix,'"'));
                $kds['tcgamedetailurl'] = $config['trdcfg']['tc_token']."/ag/result/result.html?language=".$config['trdcfg']['tc_langmap']."&agentFix=".$agentFix."&id=".trim($ohterkvs['gbsn'],'-');
            }
        }

    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjM5LCJ0Y19uYW1lIjoiREciLCJ0Y19pbnRpbWUiOiIyMDIzLTA0LTEyIDEzOjIzOjM5IiwidGNfY29kZSI6IkRHIiwidGNfc2V0X2NvZGUiOm51bGwsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvYXBpLmRnMi5jbyIsInRjX2FwaV9wcmlrZXkiOiJBIiwidGNfYXBpX3NpZ25rZXkiOiIxNDJhNThhYzZmZTQ0MTBkYjZiM2UxODVmZDM4ZWZlNCIsInRjX3RhZyI6IlRUMCIsInRjX2N1cnJlbmN5IjoiUEhQIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiaHR0cHM6XC9cL2FnMS5kZzY2LmluZm8iLCJ0Y190b2tlbnRpbWUiOjEsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoiUEgiLCJ0Y19zdGF0dXMiOjAsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLmx1Y2t5MDFiZXQuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjMwNDIwXC9mM2FiNzA0NjYwMTMyYjQ0Nzc2NjI4ZjdkYjVjZjg0NS5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImFsbCIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMC4xMCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLmx1Y2t5MDFiZXQuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjMwNDIwXC9mNWQ5MjI5Yzc4OTRhYzg4ZDMxNTJjYzhiN2RiMmRmMS5wbmciLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjAsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6ODUsImdhX3RjaWQiOjM5LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRERVRBSUwiLCJnYV9yb3V0ZSI6Ilwvb2siLCJnYV9ydHlwZSI6ImdldCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6Int9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOm51bGwsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjF9LFwidGNnYW1lZGV0YWlsdXJsXCI6e1wia2V5XCI6XCJkYXRhLm1zZ1wifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6bnVsbCwiZ2FfdGFza3ZhbHVlIjpudWxsLCJnYV91cmxwcmUiOiJodHRwczpcL1wvYXBpLmtnZ2FtZXM3NzcuY29tIn0seyJnYV9pZCI6ODYsImdhX3RjaWQiOjM5LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcL2dhbWVcL2dldFJlcG9ydFwve3RjdGFnfSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJ0b2tlblwiOlwie3Rva2VufVwiLFwicmFuZG9tXCI6XCJ7cmFuZH1cIn0iLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVJZFwiLFwidmFsdWVcIjowfSxcInJfbGlzdFwiOntcImtleVwiOlwibGlzdFwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcIjEwMTAxXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjAsXCJjdXJ0aW1lXCI6MCxcInRva2VuXCI6XCJhNjIxMTNhOTExOWMwYzJjZGRiN2Q5NGVmZDRlYzdiMFwiLFwicmFuZFwiOlwiTno5Mjg0NURmNVwifSxcIjEwMzAxXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjAsXCJjdXJ0aW1lXCI6MH0sXCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MCxcImN1cnRpbWVcIjowLFwidG9rZW5cIjpcIjU0N2ZiYjcwNmMyMTUzOGZkMTdjMzU2M2JhNTk1ZTk5XCIsXCJyYW5kXCI6XCI3Q0ZSblF4cHNuXCJ9fSIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjg3LCJnYV90Y2lkIjozOSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC91c2VyXC9sb2dpblwve3RjdGFnfSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJ0b2tlblwiOlwie3Rva2VufVwiLFwicmFuZG9tXCI6XCJ7cmFuZH1cIixcImxhbmdcIjpcInt0Y2xhbmdtYXB9XCIsXCJkb21haW5zXCI6XCIxXCIsXCJtZW1iZXJcIjp7XCJ1c2VybmFtZVwiOlwie3VuYW1lfVwiLFwicGFzc3dvcmRcIjpcIntwYXNzd29yZH1cIn19IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZUlkXCIsXCJ2YWx1ZVwiOjB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJcIn19IiwiZ2FfcmF0ZSI6MTUwMCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOm51bGx9LHsiZ2FfaWQiOjg4LCJnYV90Y2lkIjozOSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvdXNlclwvc2lnbnVwXC97dGN0YWd9IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiQ29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwvanNvblwifSIsImdhX3BwYXJhbSI6IntcInRva2VuXCI6XCJ7dG9rZW59XCIsXCJyYW5kb21cIjpcIntyYW5kfVwiLFwiZGF0YVwiOlwie3RjYXBpa2V5fVwiLFwibWVtYmVyXCI6e1widXNlcm5hbWVcIjpcInt1bmFtZX1cIixcInBhc3N3b3JkXCI6XCJ7cGFzc3dvcmR9XCIsXCJjdXJyZW5jeU5hbWVcIjpcInt0Y2N1cnJlbmN5fVwiLFwid2luTGltaXRcIjowfX0iLCJnYV90eXBlIjoicmVnIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZUlkXCIsXCJ2YWx1ZVwiOjB9LFwidGN1c2Vya2V5XCI6e1wia2V5XCI6XCJcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifV19';
    }

}