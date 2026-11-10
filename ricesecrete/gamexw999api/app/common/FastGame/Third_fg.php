<?php


namespace app\common\FastGame;


use app\common\lib\Util;
use app\model\UserThirds;
use app\common\FastGame\Base\{Game, Http, Meta, Third, Utils};
use app\model\Games;

class Third_fg extends Third
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
        // 签名秘钥
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
        $param = $data['param'];
        // 删除其他变量
        unset($param['s']);
        $sign = $param['sign'];
        unset($param['sign']);
        unset($param['third']);
        unset($param['v1']);
        unset($param['action_method']);
        unset($param['XDEBUG_SESSION_START']); // TODO 删掉

        // 按键名排序
        ksort($param);

        // 获取加密秘钥 wallet_key
        $param['key'] = $config['trdcfg']["tc_api_signkey"];
        // 生成签名并校验，怎么urldecode掉了呢
        if ($sign == strtoupper(md5(urldecode(http_build_query($param))))) {
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
        $kvs['openid'] = UserThirds::where('ut_tcid',$g_tcid)->where('ut_uid',$uid)->value('ut_trdid');
        if($kvs['runfunc'] == 'GETUSERURL') {
            $kvs['ip'] = Util::get_ip();
            $kvs['return_url'] = getAppUrl();
            $kvs['return_url'] = getAppUrl();
        }elseif(substr($kvs['runfunc'],0,10)=="GETBETLIST"){
            $idUrl = '';
            if( $kvs['id'] == 0 ){
                //第一次不加参数
            }else{
                $idUrl = '/id/' . $kvs['id'];
            }
            $kvs['id_url'] = $idUrl;
        }elseif (in_array($kvs['runfunc'], ['DEPOSIT', 'WITHDRAW'])) {

            $kvs['externaltransactionid'] = md5(date("YmdHis") . Utils::random(10, true));
            $tomeorthird = $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0;
            if( $kvs['runfunc'] == 'DEPOSIT' ){
                $kvs['amount'] = intval($kvs['o_amount']);
            }else{
                $kvs['amount'] = intval($kvs['o_amount']) * -1;

            }
        }
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        if( $func == 'REGISTER' ){
            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
            if( !$guser ){
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_trdid' => $datas['data']['openid'] ?? '',
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }else{
                $guser->save([
                    'ut_trdid' => $datas['data']['openid'] ?? '',
                ]);
            }
        }

        if($func == 'GETUSERURL'){

            $kds['tcgameurl'] = $datas['data']['game_url'].'&token='.$datas['data']['token'];
        }
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjkwLCJ0Y19uYW1lIjoiRkciLCJ0Y19pbnRpbWUiOiIyMDI0LTAzLTA2IDA0OjE4OjU2IiwidGNfY29kZSI6IkZHIiwidGNfYXBpX3VybHByZSI6Imh0dHBzOlwvXC9KU0xUZmc3MDYtcnN0NjE2LmZnYWFhYS5uZXQiLCJ0Y19hcGlfcHJpa2V5IjoiMWZjNTk2YmJlZWQzNDVmNDI3MzMyYzcxMDhlMTc5MmEiLCJ0Y19hcGlfc2lnbmtleSI6InQ2MTZwcm90c2lnbiIsInRjX3RhZyI6IkpTTFRmZzcwNi1yc3Q2MTYiLCJ0Y19jdXJyZW5jeSI6IklOIiwidGNfZXhjaGFuZ2VyYXRlIjoiMTAwLjAwMDAwMDAwIiwidGNfdG9rZW4iOiIiLCJ0Y190b2tlbnRpbWUiOjAsInRjX3Byb3h5Y29uZmlnIjoiIiwidGNfdGFza2NvbmZpZyI6bnVsbCwidGNfdGFza3ZhbHVlIjoie1widGhpcmRzdFwiOjAsXCJzdGF0c3RcIjowLFwianNzdFwiOjAsXCJmeXN0XCI6MH0iLCJ0Y19sYW5nbWFwIjoiZW4tdXMiLCJ0Y19zdGF0dXMiOjEsInNvcnQiOjcwLCJpY29uIjoiaHR0cHM6XC9cL2ltZy5mZy5nYW1lXC9zdG9yYWdlXC9pbWdcLzIwMjQwNzE2XC81MDQ1ZjgzMzgwMDI1OGIxMjA0MjMxMjJkMDNhYjlhMy5wbmciLCJ0Y19nZXRiZXRsaXN0dHlwZSI6ImFsbCIsInRjX3VzZXJpZGVudGlmeSI6ImRlZmF1bHQiLCJ0Y19taW5fYmV0IjoiMC4wMCIsInRjX21heF9iZXQiOiIwLjAwIiwiY291bnRyeSI6bnVsbCwidGNfc3BlY2lhbF9zdGF0dXMiOjAsInNpbXBsZV9pY29uIjoiaHR0cHM6XC9cL2ltZy5mZy5nYW1lXC9zdG9yYWdlXC9pbWdcLzIwMjQwNzE2XC8yMTgyYTIxMmU3NjM1MDhkNGQ3NjMxZjVhZDQ1YmExMC5wbmciLCJ0Y190aW1lem9uZSI6MCwidGNfZXhjaGFuZ2VpbnQiOjAsInRjX3NldF9jb2RlIjoiIiwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjoyMDcsImdhX3RjaWQiOjkwLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL3YzXC9wbGF5ZXJfdWNoaXBzXC97b3BlbmlkfSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIm1lcmNoYW50bmFtZVwiOlwie3RjdGFnfVwiLFwibWVyY2hhbnRjb2RlXCI6XCJ7dGNhcGlrZXl9XCIsXCJBY2NlcHRcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJvcGVuaWRcIjpcIntvcGVuaWR9XCIsXCJhbW91bnRcIjpcInthbW91bnR9XCIsXCJleHRlcm5hbHRyYW5zYWN0aW9uaWRcIjpcIntleHRlcm5hbHRyYW5zYWN0aW9uaWR9XCIsXCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6MH0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImRhdGEuYmFsYW5jZVwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjIwNSwiZ2FfdGNpZCI6OTAsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJBTEFOQ0UiLCJnYV9yb3V0ZSI6IlwvdjNcL3BsYXllcl9jaGlwc1wve29wZW5pZH0iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJtZXJjaGFudG5hbWVcIjpcInt0Y3RhZ31cIixcIm1lcmNoYW50Y29kZVwiOlwie3RjYXBpa2V5fVwifSIsImdhX3BwYXJhbSI6IntcIm9wZW5pZFwiOlwie29wZW5pZH1cIixcImZnX3Bvc3R0eXBlXCI6XCJmb3JtXCJ9IiwiZ2FfdHlwZSI6Im90aGVyIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjowfSxcInRjYmFsYW5jZVwiOntcImtleVwiOlwiZGF0YS5iYWxhbmNlXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTkxLCJnYV90Y2lkIjo5MCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVGFyY2FkZSIsImdhX3JvdXRlIjoiXC92M1wvYWdlbnRcL2xvZ19ieV9wYWdlXC9ndFwvYXJjYWRle2lkX3VybH0iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJtZXJjaGFudG5hbWVcIjpcInt0Y3RhZ31cIixcIm1lcmNoYW50Y29kZVwiOlwie3RjYXBpa2V5fVwiLFwiY29udGVudC10eXBlXCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie30iLCJnYV90eXBlIjoidGFzayIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6MH0sXCJyX2xpc3RcIjp7XCJrZXlcIjpcImRhdGEuZGF0YVwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IntcImFsbFwiOntcImxhc3RrZXlcIjpudWxsLFwibGFzdHJ1bnRpbWVcIjoxNzI1Mzk1MDQxLFwiY3VydGltZVwiOjAsXCJlbmRcIjoxNzI1Mzk1MDQxLFwiaWRcIjpcIjIxNjg4MTMzNzI3XCJ9fSIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE4OSwiZ2FfdGNpZCI6OTAsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVExJU1RjaGVzcyIsImdhX3JvdXRlIjoiXC92M1wvYWdlbnRcL2xvZ19ieV9wYWdlXC9ndFwvY2hlc3N7aWRfdXJsfSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIm1lcmNoYW50bmFtZVwiOlwie3RjdGFnfVwiLFwibWVyY2hhbnRjb2RlXCI6XCJ7dGNhcGlrZXl9XCIsXCJjb250ZW50LXR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7fSIsImdhX3R5cGUiOiJ0YXNrIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiY29kZVwiLFwidmFsdWVcIjowfSxcInJfbGlzdFwiOntcImtleVwiOlwiZGF0YS5kYXRhXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoie1wiYWxsXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjE3MjUzOTUwNDEsXCJjdXJ0aW1lXCI6MCxcImVuZFwiOjE3MjUzOTUwNDEsXCJpZFwiOlwiNjcwOTIyOTEyXCJ9fSIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjE4OCwiZ2FfdGNpZCI6OTAsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVExJU1RodW50ZXIiLCJnYV9yb3V0ZSI6IlwvdjNcL2FnZW50XC9sb2dfYnlfcGFnZVwvZ3RcL2h1bnRlcntpZF91cmx9IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wibWVyY2hhbnRuYW1lXCI6XCJ7dGN0YWd9XCIsXCJtZXJjaGFudGNvZGVcIjpcInt0Y2FwaWtleX1cIixcImNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwifSIsImdhX3BwYXJhbSI6Int9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjB9LFwicl9saXN0XCI6e1wia2V5XCI6XCJkYXRhLmRhdGFcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiJ7XCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTcyNTM5NTA0MSxcImN1cnRpbWVcIjowLFwiZW5kXCI6MTcyNTM5NTA0MSxcImlkXCI6XCI4MTI4MDAwODY5NzI2MTIzMVwifX0iLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxOTAsImdhX3RjaWQiOjkwLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCRVRMSVNUc2xvdCIsImdhX3JvdXRlIjoiXC92M1wvYWdlbnRcL2xvZ19ieV9wYWdlXC9ndFwvc2xvdHtpZF91cmx9IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wibWVyY2hhbnRuYW1lXCI6XCJ7dGN0YWd9XCIsXCJtZXJjaGFudGNvZGVcIjpcInt0Y2FwaWtleX1cIixcImNvbnRlbnQtdHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwifSIsImdhX3BwYXJhbSI6Int9IiwiZ2FfdHlwZSI6InRhc2siLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjB9LFwicl9saXN0XCI6e1wia2V5XCI6XCJkYXRhLmRhdGFcIn19IiwiZ2FfcmF0ZSI6MSwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiJ7XCJhbGxcIjp7XCJsYXN0a2V5XCI6bnVsbCxcImxhc3RydW50aW1lXCI6MTcyNTM5NTA0MSxcImN1cnRpbWVcIjowLFwiZW5kXCI6MTcyNTM5NTA0MSxcImlkXCI6XCIyMTY4ODc2MjIzMFwifX0iLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxODYsImdhX3RjaWQiOjkwLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRVU0VSVVJMIiwiZ2Ffcm91dGUiOiJcL3YzXC9sYXVuY2hfZ2FtZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIm1lcmNoYW50bmFtZVwiOlwie3RjdGFnfVwiLFwibWVyY2hhbnRjb2RlXCI6XCJ7dGNhcGlrZXl9XCIsXCJBY2NlcHRcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIn0iLCJnYV9wcGFyYW0iOiJ7XCJsYW5ndWFnZVwiOlwie3RjbGFuZ21hcH1cIixcImdhbWVfdHlwZVwiOlwiaDVcIixcIm1lbWJlcl9jb2RlXCI6XCJmZ3t1bmFtZX1cIixcImdhbWVfaWRcIjpcIntndGFnfVwiLFwicmV0dXJuX3VybFwiOlwie3JldHVybl91cmx9XCIsXCJpcFwiOlwie2lwfVwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJjb2RlXCIsXCJ2YWx1ZVwiOjB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJkYXRhLmdhbWVfdXJsXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTg3LCJnYV90Y2lkIjo5MCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvdjNcL3BsYXllcnMiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJtZXJjaGFudG5hbWVcIjpcInt0Y3RhZ31cIixcIm1lcmNoYW50Y29kZVwiOlwie3RjYXBpa2V5fVwiLFwiQWNjZXB0XCI6XCJhcHBsaWNhdGlvblwveC13d3ctZm9ybS11cmxlbmNvZGVkXCJ9IiwiZ2FfcHBhcmFtIjoie1wibWVtYmVyX2NvZGVcIjpcImZne3VuYW1lfVwiLFwicGFzc3dvcmRcIjpcIjEyMzQ1NlwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoicmVnIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOiB7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6XCIwLDEwNVwifSxcInV0dG9rZW5cIjp7XCJrZXlcIjpcImRhdGEub3BlbmlkXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MjA2LCJnYV90Y2lkIjo5MCwiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiV0lUSERSQVciLCJnYV9yb3V0ZSI6IlwvdjNcL3BsYXllcl91Y2hpcHNcL3tvcGVuaWR9IiwiZ2FfcnR5cGUiOiJwb3N0IiwiZ2FfZ3BhcmFtIjoiIiwiZ2FfaHBhcmFtIjoie1wibWVyY2hhbnRuYW1lXCI6XCJ7dGN0YWd9XCIsXCJtZXJjaGFudGNvZGVcIjpcInt0Y2FwaWtleX1cIixcIkFjY2VwdFwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwifSIsImdhX3BwYXJhbSI6IntcIm9wZW5pZFwiOlwie29wZW5pZH1cIixcImFtb3VudFwiOlwiLXthbW91bnR9XCIsXCJleHRlcm5hbHRyYW5zYWN0aW9uaWRcIjpcIntleHRlcm5hbHRyYW5zYWN0aW9uaWR9XCIsXCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImNvZGVcIixcInZhbHVlXCI6MH0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcImRhdGEuYmFsYW5jZVwifX0iLCJnYV9yYXRlIjoxLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9XX0=';
    }
}
