<?php


namespace app\common\FastGame;


use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};
use app\model\Games;

class Third_ygg extends Third
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
        return [];
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
        $token  = $data['brand_uid'].$config['trdcfg']['tc_tag'];

        $sign = strtoupper(md5($config['trdcfg']['tc_tag'].$token.$config['trdcfg']['tc_api_signkey']));

        if($sign  == $data['sign']){
            return true;
        }
        return false;
    }

    /**验证token
     * @param $config
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-08-26 17:32
     */
    function CheckToken($config, $data)
    {

        $token = md5($data['brand_uid'].$config['trdcfg']['tc_tag']);
        if($token  == $data['token']){
            return true;
        }
        return false;
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
        $uid = isset($kvs['uid']) ? $kvs['uid']:0;
        $g_tcid = isset($config['g_tcid']) ? $config['g_tcid']:$config['trdcfg']['tc_id'];
        $gid = $config['g_id'] > 0 ?  $config['g_id'] : Game::getGamesInfoBy($g_tcid, 'aecricket', 'code', 'g_id');

        $signKey = Meta::initGetField('tcapisignkey',$g_tcid,$gid,$uid);
        $brandId = Meta::initGetField('tctag',$g_tcid,$gid,$uid);
        $username = Meta::initGetField('uname',$g_tcid,$gid,$uid);
        $kvs['sign'] = strtoupper(md5($brandId.$username.$signKey));

        if($kvs['runfunc'] == 'GETUSERURL') {
            $kvs['token'] = md5($username.$brandId);
            $platform = $kvs['platform'] ?? 0;
            $kvs['channel'] = $platform == 1 ? 'pc' : 'mobile'; //平台标识 1 pc 2 h5  3 mobile  'platform' 1 PC   2 手机
            $kvs['return_url'] = $kvs['back_url']= getAppUrl();
        }
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        if($func == 'GETUSERURL'){
//            $key = Meta::initGetField('tcapikey', $config['g_tcid'], $config['g_id'], $uid);
//            $iv = Meta::initGetField('tcapisignkey', $config['g_tcid'], $config['g_id'], $uid);
//            if( $kds ){
//                $kds = $this->aes256CbcDecrypt($key, $kds, $iv);
//            }else{
//                $kds = [];
//            }
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjMyLCJ0Y19uYW1lIjoiWUdHIiwidGNfaW50aW1lIjoiMjAyMy0wOS0xNiAyMToyMTo1NSIsInRjX2NvZGUiOiJZR0ciLCJ0Y19zZXRfY29kZSI6bnVsbCwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9nYW1pbmcuc3RhZ2VkYy5uZXQiLCJ0Y19hcGlfcHJpa2V5IjoiIiwidGNfYXBpX3NpZ25rZXkiOiJEMUZDNUI5NjgwNUM0RjMwQjA5RDNBRjg0MURBNjJDNyIsInRjX3RhZyI6IlMwOTUwMjUiLCJ0Y19jdXJyZW5jeSI6IkJSTCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjAuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOm51bGwsInRjX2xhbmdtYXAiOiJQVCIsInRjX3N0YXR1cyI6MSwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC8wc2ltZy5zMy5hcC1zb3V0aGVhc3QtMS5hbWF6b25hd3MuY29tXC9zdG9yYWdlXC9pbWdcLzIwMjMwMTIyXC8xZjEzMjg0NmNmYjc0NDkwYzdlNzhiNzVjZGZlZDgwYy5qcGciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImJ5Z2FtZSIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMS4wMCIsInRjX21heF9iZXQiOiIxMDAwMDAwMC4wMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6IiIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwiaXNfbmV3X3RhYiI6MCwidGNfZGlzYWJsZV9hZ2VudF9pZHMiOiIwIn0sImFwaXMiOlt7ImdhX2lkIjo4MiwiZ2FfdGNpZCI6MzIsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVFVTRVJVUkwiLCJnYV9yb3V0ZSI6IlwvZGNzXC9sb2dpbkdhbWUiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJicmFuZF9pZFwiOlwie3RjdGFnfVwiLFwiYnJhbmRfdWlkXCI6XCJ7dW5hbWV9XCIsXCJjdXJyZW5jeVwiOlwie3RjY3VycmVuY3l9XCIsXCJsYW5ndWFnZVwiOlwie3RjbGFuZ21hcH1cIixcImNvdW50cnlfY29kZVwiOlwiQlJcIixcImZ1bGxfc2NyZWVuXCI6XCJ7ZnVsbF9zY3JlZW59XCIsXCJzaWduXCI6XCJ7c2lnbn1cIixcInRva2VuXCI6XCJ7dG9rZW59XCIsXCJnYW1lX2lkXCI6XCJ7Z2FtZV9pZH1cIixcImNoYW5uZWxcIjpcIntjaGFubmVsfVwiLFwicmV0dXJuX3J1bFwiOlwiaHR0cHM6XC9cL3Rlc3QuYm9zYTY2LmNvbVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6MTAwMH0sXCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcImRhdGEuZ2FtZV91cmxcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifV19';
    }

}
