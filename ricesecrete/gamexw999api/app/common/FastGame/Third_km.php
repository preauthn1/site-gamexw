<?php

namespace app\common\FastGame;


use app\model\{GameBetsTemp, Games, GameWmSpecial, UserThirds};
use app\common\FastGame\Base\{Http, Meta, Third, ThirdTask, Utils};

class Third_km extends Third
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
        $ip_list = ["1.32.212.184","1.32.212.187","1.32.212.188","1.32.212.189","1.32.212.194","1.32.212.195","1.32.212.196","1.32.212.197","1.32.212.198","1.32.212.199","1.32.212.200","1.32.212.201","1.32.212.202","1.32.212.203","1.32.212.204","1.32.212.205","1.32.212.206","1.32.212.207","1.32.212.208","1.32.212.209","1.32.212.210","1.32.212.211","1.32.212.212","1.32.212.213","1.32.212.214","1.32.212.215","1.32.212.216","1.32.212.217","1.32.212.218","1.32.212.219","1.32.212.220","1.32.212.221","1.32.212.222","218.189.17.18","218.189.17.19","218.189.17.20","218.189.17.21","218.189.17.22","218.189.17.23","218.189.17.24","218.189.17.25","218.189.17.26","218.189.17.27","218.189.17.28","218.189.17.29","218.189.17.30","118.163.33.139","54.150.100.111","59.120.133.194","123.51.185.146","54.199.91.233","35.79.242.124","54.248.240.187","35.73.221.181","54.199.91.233","35.79.242.124"];
        $header = $data['header'];
        $param = $data['param'];
        //渠道id 等于数据库中的渠道id 或者ping请求的时候为true  并且请求ip要跟数据库定义的ip相等
        if(in_array($header['x-forwarded-for'], $ip_list)){
            if($param['key'] == $config['trdcfg']['tc_api_prikey']){
                return true;
            }
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
        return 'KM CLASS';
    }

    /**
     * @inheritDoc
     */
    function transOtherKvs(&$kvs)
    {
        $config = $this->apicfgs;
        $name = Meta::initGetField('uname', $config['g_tcid'], $config['g_id'], $kvs['uid']);
        $kvs['km_name'] = $name;
        $platform = $kvs['platform'] ?? 0;
        $kvs['platForm'] = $platform == 1 ? 'false' : 'true'; //平台标识 1 pc 2 h5  3 mobile  'platform' 1
        $kvs['gameType'] = $config['g_exfield'];
        $kvs['gcode'] = $config['g_code'];
        // TODO: Implement transOtherKvs() method.
    }


    function transResultData($func, &$kds, $datas, $tcid, $uid, $gid, $ohterkvs)
    {
        if($func == 'REGISTER'){
            $guser = UserThirds::where('ut_tcid',$tcid)->where('ut_uid',$uid)->find();
            if (!$guser){
                //TODO 错误处理
                UserThirds::create([
                    'ut_uid' => $uid,
                    'ut_tcid' => $tcid,
                    'ut_trdid' => $ohterkvs['km_name'],
                    'ut_intime' => time(),
                    'ut_state' => 0
                ]);
            }else{
                $guser->ut_trdid =  $ohterkvs['km_name'];
                //TODO 错误处理
                $guser->save();
            }
        }
    }



}
