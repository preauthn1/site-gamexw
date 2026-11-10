<?php


namespace app\common\FastGame;


use app\common\payment\PayRequest;
use app\model\GameBetsSon;
use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};
use app\model\Games;

class Third_pgfalse extends Third
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
        $secret = $config['trdcfg']["tc_api_signkey"];
        $data['ts'] = time();
        $sign = $this->generateSign($data, $secret);
        $data['sign'] = $sign;
        return compact('sign');
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
        $uid = isset($kvs['uid']) ? $kvs['uid']:0;
        $g_tcid = isset($config['g_tcid']) ? $config['g_tcid']:$config['trdcfg']['tc_id'];
        if($kvs['runfunc'] == 'GETUSERURL') {
            $kvs['user_token'] = UserThirds::where('ut_tcid',$g_tcid)->where('ut_uid',$uid)->value('ut_token');
            $kvs['user_id'] = UserThirds::where('ut_tcid',$g_tcid)->where('ut_uid',$uid)->value('ut_trdid');
        }
        if($kvs['runfunc'] == 'SETUSERRTP') {
            //传过来userids格式为id逗号隔开
            $thirdUserIds = [];
            $userIdsArr = explode(',', $kvs['user_ids']);
            $userThirdsModel = new UserThirds();
            foreach ($userIdsArr as $v){
                $condition = [];
                $condition[] = ['ut_uid', '=', $v];
                $condition[] = ['ut_tcid', '=', $g_tcid];
                $_userId = $userThirdsModel->where($condition)->value('ut_trdname');
                if( $_userId ){
                    $thirdUserIds[] = $_userId;
                }
            }
            $kvs['user_ids'] = implode('|', $thirdUserIds);
        }
        if($kvs['runfunc'] == 'GETONEUSERRTP') {
            $kvs['user_id'] = UserThirds::where('ut_tcid',$g_tcid)->where('ut_uid',$uid)->value('ut_trdname');
        }
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
                    'ut_token' => $datas['data']['token'],
                    'ut_trdid' => $datas['data']['user_id'],
                    'ut_intime' => time(),
                    'ut_trdname' => $datas['data']['player_id'],
                    'ut_state' => 0
                ]);
            }else{
                $guser->save([
                    'ut_token' => $datas['data']['token'],
                    'ut_trdid' => $datas['data']['user_id'],
                    'ut_trdname' => $datas['data']['player_id'],
                ]);
            }
        }
    }

    private function generateSign($data, $secret = ''){
        unset($data["sign"]);
        ksort($data);
        $str = urldecode(http_build_query(myArrayFilter($data)));
        $str .= '&key='.$secret;
        return strtolower(md5($str));
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjMyLCJ0Y19uYW1lIjoiUEcgR2FtZXMiLCJ0Y19pbnRpbWUiOiIyMDI0LTAxLTI0IDAwOjQ4OjEzIiwidGNfY29kZSI6IlBHRkFMU0UiLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL2FwaS5taW5lczI1LmNvbSIsInRjX2FwaV9wcmlrZXkiOiI2ZGUwNDU5NzhjZWZhNTZlMDEyNmZlYWJkMjNmMTQxNyIsInRjX2FwaV9zaWdua2V5IjoiNGM2OGM1NjIxOTQyNzllMzNhNGI1ZTdlZWUyNTE0MTIiLCJ0Y190YWciOiIiLCJ0Y19jdXJyZW5jeSI6IkJSTCIsInRjX2V4Y2hhbmdlcmF0ZSI6IjEuMDAwMDAwMDAiLCJ0Y190b2tlbiI6IiIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOm51bGwsInRjX2xhbmdtYXAiOiJwdCIsInRjX3N0YXR1cyI6MCwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcuYWZiZXQudG9wXC9zdG9yYWdlXC9pbWdcLzIwMjMwMTIyXC8xZjEzMjg0NmNmYjc0NDkwYzdlNzhiNzVjZGZlZDgwYy5qcGciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImJ5Z2FtZSIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMC4wMSIsInRjX21heF9iZXQiOiI5OTk5OTk5LjAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJ0Y19zZXRfY29kZSI6IiIsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6NzUsImdhX3RjaWQiOjMyLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL2FwaVwvd2ViXC9nZXRfbGF1bmNoX3VybCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IiIsImdhX3BwYXJhbSI6IntcIm9wZXJhdG9yX3Rva2VuXCI6XCJ7dGNhcGlrZXl9XCIsXCJ1c2VyX2lkXCI6XCJ7dXNlcl9pZH1cIixcInVzZXJfdG9rZW5cIjpcInt1c2VyX3Rva2VufVwiLFwiZ2FtZV9jb2RlXCI6XCJ7Z3RhZ31cIixcImxhbmd1YWdlXCI6XCJ7dGNsYW5nbWFwfVwiLFwidHNcIjpcInt0c31cIixcInNpZ25cIjpcIntzaWdufVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJ0Y2dhbWV1cmxcIjp7XCJrZXlcIjpcInJhd2RhdGFcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjEsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjo3NiwiZ2FfdGNpZCI6MzIsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IlJFR0lTVEVSIiwiZ2Ffcm91dGUiOiJcL2FwaVwvd2ViXC91c2VyX3Nlc3Npb24iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiIiLCJnYV9wcGFyYW0iOiJ7XCJvcGVyYXRvcl90b2tlblwiOlwie3RjYXBpa2V5fVwiLFwidXNlcl9pZFwiOlwie3VuYW1lfVwiLFwidXNlcl9uYW1lXCI6XCJ7dW5hbWV9XCIsXCJ0c1wiOlwie3RzfVwiLFwic2lnblwiOlwie3NpZ259XCIsXCJjdXJyZW5jeVwiOlwie3RjY3VycmVuY3l9XCJ9IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcInN0YXR1c1wiLFwidmFsdWVcIjowfX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MSwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }

}
