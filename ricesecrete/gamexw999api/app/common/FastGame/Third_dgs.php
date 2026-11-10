<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;
use app\common\payment\PayRequest;


class Third_dgs extends Third
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
        $rand = get_rand_char(10);
        $token = md5($config['trdcfg']['tc_tag'].$config['trdcfg']['tc_api_signkey'].$rand);
        $kvs['token'] = $token;
        $kvs['rand'] = $rand;
        if(in_array($kvs['runfunc'],['REGISTER','GETUSERURL'])) {
            $uname = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
            $kvs['password'] = md5($uname);
        }
        if(in_array($kvs['runfunc'],['DEPOSIT','WITHDRAW'])) {
            $kvs['money'] = Third::transExchangerate($kvs['o_amount'],$kvs['runfunc'] == 'DEPOSIT' ? 1 : 0, 2);
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
            if( $game_id ){
                $url1 = $datas['list'][0].$token.'&language='.$lan.'&tableId='.$game_id.'&backType=enter&backUrl='.$web_url;
                $url2 = $datas['list'][1].$token.'&language='.$lan.'&tableId='.$game_id.'&backType=enter&backUrl='.$web_url;
            }else{
                $url1 = $datas['list'][0].$token.'&language='.$lan.'&backType=enter&backUrl='.$web_url;
                $url2 = $datas['list'][1].$token.'&language='.$lan.'&backType=enter&backUrl='.$web_url;
            }
            $kds['tcgameurl'] = $ohterkvs['platform'] == 1 ? $url1:$url2;
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjMwLCJ0Y19uYW1lIjoiREdcdTc3MWZcdTRlYmEiLCJ0Y19pbnRpbWUiOiIyMDIzLTA0LTEyIDEzOjIzOjM5IiwidGNfY29kZSI6IkRHUyIsInRjX3NldF9jb2RlIjpudWxsLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL2FwaS5kZzIuY28iLCJ0Y19hcGlfcHJpa2V5IjoiQSIsInRjX2FwaV9zaWdua2V5IjoiYTA0M2EzNTAzYzFiNGVlMmFjYWY3YTg2NGQxYzM4NGMiLCJ0Y190YWciOiJERzAwQlIwMTBHIiwidGNfY3VycmVuY3kiOiJUV0QiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoidHciLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA1MTNcLzBiMGE4ODdmYWUwZThhYjI5OTU2N2E5YWYyYWExZjM4LnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYWxsIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjEwMDAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA1MTNcLzI0NDEyZTUxMTkwMzA2OTg2YmM2NTVkZDI2NzNmZWY4LnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MSwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjo2OCwiZ2FfdGNpZCI6MzAsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkRFUE9TSVQiLCJnYV9yb3V0ZSI6IlwvYWNjb3VudFwvdHJhbnNmZXJcL3t0Y3RhZ30iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoie1widG9rZW5cIjpcInt0b2tlbn1cIixcInJhbmRvbVwiOlwie3JhbmR9XCIsXCJkYXRhXCI6XCJ7cmFuZG9tfVwiLFwibWVtYmVyXCI6e1widXNlcm5hbWVcIjpcInt1bmFtZX1cIixcImFtb3VudFwiOlwie21vbmV5fVwifX0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlSWRcIixcInZhbHVlXCI6MH0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcIm1lbWJlci5iYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjo2OSwiZ2FfdGNpZCI6MzAsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJBTEFOQ0UiLCJnYV9yb3V0ZSI6IlwvdXNlclwvZ2V0QmFsYW5jZVwve3RjdGFnfSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJ0b2tlblwiOlwie3Rva2VufVwiLFwicmFuZG9tXCI6XCJ7cmFuZH1cIixcIm1lbWJlclwiOntcInVzZXJuYW1lXCI6XCJ7dW5hbWV9XCJ9fSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVJZFwiLFwidmFsdWVcIjowfSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwibWVtYmVyLmJhbGFuY2VcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOm51bGx9LHsiZ2FfaWQiOjcwLCJnYV90Y2lkIjozMCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVCIsImdhX3JvdXRlIjoiXC9nYW1lXC9nZXRSZXBvcnRcL3t0Y3RhZ30iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoie1widG9rZW5cIjpcInt0b2tlbn1cIixcInJhbmRvbVwiOlwie3JhbmR9XCJ9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlSWRcIixcInZhbHVlXCI6MH0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcImxpc3RcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiJ7XCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MCxcImN1cnRpbWVcIjowLFwidG9rZW5cIjpcImM4MGMwYzUyYmNiOTlkMjZiMmRjNjcyYjlmNjFhZjNlXCIsXCJyYW5kXCI6XCJmZ3Y2M0R5N2NFXCJ9fSIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjcxLCJnYV90Y2lkIjozMCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC91c2VyXC9sb2dpblwve3RjdGFnfSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJ0b2tlblwiOlwie3Rva2VufVwiLFwicmFuZG9tXCI6XCJ7cmFuZH1cIixcImxhbmdcIjpcInt0Y2xhbmdtYXB9XCIsXCJkb21haW5zXCI6XCIxXCIsXCJtZW1iZXJcIjp7XCJ1c2VybmFtZVwiOlwie3VuYW1lfVwiLFwicGFzc3dvcmRcIjpcIntwYXNzd29yZH1cIn19IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZUlkXCIsXCJ2YWx1ZVwiOjB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjo3MiwiZ2FfdGNpZCI6MzAsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IlJFR0lTVEVSIiwiZ2Ffcm91dGUiOiJcL3VzZXJcL3NpZ251cFwve3RjdGFnfSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL2pzb25cIn0iLCJnYV9wcGFyYW0iOiJ7XCJ0b2tlblwiOlwie3Rva2VufVwiLFwicmFuZG9tXCI6XCJ7cmFuZH1cIixcImRhdGFcIjpcInt0Y2FwaWtleX1cIixcIm1lbWJlclwiOntcInVzZXJuYW1lXCI6XCJ7dW5hbWV9XCIsXCJwYXNzd29yZFwiOlwie3Bhc3N3b3JkfVwiLFwiY3VycmVuY3lOYW1lXCI6XCJ7dGNjdXJyZW5jeX1cIixcIndpbkxpbWl0XCI6MH19IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVJZFwiLFwidmFsdWVcIjpcIjBcIn0sXCJ0Y3VzZXJrZXlcIjp7XCJrZXlcIjpcIlwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjczLCJnYV90Y2lkIjozMCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvYWNjb3VudFwvdHJhbnNmZXJcL3t0Y3RhZ30iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCJ9IiwiZ2FfcHBhcmFtIjoie1widG9rZW5cIjpcInt0b2tlbn1cIixcInJhbmRvbVwiOlwie3JhbmR9XCIsXCJkYXRhXCI6XCJ7cmFuZG9tfVwiLFwibWVtYmVyXCI6e1widXNlcm5hbWVcIjpcInt1bmFtZX1cIixcImFtb3VudFwiOlwiLXttb25leX1cIn19IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZUlkXCIsXCJ2YWx1ZVwiOjB9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJtZW1iZXIuYmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6bnVsbH1dfQ==';
    }


}