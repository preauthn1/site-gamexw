<?php


namespace app\common\FastGame;


use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};
use app\model\Games;

class Third_png extends Third
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

    function encode($trace_id,$json,$key){
        return md5($trace_id.$json.$key);
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


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $otherkvs)
    {
        if($func == 'GETUSERURL'){

            $config = $this->apicfgs;
            $url = $config['trdcfg']['tc_token'];
            $pid = $config['trdcfg']['tc_tag'];
            $gameid = $config['g_tag'];
            $u = Meta::initGetField('uname',$tcid,$gid,$uid);

            if($otherkvs['platform'] == 1){
                $channel = 'desktop';
            }else{
                $channel = 'mobile';
                $gameid = '100'.$gameid;
            }

            $lang = $config['trdcfg']['tc_langmap'];
            $web_url = getAppUrl();

            $kds['tcgameurl'] = $url.'?pid='.$pid.'&gameid='.$gameid.'&channel='.$channel.'&lang='.$lang.'&practice=0&ticket='.$u.'&origin='.$web_url;
        }elseif($func == 'GETTRYURL'){

            $config = $this->apicfgs;
            $url = $config['trdcfg']['tc_token'];
            $pid = $config['trdcfg']['tc_tag'];
            $gameid = $config['g_tag'];

            $lang = $config['trdcfg']['tc_langmap'];

            $web_url = getAppUrl();

            if($otherkvs['platform'] == 1){
                $channel = 'desktop';
            }else{
                $channel = 'mobile';
                $gameid = '100'.$gameid;
            }

            //pc端试玩链接
            $kds['tctrygameurl'] = $url.'?pid='.$pid.'&gameid='.$gameid.'&channel='.$channel.'&lang='.$lang.'&practice=1&origin='.$web_url;
        }

    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjk4LCJ0Y19uYW1lIjoiUE5HXHU4MDAxXHU4NjRlXHU2NzNhIiwidGNfaW50aW1lIjoiMjAyNC0wNS0yMSAxOTozMzoyOSIsInRjX2NvZGUiOiJQTkciLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL3Rlc3R0NjE5YXBpLnJzZGVtby5jbyIsInRjX2FwaV9wcmlrZXkiOiJzdGFnZXN0YWdlc3RhZ2VzdGFnZSIsInRjX2FwaV9zaWdua2V5IjoieXRyYXBpX25TUHNncFpzZ0p3REtlRU54Q1hyaUpTUXMiLCJ0Y190YWciOiIxOTYzIiwidGNfY3VycmVuY3kiOiJVU0QiLCJ0Y19leGNoYW5nZXJhdGUiOiIxLjAwMDAwMDAwIiwidGNfdG9rZW4iOiJodHRwczpcL1wvYXNpc3RhZ2UucGxheW5nb25ldHdvcmsuY29tXC9jYXNpbm9cL0NvbnRhaW5lckxhdW5jaGVyIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6IntcInRoaXJkc3RcIjowLFwic3RhdHN0XCI6MCxcImpzc3RcIjowLFwiZnlzdFwiOjB9IiwidGNfbGFuZ21hcCI6ImphX0pQIiwidGNfc3RhdHVzIjoxLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2ltZy5wYXp1cnUtbWJnYW1pbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQxMDIzXC8wNjViYzQyODUxZmE4ZTg3MDBkZjk1NWI0MjIwOTEyNS5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImFsbCIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMTAuMDAwMCIsInRjX21heF9iZXQiOiIxMDAwMDAuMDAwMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6Imh0dHBzOlwvXC9pbWcucGF6dXJ1LW1iZ2FtaW5nLmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MTAyM1wvMWQ2YTdlZGY3MzBmMTM0ZWZmZTA3MjJjNTEzNWQxMWEucG5nIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJ0Y19zZXRfY29kZSI6IiIsImlzX25ld190YWIiOjAsInRjX2Rpc2FibGVfYWdlbnRfaWRzIjoiMCIsInRjX3dpbm5pbmdfZmVlIjpudWxsfSwiYXBpcyI6W3siZ2FfaWQiOjMwNCwiZ2FfdGNpZCI6OTgsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6Ilwvb2siLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiIiLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjF9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifV19';
    }


}
