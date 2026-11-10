<?php


namespace app\common\FastGame;


use app\common\lib\Util;
use app\common\FastGame\Base\{Game, Third, Utils, Http, TaskInterface, ThirdTask};
use app\model\ThirdConfigs;
use app\common\exception\MessageException;


class ThirdTask_mw extends ThirdTask implements TaskInterface
{

    /**
     * 继承类中开始执行三方平台任务
     * @return mixed
     * @author twt326
     * @date 2022-08-25 12:19
     */
    function exec()
    {
        // TODO 必须转三方时区
        date_default_timezone_set('Asia/Shanghai'); //转三方时区时间
        $cfgvalues=$this->cfgvalues;
        //执行任务
        $this->runThirdTasks();
        $this->runJs($cfgvalues['jsst']);
        $this->runFy($cfgvalues['fyst']);
        $this->runStat($cfgvalues['statst']);
        $this->updateConfigValue();
        //转转回项目时区
        date_default_timezone_set(env('DEFAULT_TIMEZONE', 'Asia/Shanghai')); //转三方时区时间

    }

    /** 根据读取列表的返回数据更新下次游戏列表的开始特征字段值
     * @param $gtag
     * @param $thistime
     * @param $data
     * @return mixed
     * @author twt326
     * @date 2022-09-19 21:26
     */
    function setBetListStartFlag($gtag, $thistime, $data)
    {
        $func = 'GETBETLIST';
        Utils::L('test',['准备保存API配置值', 'gcode'=>$gtag, 'thistime'=>$this, 'cfg'=>$this->apiscfgvalues[$func]]);

        $cfg=json_decode(json_encode($this->apiscfgvalues[$func]),true); //getApiCfgvalues($func,$gcode);
        $upflag = false;
        if (isset($cfg[$gtag])) {
            $upflag = true;
        }
        if ($upflag) $this->updateApiConfigValue($func,$cfg);
    }

    /**
     * 处理一条游戏记录数据为可写入注单表的状态
     * @param $gcode 游戏在三方平台的代码
     * @param $data 游戏注单数据（单条）
     * @return mixed
     * @author twt326
     * @date 2022-09-20 22:00
     */
    function procBetData($gcode, $data)
    {
        return $data;
    }

    //多条数据
    function procBetDataPre($gcfg, $data, $rlt){

        $EC_PRIVATE_KEY = str_replace("\\n", "\n", $gcfg['trdcfg']['tc_api_prikey']);

        $res_data = self::decrypt_response(json_encode($rlt),$EC_PRIVATE_KEY);

        $data_arr = [];
        if($res_data) {

            Utils::L("{$gcfg['trdcfg']['tc_id']}/ResultData",['获取注单数据', $res_data]);
            //echo json_encode($res_data);die;
            if($res_data['ret'] == '0000') {
                $data_arr = $res_data['userGameLogs'];
            }else{

                Utils::L("{$gcfg['trdcfg']['tc_id']}/ResultData_error",['获取注单数据失败',$res_data]);

            }
        }else{

            Utils::L("{$gcfg['trdcfg']['tc_id']}/ResultData_error",['获取注单数据异常',$data]);

        }

        $rd = [];
        Third::initExtClass(['trdcfg' => ['tc_code'=> 'MW' ]]);
        if(isset($data_arr['0'])){
            foreach ($data_arr as $v) {
                $gb_winlose = 2;
                $gb_price = Third::transExchangerate($v['playMoney'],0);//投注金额
                $gb_winprice = Third::transExchangerate($v['winMoney'],0);//赢金额
                if($v['winMoney'] > 0){
                    $gb_winlose = 3;
                }
                $time = strtotime($v['logDate']);
                $rd[] = [
                    'gb_op' => 'n',
                    'gb_gid'=>Game::getGamesInfoBy($gcfg['trdcfg']['tc_id'], $v['gameId'], 'tag', 'g_id'),
                    'gb_uname'=> $v['uid'],
                    'gb_winlose'=>$gb_winlose,
                    'gb_bettime'=>$time,
                    'gb_overtime'=>$time,
                    'gb_price'=>$gb_price,
                    'gb_sn'=> $v['wagerId'],
                    'gb_winprice'=>$gb_winprice,
                ];
            }
        }

        return empty($data) ? []:$rd;
    }


    /**
     * 获取游戏列表的开始特征字段
     * @param string $gtag 游戏三方代码
     * @param int $thistime 当前调用时间节点
     * @return mixed
     * @author twt326
     * @date 2022-09-18 17:23
     */
    function getBetListStartFlag($gtag,$thistime)
    {
        $func = 'GETBETLIST';
        $cfg = $this->getApiCfgvalues($func, $gtag);
        if ($cfg['lastruntime'] == 0) { //初始化
            $start = time() - 600; //前10分钟
            $endtime = $thistime - 300; //前10分钟
        } else {
            //比如上次执行时间是 10分  获取的是 00 - 05 分的数据 开始时间减 600 结束时间减 300
            //这次执行时间是15分 获取到上次的执行时间为 10分。 10分为结束时间 再减300 为开始时间
            //下次执行时间是20分 获取到上次的执行时间为 15分。15分为结束时间 再减300 为开始时间
            $start = $cfg['end']?? $thistime; //查询上一次执行的时间
//            if ($start + 3600 <= $thistime) {//超过一个小时
//                $start = $thistime - 600; //一个小时以前
//                $endtime = $thistime - 300;
//            } else {
            $endtime = $start;
            $start = $start - 300; //前5分钟
//            }
        }
        $flags = [
            "lastruntime" => $thistime,
            "end" => min($endtime + 240, $thistime),
            "beginTime" =>date('Y-m-d H:i:s', $start), //前8分钟
            "endTime" => date('Y-m-d H:i:s', $endtime), //前4分钟
        ];
        $this->apiscfgvalues[$func][$gtag] = array_merge($this->apiscfgvalues[$func][$gtag],$flags);
        return $flags;
    }

    /**
     * 重写蕨类的转移数据方法
     * @param $gid
     * @author twt326
     * @date 2022-09-20 22:50
     */
    public function transferBets($gid,$type){
        //业务代码开始
        parent::transferBets($gid,$type);//此处为示例，直接调用基类方法进行转移，如果有特殊数值需要处理，请在此处处理，是否调用基类方法因情而定
        //业务代码结束
    }

    public function runFy($lasttime)
    {
        // TODO: Implement runJs() method.
    }

    public function runJs($lasttime)
    {
        // TODO: Implement runJs() method.
    }

    function decrypt_response($response,$EC_PRIVATE_KEY) {

        $response_json = json_decode($response, true);
        $encryptKey = $response_json["key"]; // MW加密KEY
        $encryptData = $response_json["data"]; // MW加密DATA
        $aesKey = self::rsa_decrypt($encryptKey, $EC_PRIVATE_KEY); // EC方私鑰 解密 MW加密KEY 獲得 AESKEY
        $decryptData = self::aes_decrypt($aesKey, $encryptData); // AESKEY 解密 MW加密DATA 獲得 DATA
        //echo_log("DecryptData: $decryptData");
        return json_decode($decryptData, true);
    }

    function aes_decrypt($key, $str) {
        $encryptedData = base64_decode($str);
        $decrypted = openssl_decrypt($encryptedData, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $decrypted;
    }

    function rsa_decrypt($ciphertext, $prikey) {
        $receiveKey = base64_decode($ciphertext);
        $ecPrivateKey = openssl_pkey_get_private($prikey);
        openssl_private_decrypt($receiveKey, $decryptAesKey, $ecPrivateKey);
        return $decryptAesKey;
    }

}
