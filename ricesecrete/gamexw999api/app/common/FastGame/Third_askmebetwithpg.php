<?php


namespace app\common\FastGame;


use app\common\model\GameInfosModel;
use app\common\model\GamesModel;
use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\GameConfigs;
use app\model\UserThirds;
use app\common\FastGame\Base\{Http, Meta, Redis, Third, Utils};
use app\model\Games;

class Third_askmebetwithpg extends Third
{

    private $product="PGSOFT2";

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
        return ["authorization"=>"Basic ".base64_encode($config['trdcfg']['tc_tag'].":".$config['trdcfg']['tc_api_prikey'])];
    }

    /**验证签名
     * @param $config
     * @param $data 要求传入HEDAER
     * @return mixed
     * @author twt326
     * @date 2022-08-26 17:32
     */
    function CheckSign($config, $data)
    {
        if (isset($data['return_headers'])){
            $header=$data['return_headers'];
        }elseif(isset($data['header'])){
            $header=$data['header'];
        }elseif(isset($data['headers'])){
            $header=$data['headers'];
        }else{
            $header=$data;
        }
        if (is_array($header) && isset($header['Authorization'])){
            $au=explode(" ",$header['Authorization']);
            if (isset($au[1])){
                return $au[1]==base64_encode($config['trdcfg']['tc_tag'].":".$config['trdcfg']['tc_api_prikey']);
            }else{
                return false;
            }
        }else{
            return false;
        }
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

    function test(){
        return 'askmebetwithpg CLASS';
    }

    function utoken($uid,$v=null){
        if ($v){
            //写
            Redis::hset($this->product."_usertoken",$uid,$v,2);
        }else{
            //读
            return Redis::hget($this->product."_usertoken",$uid,2);
        }
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;
        if($kvs['runfunc'] == 'GETUSERURL') {
            $kvs['Token']=md5($kvs['uid'].$config['trdcfg']['tc_id'].time()); //生成token
            $this->utoken($kvs['uid'],$kvs['Token']);
        }
        // TODO: Implement transOtherKvs() method.
    }


    function get_game_list($apicfgs,$otherkvs){
        try{
            $otherkvs = [];
            $rlt=Http::http($apicfgs,'GETGAMELIST',self::$extClass,$otherkvs);
            Utils::L('test',['gl:',$rlt]);
            if($rlt){
                /*$games=$rlt['data']['games'];
                foreach ($games as $v){
                    Utils::L('test',['gli:',$v]);
                    try {
                        $gid = (new GamesModel())->insertGetId([
                            "g_tcid" => 36,
                            "g_web_tcid" => 36,
                            "g_code" => strtoupper($v['code']),
                            "g_tag" => $v['code'],
                            "label" => $v['name']
                        ]);
                        GameInfosModel::create([
                            "gi_gid" => $gid,
                            "gi_lang" => 'CN',
                            "gi_name" => $v['name'],
                            "gi_headimg" => $v['img']
                        ]);
                        GameConfigs::create(['gc_gid'=>$gid]);
                    }catch (\Exception $ex){
                        Utils::L('test',['ex:',$ex->getMessage()]);
                    }
                }*/
                Utils::writeToStaticFile('askmebetwithpg.games.json', json_encode($rlt['data']));
            }
        }catch (\Exception $e){
//            dump($e->getMessage());
            return $e->getMessage();
        }

    }

    public function importPlatform(){
        return 'eyJjb25maWciOnsidGNfaWQiOjg4LCJ0Y19uYW1lIjoiUEciLCJ0Y19pbnRpbWUiOiIyMDI0LTAxLTAyIDAxOjQzOjQzIiwidGNfY29kZSI6IkFTS01FQkVUV0lUSFBHIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcGkuaGVudG9yeS5pbyIsInRjX2FwaV9wcmlrZXkiOiI3YzNmN2E2MC1hZDI1LTRiNGMtOGI3Zi1lMmQ3OWVhYzZhM2YiLCJ0Y19hcGlfc2lnbmtleSI6bnVsbCwidGNfdGFnIjoiVDEyVEhCcHJvZCIsInRjX2N1cnJlbmN5IjoiVEhCIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6bnVsbCwidGNfbGFuZ21hcCI6IntcImVuXCI6XCJlblwiLFwidGhcIjpcInRoXCIsXCJ6aFwiOlwiemhcIn0iLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjAsImljb24iOiJodHRwczpcL1wvZDExZmJsaHE0MTc1N24uY2xvdWRmcm9udC5uZXRcL3N0b3JhZ2VcL2ltZ1wvMjAyNDAxMDRcL2Q3ZmUzNDc2NTJiZWQwOWE5MDhhMTFiNzY0M2NmZTlhLnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYnlnYW1lIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjEwMDAiLCJ0Y19tYXhfYmV0IjoiMTAwMDAwLjAwMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvZDExZmJsaHE0MTc1N24uY2xvdWRmcm9udC5uZXRcL3N0b3JhZ2VcL2ltZ1wvMjAyNDAxMDRcLzk5ODJmOTg3ODJlMzUxNGViNTgzOTVmYzEyYjFiYzAxLnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOm51bGwsImlzX25ld190YWIiOjAsInRjX2Rpc2FibGVfYWdlbnRfaWRzIjoiMCJ9LCJhcGlzIjpbeyJnYV9pZCI6Mjg5LCJnYV90Y2lkIjo4OCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUR0FNRUxJU1QiLCJnYV9yb3V0ZSI6Ilwvc2VhbWxlc3NcL2dhbWVzIiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiJwcm9kdWN0SWQ9UEdTT0ZUMiIsImdhX2hwYXJhbSI6IntcIkF1dGhvcml6YXRpb25cIjpcInthdXRob3JpemF0aW9ufVwifSIsImdhX3BwYXJhbSI6Int9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjpcIjBcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoyOTAsImdhX3RjaWQiOjg4LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL3NlYW1sZXNzXC9sb2dJbiIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkF1dGhvcml6YXRpb25cIjpcInthdXRob3JpemF0aW9ufVwifSIsImdhX3BwYXJhbSI6IntcInVzZXJuYW1lXCI6XCJ7dW5hbWV9XCIsXCJwcm9kdWN0SWRcIjpcIlBHU09GVDJcIixcImdhbWVDb2RlXCI6XCJ7Z3RhZ31cIixcImlzTW9iaWxlTG9naW5cIjp0cnVlLFwic2Vzc2lvblRva2VuXCI6XCJ7VG9rZW59XCIsXCJjdXJyZW5jeVwiOlwie3RjY3VycmVuY3l9XCIsXCJsYW5ndWFnZVwiOlwie2xhbmd9XCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjpcIjBcIn0sXCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcImRhdGEudXJsXCJ9LFwidGN1c2VybmFtZVwiOntcImtleVwiOlwiZGF0YS51c2VybmFtZVwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }
}
