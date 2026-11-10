<?php


namespace app\common\FastGame;


use app\model\UserThirds;
use app\common\FastGame\Base\{Http, Meta, Third, Utils};
use app\model\Games;

class Third_og extends Third
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
        $string = Http::ToUrlParams($data, false, []);
        $data['signature'] = md5("{$string}{$config['trdcfg']['tc_api_prikey']}");
        return $data;
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
        $param = $data;
        $signature = $param['signature'];
        if($param['action'] == 'bulk_debit'){
            $param['records'] = json_encode($param['records']);
        }
        foreach($param as $k =>&$v){
            if(is_array($v)){
                $param[$k] = json_encode($v);
            }
        }
        unset($param['signature'], $param['third'], $param['action_method'], $param['api'], $param['operator'], $param['s'], $param['bulk'], $param['action']);
        ksort($param);
        $string = Http::ToUrlParams($param, false, []);
        $sign = md5("{$string}{$config['trdcfg']['tc_api_prikey']}");
        if($sign == $signature){
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

    function test(){
        return 'kyrianstg CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $kvs['ron_token'] = Utils::random(16);
        $kvs['timestamp'] = time() * 1000;
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'GETUSERURL'){
            $config = $this->apicfgs;
            $name = Meta::initGetField('uname', $config['g_tcid'], $config['g_id'], $uid);
            //如果没写入成功,以后用这种方式，
            //$kds['tcusername'] = $name;
            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
            if (!$guser){
                //TODO 错误处理
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_trdid' => $name,
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }else{
                $guser->ut_trdid =  $name;
                //TODO 错误处理
                $guser->save();
            }
        }
    }

    function get_game_list($apicfgs,$otherkvs){
        /** 外部调用方法
         * $apicfgs=Games::getConfigs(tcid,gid);
         * Third::run($apicfgs,'本函数名',$otherkvs); //注单获取的开始时间结束时间等在在otherkvs中指定
         */
        try{
            $otherkvs = [];
            $rlt=Http::http($apicfgs,'GETGAMELIST',self::$extClass,$otherkvs);
            if($rlt && $rlt['rs_code'] == 'S-100'){
                $arr = [];
                foreach ($rlt['records'] as $k=>$v){
                    $arr[$v['game_id']] = $v['game_name'];
                }
                Utils::writeToStaticFile('og_game.info.json', json_encode($arr));
            }
        }catch (\Exception $e){
//            dump($e->getMessage());
            return $e->getMessage();
        }

    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjE2LCJ0Y19uYW1lIjoiT0cgR2FtZXMiLCJ0Y19pbnRpbWUiOiIyMDIyLTEyLTI2IDE1OjQ3OjUzIiwidGNfY29kZSI6Ik9HIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9hcG9sbG8uYWxsNTU1NS5jb20iLCJ0Y19hcGlfcHJpa2V5IjoiN3dndHg0eHJMNjdhdUE0UiIsInRjX2FwaV9zaWdua2V5IjoiSkVlRWZVeGVXWVhFY0U2YyIsInRjX3RhZyI6Im1vZzczMXZuZCIsInRjX2N1cnJlbmN5IjoiVk5EIiwidGNfZXhjaGFuZ2VyYXRlIjoiMC4wMDEwMDAwMCIsInRjX3Rva2VuIjoiIiwidGNfdG9rZW50aW1lIjowLCJ0Y19wcm94eWNvbmZpZyI6IiIsInRjX3Rhc2tjb25maWciOm51bGwsInRjX3Rhc2t2YWx1ZSI6bnVsbCwidGNfbGFuZ21hcCI6ImVuIiwidGNfc3RhdHVzIjowLCJzb3J0IjowLCJpY29uIjoiaHR0cHM6XC9cL2ltZy5hZmJldC50b3BcL3N0b3JhZ2VcL2ltZ1wvMjAyMzAxMDZcLzBmMjJlZGM2YmFhYjhkMDhlMzAwYWM3MjkxNTZiZjI3LnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYnlnYW1lIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjEwIiwidGNfbWF4X2JldCI6IjEwMDAwMC4wMCIsImNvdW50cnkiOm51bGwsInRjX3NwZWNpYWxfc3RhdHVzIjowLCJzaW1wbGVfaWNvbiI6IiIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MCwidGNfc2V0X2NvZGUiOm51bGwsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6NDMsImdhX3RjaWQiOjE2LCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRHQU1FTElTVCIsImdhX3JvdXRlIjoiXC9hcGlcL3YxXC9wbGF0Zm9ybVwvZ2FtZVwvZ2FtZS1saXN0IiwiZ2FfcnR5cGUiOiJnZXQiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJrZXlcIjpcInt0Y2FwaXNpZ25rZXl9XCIsXCJvcGVyYXRvci1uYW1lXCI6XCJ7dGN0YWd9XCJ9IiwiZ2FfcHBhcmFtIjoie30iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJyc19jb2RlXCIsXCJ2YWx1ZVwiOlwiUy0xMDBcIn0sXCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcImdhbWVfbGlua1wifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjMzLCJnYV90Y2lkIjoxNiwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9hcGlcL3YxXC9wbGF0Zm9ybVwvZ2FtZXNcL2xhdW5jaCIsImdhX3J0eXBlIjoiZ2V0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wia2V5XCI6XCJ7dGNhcGlzaWdua2V5fVwiLFwib3BlcmF0b3ItbmFtZVwiOlwie3RjdGFnfVwifSIsImdhX3BwYXJhbSI6IntcInBsYXllcl9pZFwiOlwie3VuYW1lfVwiLFwidGltZXN0YW1wXCI6XCJ7dGltZXN0YW1wfVwiLFwibmlja25hbWVcIjpcInJhY2Nvb25cIixcInRva2VuXCI6XCJ7cm9uX3Rva2VufVwiLFwibGFuZ1wiOlwie3RjbGFuZ21hcH1cIixcImdhbWVfaWRcIjoxLFwiYmV0bGltaXRcIjoxfSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInJzX2NvZGVcIixcInZhbHVlXCI6XCJTLTEwMFwifSxcInRjZ2FtZXVybFwiOntcImtleVwiOlwiZ2FtZV9saW5rXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjoxLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn1dfQ==';
    }
}
