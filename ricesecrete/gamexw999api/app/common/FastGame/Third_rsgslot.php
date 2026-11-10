<?php


namespace app\common\FastGame;


use app\model\GameBetsTemp;
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};
use app\model\Games;
use app\api\redis\ApiRedis;
use app\common\FastGame\Base\Redis;

//RSGSLOT
class Third_rsgslot extends Third
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
}