<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;
use app\common\game\GameRequest;


class Third_atg extends Third
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
        if($kvs['runfunc'] == 'GETUSERURL') {
            //获取游戏key
            $gamekey = '';
            try {
                $username = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
                $urls = $config['trdcfg']['tc_api_urlpre']."/game-providers/".$config['trdcfg']['tc_tag']."/games/".$config['g_tag']."/key";
                $header[] = 'X-Token: '.Meta::initGetField('tctoken', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
                $content = GameRequest::curl_get($urls,['username'=>$username],$header);
                Utils::L("{$config['trdcfg']['tc_id']}/http",['获取游戏key',$urls, $header,$content]);
                $res = json_decode($content,true);
                if(isset($res['status']) && $res['status'] == 'success'){
                    $gamekey = $res['data']['key'];
                }else{
                    Utils::L("{$config['trdcfg']['tc_id']}/http",['获取游戏key失败', $res]);
                }
            } catch (\Exception $e) {
                Utils::L("{$config['trdcfg']['tc_id']}/http",['获取游戏key错误', $e->getMessage(), $e->getTraceAsString()]);
            }
            $kvs['gamekey'] = $gamekey;
            $platform = $kvs['platform'] ?? 0;
            $kvs['platForm'] = $platform == 1 ? 'desktop' : 'mobile'; //平台标识
        }else if( in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW']) ) {
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            $kvs['o_amount'] = Third::transExchangerate($kvs['o_amount'], $tomeorthird, 2);
        }
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {


    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjMxLCJ0Y19uYW1lIjoiQVRHXHU5NmZiXHU1YjUwIiwidGNfaW50aW1lIjoiMjAyNC0wNS0wNiAyMjoyOTo0OSIsInRjX2NvZGUiOiJBVEciLCJ0Y19zZXRfY29kZSI6IiIsInRjX2FwaV91cmxwcmUiOiJodHRwczpcL1wvYXBpLmdvZGVlYnhwLmNvbSIsInRjX2FwaV9wcmlrZXkiOiJYV2JldF9nYW1lX3Byb2QiLCJ0Y19hcGlfc2lnbmtleSI6IjJhZGY2MTNjNTM3NzQ3YWNiOTYzNDE5NWE0MTFmNjU0IiwidGNfdGFnIjoiNCIsInRjX2N1cnJlbmN5IjoiVFdEIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiMDQ4MTI5OTk4Y2ZhNGJkZGJkODhiOGE0NzNjMzUwZTQiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoiemgtdHciLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjk5OTk5LCJpY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjA3XC80NTgzN2JlZGJhMTA3MWQ3MTZiZDM4MTM1NzE1OTE5NS5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImFsbCIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMC4wMDAwIiwidGNfbWF4X2JldCI6IjEwMDAwMC4wMDAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy54d2JldHRpbmcuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjQwNjA3XC80ZWQ1MTYzMGQ2ZjdmNjM2MGRlMGZhYTY5NTgzM2JmMC5wbmciLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjEsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6NzQsImdhX3RjaWQiOjMxLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL2dhbWUtcHJvdmlkZXJzXC97dGN0YWd9XC9iYWxhbmNlIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wiWC1Ub2tlblwiOlwie3RjdG9rZW59XCJ9IiwiZ2FfcHBhcmFtIjoie1widXNlcm5hbWVcIjpcInt1bmFtZX1cIixcImJhbGFuY2VcIjpcIntvX2Ftb3VudH1cIixcImFjdGlvblwiOlwiSU5cIixcInRyYW5zZmVySWRcIjpcIntyYW5kb219XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwic3RhdHVzXCIsXCJ2YWx1ZVwiOlwic3VjY2Vzc1wifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiZGF0YS5iYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6NzUsImdhX3RjaWQiOjMxLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRBVVRIIiwiZ2Ffcm91dGUiOiJcL3Rva2VuIiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJYLU9wZXJhdG9yXCI6XCJ7dGNhcGlrZXl9XCIsXCJYLUtleVwiOlwie3RjYXBpc2lnbmtleX1cIn0iLCJnYV9wcGFyYW0iOiJ7fSIsImdhX3R5cGUiOiJnZXRhdXRodG9rZW4iLCJnYV9ybHRmaWVsZCI6bnVsbCwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInN0YXR1c1wiLFwidmFsdWVcIjpcInN1Y2Nlc3NcIn0sXCJ0Y3Rva2VuXCI6e1wia2V5XCI6XCJkYXRhLnRva2VuXCJ9fSIsImdhX3JhdGUiOjQ4MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOm51bGwsImdhX3Rhc2t2YWx1ZSI6bnVsbCwiZ2FfdXJscHJlIjpudWxsfSx7ImdhX2lkIjo3NiwiZ2FfdGNpZCI6MzEsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJBTEFOQ0UiLCJnYV9yb3V0ZSI6IlwvZ2FtZS1wcm92aWRlcnNcL3t0Y3RhZ31cL2JhbGFuY2UiLCJnYV9ydHlwZSI6ImdldCIsImdhX2dwYXJhbSI6InVzZXJuYW1lPXt1bmFtZX0iLCJnYV9ocGFyYW0iOiJ7XCJYLVRva2VuXCI6XCJ7dGN0b2tlbn1cIn0iLCJnYV9wcGFyYW0iOiJ7fSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInN0YXR1c1wiLFwidmFsdWVcIjpcInN1Y2Nlc3NcIn0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImRhdGEuYmFsYW5jZVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6bnVsbH0seyJnYV9pZCI6NzcsImdhX3RjaWQiOjMxLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUIiwiZ2Ffcm91dGUiOiJcL3RyYW5zYWN0aW9uIiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoiIiwiZ2FfcHBhcmFtIjoie1wiT3BlcmF0b3JcIjpcInt0Y2FwaWtleX1cIixcIktleVwiOlwie3RjYXBpc2lnbmtleX1cIixcIlNEYXRlXCI6XCJ7U0RhdGV9XCIsXCJFRGF0ZVwiOlwie0VEYXRlfVwifSIsImdhX3R5cGUiOiJ0YXNrIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiaHR0cGNvZGVcIixcInZhbHVlXCI6MjAwfSxcInJfbGlzdFwiOntcImtleVwiOlwiXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjpudWxsLCJnYV90YXNrdmFsdWUiOiJ7XCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTcyNTU2MDg4MixcImN1cnRpbWVcIjowLFwiU0RhdGVcIjpcIjIwMjQtMDktMDYgMDI6MjI6MDJcIixcIkVEYXRlXCI6XCIyMDI0LTA5LTA2IDAyOjI3OjAyXCIsXCJlbmRcIjoxNzI1NTYwODgyfX0iLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjo3OCwiZ2FfdGNpZCI6MzEsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6IlwvZ2FtZS1wcm92aWRlcnNcL3t0Y3RhZ31cL2xvYmJ5IiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiJ1c2VybmFtZT17dW5hbWV9JmhlYWRsZXNzPTAmZGFyaz0xIiwiZ2FfaHBhcmFtIjoie1wiWC1Ub2tlblwiOlwie3RjdG9rZW59XCJ9IiwiZ2FfcHBhcmFtIjoie30iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJzdGF0dXNcIixcInZhbHVlXCI6XCJzdWNjZXNzXCJ9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJkYXRhLnVybFwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjc5LCJnYV90Y2lkIjozMSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvcmVnaXN0ZXIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJYLVRva2VuXCI6XCJ7dGN0b2tlbn1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJ1c2VybmFtZVwiOlwie3VuYW1lfVwiLFwibGFuZ3VhZ2VcIjpcInt0Y2xhbmdtYXB9XCJ9IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImh0dHBjb2RlXCIsXCJ2YWx1ZVwiOjIwMH0sXCJ0Y3VzZXJrZXlcIjp7XCJrZXlcIjpcImRvbm90aGF2ZWtleVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjgwLCJnYV90Y2lkIjozMSwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvZ2FtZS1wcm92aWRlcnNcL3t0Y3RhZ31cL2JhbGFuY2UiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJYLVRva2VuXCI6XCJ7dGN0b2tlbn1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJ1c2VybmFtZVwiOlwie3VuYW1lfVwiLFwiYmFsYW5jZVwiOlwie29fYW1vdW50fVwiLFwiYWN0aW9uXCI6XCJPVVRcIixcInRyYW5zZmVySWRcIjpcIntyYW5kb219XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwic3RhdHVzXCIsXCJ2YWx1ZVwiOlwic3VjY2Vzc1wifSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiZGF0YS5iYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjpudWxsfV19';
    }

}