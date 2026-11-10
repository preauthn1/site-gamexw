<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;
use app\api\redis\ApiRedis;
use app\common\FastGame\Base\Redis;

class Third_rsg extends Third
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
        //其它配置多的，合并在token这里进行分割
        $token = explode('_',$config['trdcfg']["tc_token"]);

        $req_base64 = $this->Encrypt($config['trdcfg']["tc_api_signkey"],json_encode($data),$token[0]);
        $timestamp=time();
        $signature_data= $config['trdcfg']["tc_tag"].$config['trdcfg']["tc_api_prikey"].$timestamp.$req_base64;
        $signature=md5($signature_data);
        return compact('signature','timestamp','req_base64');
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

        //其它配置多的，合并在token这里进行分割
        $token = explode('_',$config['trdcfg']["tc_token"]);
        if(in_array($kvs['runfunc'],['REGISTER','GETUSERURL','GETBALANCE','DEPOSIT','WITHDRAW'])){
            $uname = Meta::initGetField('uname',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 用户名
            $tccurrency = Meta::initGetField('tccurrency', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 货币
        }
        // 注册
        if($kvs['runfunc'] == "REGISTER"){
            $data = [
                'SystemCode'=>$token[1],
                'WebId'=>$token[2],
                'UserId'=>$uname,
                'Currency'=>$tccurrency
            ];
        }
        // 获取游戏URL
        if($kvs['runfunc'] == 'GETUSERURL') {
            $lang = Meta::initGetField('tclangmap',  $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 语言
            $gtag = Meta::initGetField('gtag', $config['trdcfg']['tc_id'], $config['g_id'], $kvs['uid']); // 游戏代码
            $data = [
                'SystemCode'=>$token[1],
                'WebId'=>$token[2],
                'UserId'=>$uname,
                'UserName'=>$uname,
                'Currency'=>$tccurrency,
                'Language'=>$lang,
            ];
            if( $gtag ){
                $data['GameId'] = intval($gtag);
                $data['ExitAction'] = getAppUrl();
                $kvs['game_url'] = 'GetURLToken';
            }else{
                $kvs['game_url'] = 'GetLobbyURLToken';
            }
        }
        // 获取用户余额
        if($kvs['runfunc'] == 'GETBALANCE') {
            $data = [
                'SystemCode'=>$token[1],
                'WebId'=>$token[2],
                'UserId'=>$uname,
                'Currency'=>$tccurrency
            ];
        }
        // 转入/转出
        if($kvs['runfunc'] == 'DEPOSIT' || $kvs['runfunc'] == 'WITHDRAW') {
            $ran = Utils::random(16);
            $key = "RSG_rand_".$uname;
            $rand = Redis::get($key);
            if(!$rand){
                Redis::setex($key, $ran, 3);
                $rand = $ran;
            }
            $data = [
                'SystemCode'=>$token[1],
                'WebId'=>$token[2],
                'UserId'=>$uname,
                'TransactionID'=>$rand,
                'Currency'=>$tccurrency,
                'Balance'=>floatval(Third::transExchangerate($kvs['o_amount'], $kvs['runfunc'] == 'DEPOSIT' ? 1 : 0, 2)),
            ];
        }
        //获取游戏注单数据-老虎机类型
        if($kvs['runfunc'] == 'GETBETLIST') {
            $data = [
                'SystemCode'=>$token[1],
                'WebId'=>$token[2],
                'GameType'=>1,
                'TimeStart'=>$kvs['TimeStart'],
                'TimeEnd'=>$kvs['TimeEnd']
            ];
        }
        //获取游戏注单数据-捕鱼类型
        if($kvs['runfunc'] == 'GETBETLIST2') {
            $data = [
                'SystemCode'=>$token[1],
                'WebId'=>$token[2],
                'GameType'=>2,
                'TimeStart'=>$kvs['TimeStart'],
                'TimeEnd'=>$kvs['TimeEnd']
            ];
        }

        $sign_data = $this->MakeSign($config,$data);
        $kvs['Msg'] = $sign_data['req_base64'];
        $kvs['signature'] = $sign_data['signature'];
        $kvs['timestamp'] = $sign_data['timestamp'];
        Utils::L("{$config['trdcfg']['tc_id']}/http_data",[$kvs['runfunc'], $data,$kvs['Msg']]);
        // TODO: Implement transOtherKvs() method.
    }

    // 转换第三方返回数据为本平台标准数据格式

    /**
     * @throws \Exception
     */
    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        $config = $this->apicfgs;
        $token = explode('_',$config['trdcfg']["tc_token"]);
        $res_data = $this->Decrypt($config['trdcfg']["tc_api_signkey"],$datas,$token[0]);
        if($res_data){
            $res_data = json_decode($res_data,true);
            Utils::L("{$config['trdcfg']['tc_id']}/ResultData",['解密数据',$func, $res_data]);
            if($func == 'REGISTER'){
                if($res_data['ErrorCode'] == 0){
                    $kds['tcuserkey'] = $res_data['Data']['UserId'];
                }else{
                    $kds['tcuserkey'] = '';
                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error",['数据错误',$func, $res_data]);
                }
            }

            if($func == 'GETUSERURL'){
                if($res_data['ErrorCode'] == 0) {
                    $kds['tcgameurl'] = $res_data['Data']['URL'];
                }else{
                    $kds['tcgameurl'] = '';
                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error",['数据错误',$func, $res_data]);
                }
            }

            if($func == 'GETBALANCE' || $func == 'DEPOSIT' || $func == 'WITHDRAW'){
                if($res_data['ErrorCode'] == 0) {
                    $kds['tcbalance'] = $res_data['Data']['CurrentPlayerBalance'];
                }else{
                    $kds['tcbalance'] = 0;
                    Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error",['数据错误',$func, $res_data], true, true);
                    //TODO 这里必须强行阻断，否则会导致三方未成功，但是我们平台成功了
                    throw new \Exception("三方转入/转出失败{$func}");
                }
            }

        }else{
            Utils::L("{$config['trdcfg']['tc_id']}/ResultData_error",['解密失败', $func,$datas], true, true);
        }
    }

    // 加密
    function Encrypt($DesKey, $data, $DesIV)
    {
        $encrypt_data = openssl_encrypt($data,'DES-CBC',$DesKey,OPENSSL_RAW_DATA ,$DesIV);
        $req_base64 = base64_encode($encrypt_data);
        return $req_base64;
    }

    // 解密
    function Decrypt($DesKey, $data, $DesIV)
    {
        return openssl_decrypt(base64_decode($data),'DES-CBC',$DesKey,OPENSSL_RAW_DATA ,$DesIV);
    }

    //导入文件
    public function importPlatform() {
        return 'eyJjb25maWciOnsidGNfaWQiOjMzLCJ0Y19uYW1lIjoiUlNHXHU5NmZiXHU1YjUwIiwidGNfaW50aW1lIjoiMjAyNC0wNS0xNSAxMzo1OTo0OCIsInRjX2NvZGUiOiJSU0ciLCJ0Y19zZXRfY29kZSI6IiIsInRjX2FwaV91cmxwcmUiOiJodHRwOlwvXC9YV0JFVC1hcGkucnNnYW1pbmc4ODguY29tXC9XaXRoQmFsYW5jZSIsInRjX2FwaV9wcmlrZXkiOiJJVTNGS1FFRSIsInRjX2FwaV9zaWdua2V5IjoiWFM4T0RXNEgiLCJ0Y190YWciOiJibG02czQ5NWFuYnkiLCJ0Y19jdXJyZW5jeSI6Ik5UIiwidGNfZXhjaGFuZ2VyYXRlIjoiMS4wMDAwMDAwMCIsInRjX3Rva2VuIjoiR0czWUMyOVRfWFdCRVRfVDAxMyIsInRjX3Rva2VudGltZSI6MCwidGNfcHJveHljb25maWciOiIiLCJ0Y190YXNrY29uZmlnIjpudWxsLCJ0Y190YXNrdmFsdWUiOiJ7XCJ0aGlyZHN0XCI6MCxcInN0YXRzdFwiOjAsXCJqc3N0XCI6MCxcImZ5c3RcIjowfSIsInRjX2xhbmdtYXAiOiJ6aC1UVyIsInRjX3N0YXR1cyI6MSwic29ydCI6MCwiaWNvbiI6Imh0dHBzOlwvXC9pbWcueHdiZXR0aW5nLmNvbVwvc3RvcmFnZVwvaW1nXC8yMDI0MDYwN1wvNWJmYWZhNWUyODZlZGRiM2ZiNjc0NjBlYjEzNjI3NjgucG5nIiwidGNfZ2V0YmV0bGlzdHR5cGUiOiJhbGwiLCJ0Y191c2VyaWRlbnRpZnkiOiJkZWZhdWx0IiwidGNfbWluX2JldCI6IjAuMDAwMCIsInRjX21heF9iZXQiOiIwLjAwMDAiLCJjb3VudHJ5IjpudWxsLCJ0Y19zcGVjaWFsX3N0YXR1cyI6MCwic2ltcGxlX2ljb24iOiJodHRwczpcL1wvaW1nLnh3YmV0dGluZy5jb21cL3N0b3JhZ2VcL2ltZ1wvMjAyNDA2MDdcLzVkYmY2MTk4NDYxZWIwZmM5MzhlZjUwNWEyMDMyZDI2LnBuZyIsInRjX3RpbWV6b25lIjowLCJ0Y19leGNoYW5nZWludCI6MSwiaXNfbmV3X3RhYiI6MH0sImFwaXMiOlt7ImdhX2lkIjoxMDYsImdhX3RjaWQiOjMzLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJERVBPU0lUIiwiZ2Ffcm91dGUiOiJcL1BsYXllclwvRGVwb3NpdCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtVHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwiLFwiWC1BUEktQ2xpZW50SURcIjpcInt0Y3RhZ31cIixcIlgtQVBJLVNpZ25hdHVyZVwiOlwie3NpZ25hdHVyZX1cIixcIlgtQVBJLVRpbWVzdGFtcFwiOlwie3RpbWVzdGFtcH1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJNc2dcIjpcIntNc2d9XCIsXCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImh0dHBjb2RlXCIsXCJ2YWx1ZVwiOjIwMH0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcInJhd2RhdGFcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOiIifSx7ImdhX2lkIjoxMDUsImdhX3RjaWQiOjMzLCJnYV9naWQiOjAsImdhX2Z1bmMiOiJHRVRCQUxBTkNFIiwiZ2Ffcm91dGUiOiJcL1BsYXllclwvR2V0QmFsYW5jZSIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtVHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwiLFwiWC1BUEktQ2xpZW50SURcIjpcInt0Y3RhZ31cIixcIlgtQVBJLVNpZ25hdHVyZVwiOlwie3NpZ25hdHVyZX1cIixcIlgtQVBJLVRpbWVzdGFtcFwiOlwie3RpbWVzdGFtcH1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJNc2dcIjpcIntNc2d9XCIsXCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwifSIsImdhX3R5cGUiOiJvdGhlciIsImdhX3JsdGZpZWxkIjoiIiwiZ2Ffa2V5ZmllbGQiOiJ7XCJyaWdodGZsYWdcIjp7XCJrZXlcIjpcImh0dHBjb2RlXCIsXCJ2YWx1ZVwiOjIwMH0sXCJ0Y2JhbGFuY2VcIjp7XCJrZXlcIjpcInJhd2RhdGFcIn19IiwiZ2FfcmF0ZSI6MCwiZ2FfbmVlZHNpZ24iOjAsImdhX3Rhc2tjZmciOiIiLCJnYV90YXNrdmFsdWUiOiIiLCJnYV91cmxwcmUiOm51bGx9LHsiZ2FfaWQiOjEwOCwiZ2FfdGNpZCI6MzMsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IkdFVEJFVExJU1QiLCJnYV9yb3V0ZSI6IlwvSGlzdG9yeVwvR2V0R2FtZURldGFpbCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtVHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwiLFwiWC1BUEktQ2xpZW50SURcIjpcInt0Y3RhZ31cIixcIlgtQVBJLVNpZ25hdHVyZVwiOlwie3NpZ25hdHVyZX1cIixcIlgtQVBJLVRpbWVzdGFtcFwiOlwie3RpbWVzdGFtcH1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJNc2dcIjpcIntNc2d9XCIsXCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwifSIsImdhX3R5cGUiOiJ0YXNrIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiaHR0cGNvZGVcIixcInZhbHVlXCI6MjAwfSxcInJfbGlzdFwiOntcImtleVwiOlwicmF3ZGF0YVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6bnVsbCwiZ2FfdGFza3ZhbHVlIjoie1wiYWxsXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjE3MjU1NjAzNDUsXCJjdXJ0aW1lXCI6MCxcIlRpbWVTdGFydFwiOlwiMjAyNC0wOS0wNiAwMjoxMVwiLFwiVGltZUVuZFwiOlwiMjAyNC0wOS0wNiAwMjoxNVwiLFwiZW5kXCI6MTcyNTU2MDE2NX19IiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTA5LCJnYV90Y2lkIjozMywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUQkVUTElTVDIiLCJnYV9yb3V0ZSI6IlwvSGlzdG9yeVwvR2V0R2FtZURldGFpbCIsImdhX3J0eXBlIjoicG9zdCIsImdhX2dwYXJhbSI6IiIsImdhX2hwYXJhbSI6IntcIkNvbnRlbnQtVHlwZVwiOlwiYXBwbGljYXRpb25cL3gtd3d3LWZvcm0tdXJsZW5jb2RlZFwiLFwiWC1BUEktQ2xpZW50SURcIjpcInt0Y3RhZ31cIixcIlgtQVBJLVNpZ25hdHVyZVwiOlwie3NpZ25hdHVyZX1cIixcIlgtQVBJLVRpbWVzdGFtcFwiOlwie3RpbWVzdGFtcH1cIn0iLCJnYV9wcGFyYW0iOiJ7XCJNc2dcIjpcIntNc2d9XCIsXCJmZ19wb3N0dHlwZVwiOlwiZm9ybVwifSIsImdhX3R5cGUiOiJ0YXNrIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiaHR0cGNvZGVcIixcInZhbHVlXCI6MjAwfSxcInJfbGlzdFwiOntcImtleVwiOlwicmF3ZGF0YVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6bnVsbCwiZ2FfdGFza3ZhbHVlIjoie1wiYWxsXCI6e1wibGFzdGtleVwiOm51bGwsXCJsYXN0cnVudGltZVwiOjE3MjU1NjAzNDUsXCJjdXJ0aW1lXCI6MCxcIlRpbWVTdGFydFwiOlwiMjAyNC0wOS0wNiAwMjoxMVwiLFwiVGltZUVuZFwiOlwiMjAyNC0wOS0wNiAwMjoxNVwiLFwiZW5kXCI6MTcyNTU2MDE2NX19IiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTA0LCJnYV90Y2lkIjozMywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiR0VUVVNFUlVSTCIsImdhX3JvdXRlIjoiXC9QbGF5ZXJcL3tnYW1lX3VybH0iLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIixcIlgtQVBJLUNsaWVudElEXCI6XCJ7dGN0YWd9XCIsXCJYLUFQSS1TaWduYXR1cmVcIjpcIntzaWduYXR1cmV9XCIsXCJYLUFQSS1UaW1lc3RhbXBcIjpcInt0aW1lc3RhbXB9XCJ9IiwiZ2FfcHBhcmFtIjoie1wiTXNnXCI6XCJ7TXNnfVwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJodHRwY29kZVwiLFwidmFsdWVcIjoyMDB9LFwidGNnYW1ldXJsXCI6e1wia2V5XCI6XCJyYXdkYXRhXCJ9fSIsImdhX3JhdGUiOjEsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjoiIn0seyJnYV9pZCI6MTAzLCJnYV90Y2lkIjozMywiZ2FfZ2lkIjowLCJnYV9mdW5jIjoiUkVHSVNURVIiLCJnYV9yb3V0ZSI6IlwvUGxheWVyXC9DcmVhdGVQbGF5ZXIiLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIixcIlgtQVBJLUNsaWVudElEXCI6XCJ7dGN0YWd9XCIsXCJYLUFQSS1TaWduYXR1cmVcIjpcIntzaWduYXR1cmV9XCIsXCJYLUFQSS1UaW1lc3RhbXBcIjpcInt0aW1lc3RhbXB9XCJ9IiwiZ2FfcHBhcmFtIjoie1wiTXNnXCI6XCJ7TXNnfVwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoicmVnIiwiZ2Ffcmx0ZmllbGQiOiIiLCJnYV9rZXlmaWVsZCI6IntcInJpZ2h0ZmxhZ1wiOntcImtleVwiOlwiaHR0cGNvZGVcIixcInZhbHVlXCI6MjAwfSxcInRjdXNlcmtleVwiOntcImtleVwiOlwicmF3ZGF0YVwifX0iLCJnYV9yYXRlIjowLCJnYV9uZWVkc2lnbiI6MCwiZ2FfdGFza2NmZyI6IiIsImdhX3Rhc2t2YWx1ZSI6IiIsImdhX3VybHByZSI6IiJ9LHsiZ2FfaWQiOjEwNywiZ2FfdGNpZCI6MzMsImdhX2dpZCI6MCwiZ2FfZnVuYyI6IldJVEhEUkFXIiwiZ2Ffcm91dGUiOiJcL1BsYXllclwvV2l0aGRyYXciLCJnYV9ydHlwZSI6InBvc3QiLCJnYV9ncGFyYW0iOiIiLCJnYV9ocGFyYW0iOiJ7XCJDb250ZW50LVR5cGVcIjpcImFwcGxpY2F0aW9uXC94LXd3dy1mb3JtLXVybGVuY29kZWRcIixcIlgtQVBJLUNsaWVudElEXCI6XCJ7dGN0YWd9XCIsXCJYLUFQSS1TaWduYXR1cmVcIjpcIntzaWduYXR1cmV9XCIsXCJYLUFQSS1UaW1lc3RhbXBcIjpcInt0aW1lc3RhbXB9XCJ9IiwiZ2FfcHBhcmFtIjoie1wiTXNnXCI6XCJ7TXNnfVwiLFwiZmdfcG9zdHR5cGVcIjpcImZvcm1cIn0iLCJnYV90eXBlIjoib3RoZXIiLCJnYV9ybHRmaWVsZCI6IiIsImdhX2tleWZpZWxkIjoie1wicmlnaHRmbGFnXCI6e1wia2V5XCI6XCJodHRwY29kZVwiLFwidmFsdWVcIjoyMDB9LFwidGNiYWxhbmNlXCI6e1wia2V5XCI6XCJyYXdkYXRhXCJ9fSIsImdhX3JhdGUiOjAsImdhX25lZWRzaWduIjowLCJnYV90YXNrY2ZnIjoiIiwiZ2FfdGFza3ZhbHVlIjoiIiwiZ2FfdXJscHJlIjpudWxsfV19';
    }
}