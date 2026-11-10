<?php


namespace app\common\FastGame;


use app\common\model\UserModel;
use app\model\{GameBetsTemp, Games, GameWmSpecial, UserThirds};
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};

class Third_luckysport extends Third
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
//        $name = Meta::initGetField('uname', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']);
//        if( $kvs['runfunc'] == 'REGISTER' ){
//
//        }
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
//        if($func == 'REGISTER'){
//            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
//            if (!$guser){
//                //TODO 错误处理
//                UserThirds::create([
//                    'ut_uid' => $uid,
//                    'ut_tcid' => $tcid,
//                    'ut_trdname' => $ohterkvs['wm_name'],
//                    'ut_intime' => time(),
//                    'ut_state' => 0
//                ]);
//            }else{
//                $guser->ut_trdname =  $ohterkvs['wm_name'];
//                //TODO 错误处理
//                $guser->save();
//            }
//        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjMzLCJ0Y19uYW1lIjoiTHVja3kgU3BvcnQiLCJ0Y19pbnRpbWUiOiIyMDI0LTA2LTA3IDE1OjE4OjI0IiwidGNfY29kZSI6IkxVQ0tZU1BPUlQiLCJ0Y19hcGlfdXJscHJlIjoiaHR0cHM6XC9cL2FwaS51bmkyNDcueHl6XC9hcGkiLCJ0Y19hcGlfcHJpa2V5IjoiQUl6YVN5QVd2SE5Yc1dMSkppLUJ1Nzd1ZG1FaTJLRGlCWXF4ekJvIiwidGNfYXBpX3NpZ25rZXkiOiIxQ3A1WlZsalppNjc2R3EiLCJ0Y190YWciOiJjbXQtemYtc3ViLTg4MHIyZDFAbWVyY2hhbnQudW5pMjQ3Lnh5eiIsInRjX2N1cnJlbmN5IjoiQkRUIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiZXlKaGJHY2lPaUpTVXpJMU5pSXNJbXRwWkNJNkltTmpOV1UwTVRnME0yTTFaRFV5WlRZNFpXWTFNMlV5WW1Wak9UZ3hORE5rWVRFME5Ea3dOV1VpTENKMGVYQWlPaUpLVjFRaWZRLmV5SnBjM01pT2lKb2RIUndjem92TDNObFkzVnlaWFJ2YTJWdUxtZHZiMmRzWlM1amIyMHZZMjEwTFhwbUxYTjFZaTA0T0RCeU1tUXhJaXdpWVhWa0lqb2lZMjEwTFhwbUxYTjFZaTA0T0RCeU1tUXhJaXdpWVhWMGFGOTBhVzFsSWpveE56STFNek0yTmpRd0xDSjFjMlZ5WDJsa0lqb2ljMHhuWkVGaGNuUmFTVkp2YTJNM01FeDFXazlQZVRWeFdsazVNaUlzSW5OMVlpSTZJbk5NWjJSQllYSjBXa2xTYjJ0ak56Qk1kVnBQVDNrMWNWcFpPVElpTENKcFlYUWlPakUzTWpVek16WTJOREFzSW1WNGNDSTZNVGN5TlRNME1ESTBNQ3dpWlcxaGFXd2lPaUpqYlhRdGVtWXRjM1ZpTFRnNE1ISXlaREZBYldWeVkyaGhiblF1ZFc1cE1qUTNMbmg1ZWlJc0ltVnRZV2xzWDNabGNtbG1hV1ZrSWpwbVlXeHpaU3dpWm1seVpXSmhjMlVpT25zaWFXUmxiblJwZEdsbGN5STZleUpsYldGcGJDSTZXeUpqYlhRdGVtWXRjM1ZpTFRnNE1ISXlaREZBYldWeVkyaGhiblF1ZFc1cE1qUTNMbmg1ZWlKZGZTd2ljMmxuYmw5cGJsOXdjbTkyYVdSbGNpSTZJbkJoYzNOM2IzSmtJbjE5LlAtNlE1SmZCc1kyWlo4R1NGOXNQc1pFd2ROTDVjU0k0V1l3dlVUTGFuTWtiQVZCSC1rdXZrTmItdnBENVpGa0VZOXBWN2NsUXdYeGZ6eXhWZ3o2T0Q0ZWNiM0FJbWYzaXlXZUk4UHVKZXlzQ0lvODJpb2ExbTB2UXJPc3lMdDdpQVNQZXNTdkFhbWYyZ1Myc25Hc3Q3Q3lyV0xhYmJtTHV5a1Y2ZmowYkVSdkMtMEg5TmVMbUZMOUZtUTZPQ0RHZ0dwVWc4VWpXMnRSUC1nNExUNkZFRFpFNm9PaDNCTHNhWjljVmM0YU9oOXpoTVBQVVJJNkw4Ty15QkFkMnRtUkNXbWpMcHJsS2JXYjFmeUxMaHprT1p6ai1KaFhPcHFfUDVkcGhlVEdRenFyUWFJVEtSZEVQMnlnT0RudHk1cnNHRjFZQjBjalE1eVR6YzJVOTdBY2c4dyIsInRjX3Rva2VudGltZSI6MzYwMCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOm51bGwsInRjX2xhbmdtYXAiOiJlbiIsInRjX3N0YXR1cyI6MCwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcuaGlyYWJkNC5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA4MDNcL2JkZGJlZmI1MmQyZjA3N2Q1NTM0ZDNiMzU2OTM3ZWYyLnBuZyIsInRjX2dldGJldGxpc3R0eXBlIjoiYnlnYW1lIiwidGNfdXNlcmlkZW50aWZ5IjoiZGVmYXVsdCIsInRjX21pbl9iZXQiOiIwLjAwIiwidGNfbWF4X2JldCI6Ijk5OTk5OTk5OTk5LjAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy5oaXJhYmQ0LmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MDgwM1wvZjdmMmZjZDNlMTNmYTlhOGE1Yzc0NDYzNjc4YTBiNTkucG5nIiwidGNfdGltZXpvbmUiOjAsInRjX2V4Y2hhbmdlaW50IjowLCJ0Y19zZXRfY29kZSI6IiIsImlzX25ld190YWIiOjB9LCJhcGlzIjpbeyJnYV9pZCI6NzIsImdhX3RjaWQiOjMzLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRBVVRIIiwiZ2Ffcm91dGUiOiJcL0dldElEVG9rZW4/a2V5PXt0Y2FwaWtleX0iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCIsXCJhY2NlcHRcIjogXCJhcHBsaWNhdGlvblwvanNvblwifSIsImdhX3BwYXJhbSI6IntcImVtYWlsXCI6XCJ7dGN0YWd9XCIsXCJwYXNzd29yZFwiOlwie3RjYXBpc2lnbmtleX1cIn0iLCJnYV90eXBlIjoiZ2V0YXV0aHRva2VuIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiaWRUb2tlblwiLFwidmFsdWVcIjpcIlZcIn0sXCJ0Y3Rva2VuXCI6e1wia2V5XCI6XCJpZFRva2VuXCJ9LFwidGN0b2tlbnRpbWVcIjp7XCJrZXlcIjpcImV4cGlyZXNJblwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6Imh0dHBzOlwvXC9tdGF1dGgudW5pMjQ3Lnh5eiJ9LHsiZ2FfaWQiOjczLCJnYV90Y2lkIjozMywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9hdXRoXC9tZXJjaGFudFwvbG9naW4tdjJcLz9rZXk9e3RjYXBpa2V5fSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcImFjY2VwdFwiOiBcImFwcGxpY2F0aW9uXC9qc29uXCIsXCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCIsXCJBdXRob3JpemF0aW9uXCI6XCJCZWFyZXIge3RjdG9rZW59XCJ9IiwiZ2FfcHBhcmFtIjoie1wibGFuZ3VhZ2VcIjpcInt0Y2xhbmdtYXB9XCIsXCJwbGF5ZXJfaWRcIjpcInt1bmFtZX1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOlwiMjAwXCJ9LFwidXR0b2tlblwiOntcImtleVwiOlwiYWNjZXNzX3Rva2VuXCJ9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJ3aWRnZXRfbG9hZGVyX3NjcmlwdFwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjc0LCJnYV90Y2lkIjozMywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvYXV0aFwvbWVyY2hhbnRcL2NyZWF0ZS1tZW1iZXJcLz9rZXk9e3RjYXBpa2V5fSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcImFjY2VwdFwiOiBcImFwcGxpY2F0aW9uXC9qc29uXCIsXCJDb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC9qc29uXCIsXCJBdXRob3JpemF0aW9uXCI6XCJCZWFyZXIge3RjdG9rZW59XCJ9IiwiZ2FfcHBhcmFtIjoie1wicGxheWVyX2lkXCI6XCJ7dW5hbWV9XCJ9IiwiZ2FfdHlwZSI6InJlZyIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6XCIyMDBcIn0sXCJ0Y3VzZXJrZXlcIjp7XCJrZXlcIjpcIlVzZXIudXNlcl9pZFwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }
}
