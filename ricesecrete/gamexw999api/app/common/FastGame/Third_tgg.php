<?php


namespace app\common\FastGame;


use app\common\FastGame\Base\Http;
use app\common\FastGame\Base\Meta;
use app\common\FastGame\Base\Third;
use app\common\FastGame\Base\Utils;

class Third_tgg extends Third
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
        if(isset($data['settings']['payout']) && $data['settings']['payout'] != 0){
            $rtp_file = base_path("common") . "FastGame/Rtp_tgg.php";
            if(file_exists($rtp_file)){
                $rtp = require($rtp_file);
                $game_rtp = $rtp[$config['g_code']]??0;
                $game_rtps = explode(",",$game_rtp);
                if(!in_array($data['settings']['payout'], $game_rtps)){
                    if(isset($game_rtps[0]) && !empty($game_rtps[0])){
                        $data['settings']['payout'] = $game_rtps[0];
                    } else {
                        unset($data['settings']['payout']);
                    }
                }
            } else {
                unset($data['settings']['payout']);
            }
        }
        $md5 = array();
        $data['token'] = $config['trdcfg']["tc_api_signkey"].":".$data['settings']['user_id'];
        foreach ($data as $required_arg) {
            $arg = $required_arg;
            if(is_array($arg)){
                if(count($arg)) {
                    $recursive_arg = '';
                    array_walk_recursive($arg, function($item) use (& $recursive_arg) {
                    if(!is_array($item)) { $recursive_arg .= ($item . ':');} });
                    $md5[] = substr($recursive_arg, 0, strlen($recursive_arg)-1); // get rid of last colon-sign
                } else {
                    $md5[] = '';
                }
            } else {
                $md5[] = $arg;
            }
        }
        
        $md5[] = $config['trdcfg']["tc_api_prikey"];
        $md5_str = implode('*', $md5);
        $md5 = md5($md5_str);
        $data['signature'] = $md5;
        return $md5;
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
        $md5 = array();
        $md5[] = $config['trdcfg']["tc_tag"];
        $md5[] = 2;
        $signature = $data['signature'];
        unset($data['third']);
        unset($data['signature']);
        foreach ($data as $required_arg) {
            $arg = $required_arg;
            if(is_array($arg)){
                if(count($arg)) {
                    $recursive_arg = '';
                    array_walk_recursive($arg, function($item) use (& $recursive_arg) {
                    if(!is_array($item)) { $recursive_arg .= ($item . ':');} });
                    $md5[] = substr($recursive_arg, 0, strlen($recursive_arg)-1); // get rid of last colon-sign
                } else {
                    $md5[] = '';
                }
            } else {
                $md5[] = $arg;
            }
        }
        $md5[] = $config['trdcfg']["tc_api_prikey"];
        $md5_str = implode('*', $md5);
        $md5 = md5($md5_str);
        if($signature != $md5){
            return false;
        }
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
        Utils::L('test',['TokenTimestampTranser',$in,time()+$in-600]);
        return time()+$in-600;
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

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjYsInRjX25hbWUiOiJUR0cgR2FtZXMiLCJ0Y19pbnRpbWUiOiIyMDIyLTEwLTE1IDIzOjU1OjE4IiwidGNfY29kZSI6IlRHRyIsInRjX2FwaV91cmxwcmUiOiJodHRwOlwvXC9hcGkuZmxleGNvbnRlbnRwcm92aWRlci5jb20iLCJ0Y19hcGlfcHJpa2V5IjoiZWI5ODIxMDA1NmE0YTBiYTZiMTkwMGQ4ZjQyZmM4NTAiLCJ0Y19hcGlfc2lnbmtleSI6IkN5YmVycmFjY29vbiIsInRjX3RhZyI6IjE4NjUiLCJ0Y19jdXJyZW5jeSI6IlZORCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjAuMDAxMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MTAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjpudWxsLCJ0Y19sYW5nbWFwIjoiQ04iLCJ0Y19zdGF0dXMiOjAsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvaW1nLmFmYmV0LnRvcFwvc3RvcmFnZVwvaW1nXC8yMDIzMDEwNlwvMGYyMmVkYzZiYWFiOGQwOGUzMDBhYzcyOTE1NmJmMjcucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJieWdhbWUiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjIuMDAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwMDAuMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiIiLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjAsInRjX3NldF9jb2RlIjpudWxsLCJpc19uZXdfdGFiIjowfSwiYXBpcyI6W3siZ2FfaWQiOjE3LCJnYV90Y2lkIjo2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL0dhbWVcL2dldFVSTCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcInByb2plY3RcIjpcInt0Y3RhZ31cIixcInZlcnNpb25cIjoxLFwidG9rZW5cIjpcIlwiLFwiZ2FtZVwiOlwie2d0YWd9XCIsXCJzZXR0aW5nc1wiOntcInVzZXJfaWRcIjpcInt1aWR9XCIsXCJsYW5ndWFnZVwiOlwiRU5cIixcIm1pbl9iZXRcIjpcInt0Y21pbmJldH1cIixcIm1pbl9iZXRcIjpcInt0Y21pbmJldH1cIixcIm1heF9iZXRcIjpcInt0Y21heGJldH1cIixcInBheW91dFwiOlwie2dydHB9XCJ9LFwiZGVub21pbmF0aW9uXCI6MSxcImN1cnJlbmN5XCI6XCJ7dGNjdXJyZW5jeX1cIixcInJldHVybl91cmxfaW5mb1wiOjEsXCJjYWxsYmFja192ZXJzaW9uXCI6Mn0iLCJnYV90eXBlIjpudWxsLCJnYV9ybHRmaWVsZCI6bnVsbCwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInN0YXR1c1wiLFwidmFsdWVcIjowfSxcInRjZ2FtZXVybFwiOntcImtleVwiOlwiZGF0YS5saW5rXCJ9fSIsImdhX3JhdGUiOjM2MDAsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjpudWxsLCJnYV90YXNrdmFsdWUiOm51bGwsImdhX3VybHByZSI6bnVsbH1dfQ==';
    }
}
